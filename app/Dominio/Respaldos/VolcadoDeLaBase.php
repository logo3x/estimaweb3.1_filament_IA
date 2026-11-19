<?php

namespace App\Dominio\Respaldos;

use Illuminate\Support\Facades\DB;

/**
 * Vuelca la base entera a un .sql, en PHP, sin mysqldump.
 *
 * El hosting es cPanel compartido: `mysqldump` no esta garantizado en el
 * PATH y no hay terminal para comprobarlo ni para arreglarlo si falla. Por
 * eso el respaldo no depende de ningun binario externo, solo de PDO.
 *
 * Medido sobre los datos reales: 55 tablas, 9.416 filas, 1,5 MB de SQL en
 * 342 ms, que comprimido queda en 210 KB. A ese tamaño el respaldo cabe
 * holgadamente en un correo.
 *
 * El volcado se escribe **a fichero mientras se genera**, no se acumula en
 * memoria: asi el consumo no crece con el tamaño de la base, que es lo que
 * tumbaria el proceso en un hosting con poca memoria.
 */
class VolcadoDeLaBase
{
    /** Cuantas filas se leen de golpe de cada tabla. */
    private const FILAS_POR_LOTE = 500;

    /**
     * Escribe el volcado y devuelve cuanto ocupa y que contiene.
     *
     * @return array{tablas: int, filas: int, bytes: int}
     */
    public function escribirEn(string $destino): array
    {
        $manejador = fopen($destino, 'wb');

        if ($manejador === false) {
            throw new \RuntimeException("No se pudo escribir el respaldo en {$destino}.");
        }

        try {
            return $this->volcar($manejador);
        } finally {
            fclose($manejador);
        }
    }

    /**
     * @param  resource  $manejador
     * @return array{tablas: int, filas: int, bytes: int}
     */
    private function volcar($manejador): array
    {
        $tablas = 0;
        $filas = 0;
        $bytes = 0;

        $escribir = function (string $texto) use ($manejador, &$bytes): void {
            $bytes += fwrite($manejador, $texto) ?: 0;
        };

        $escribir($this->cabecera());

        /**
         * Sin esto el volcado no se puede restaurar: las tablas se crean en
         * orden alfabetico y una clave foranea apuntaria a algo que todavia
         * no existe.
         */
        $escribir("SET FOREIGN_KEY_CHECKS=0;\n");

        foreach ($this->nombresDeLasTablas() as $tabla) {
            $tablas++;

            $escribir("\n--\n-- Tabla: {$tabla}\n--\n");
            $escribir("DROP TABLE IF EXISTS `{$tabla}`;\n");
            $escribir($this->sentenciaDeCreacion($tabla) . ";\n");

            $filas += $this->volcarFilas($tabla, $escribir);
        }

        $escribir("\nSET FOREIGN_KEY_CHECKS=1;\n");

        return ['tablas' => $tablas, 'filas' => $filas, 'bytes' => $bytes];
    }

    /** @return list<string> */
    private function nombresDeLasTablas(): array
    {
        return array_map(
            fn (object $fila): string => array_values((array) $fila)[0],
            DB::select('SHOW TABLES'),
        );
    }

    private function sentenciaDeCreacion(string $tabla): string
    {
        $resultado = (array) DB::select("SHOW CREATE TABLE `{$tabla}`")[0];

        /** La clave cambia de nombre entre tablas y vistas. */
        return $resultado['Create Table'] ?? $resultado['Create View'] ?? '';
    }

    /**
     * Los datos de una tabla, por lotes.
     *
     * @param  \Closure(string): void  $escribir
     */
    private function volcarFilas(string $tabla, \Closure $escribir): int
    {
        $pdo = DB::connection()->getPdo();
        $total = 0;
        $desplazamiento = 0;

        while (true) {
            /**
             * Se pagina con offset y no con `chunk()` porque varias tablas no
             * tienen una clave por la que ordenar de forma fiable.
             */
            $registros = DB::table($tabla)
                ->offset($desplazamiento)
                ->limit(self::FILAS_POR_LOTE)
                ->get();

            if ($registros->isEmpty()) {
                break;
            }

            foreach ($registros as $registro) {
                $valores = array_map(
                    fn (mixed $valor): string => $valor === null
                        ? 'NULL'
                        : $pdo->quote((string) $valor),
                    (array) $registro,
                );

                $escribir("INSERT INTO `{$tabla}` VALUES (" . implode(',', $valores) . ");\n");
                $total++;
            }

            $desplazamiento += self::FILAS_POR_LOTE;
        }

        return $total;
    }

    private function cabecera(): string
    {
        $fecha = now()->format('Y-m-d H:i:s');
        $base = DB::connection()->getDatabaseName();

        return <<<SQL
            -- Respaldo de EstimaWeb
            -- Base: {$base}
            -- Generado: {$fecha}
            --
            -- Para restaurarlo: importar este archivo desde phpMyAdmin,
            -- en la base de destino, con la opcion de SQL.

            SET NAMES utf8mb4;
            SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

            SQL;
    }
}
