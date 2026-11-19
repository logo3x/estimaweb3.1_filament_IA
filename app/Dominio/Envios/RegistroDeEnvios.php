<?php

namespace App\Dominio\Envios;

use App\Models\Cotizacion;
use App\Models\ImpresionRegistro;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Deja constancia de cada documento generado y de cada correo enviado.
 *
 * Es el respaldo ante un cliente que discute una oferta: dice que salio,
 * cuando, quien lo hizo y a quien se le mando.
 *
 * Dos decisiones heredadas del sistema anterior, a proposito al reves:
 *
 * 1. Alli el registro se escribia DENTRO de `exportToWord()`, mezclado con
 *    el recalculo del AIU. Aqui es un servicio aparte, de modo que generar
 *    un documento y registrarlo son cosas separadas.
 *
 * 2. Alli un fallo al registrar habria tumbado la descarga. Aqui **nunca**:
 *    si la bitacora falla se anota en el log, pero el usuario se lleva su
 *    documento. Perder la oferta por no poder escribir una fila seria peor
 *    que perder la fila.
 */
class RegistroDeEnvios
{
    /** Anota un documento generado: el Word o el PDF. */
    public function documentoGenerado(
        Cotizacion $cotizacion,
        TipoDeEnvio $tipo,
        ?User $quien = null,
    ): ?ImpresionRegistro {
        return $this->anotar($cotizacion, $tipo, $quien);
    }

    /** Anota un correo que salio -o que se intento- hacia una direccion. */
    public function correoEnviado(
        Cotizacion $cotizacion,
        string $correoDestino,
        TipoDeEnvio $tipo = TipoDeEnvio::CorreoAlCliente,
        ?User $quien = null,
        ?string $fallo = null,
    ): ?ImpresionRegistro {
        return $this->anotar($cotizacion, $tipo, $quien, $correoDestino, $fallo);
    }

    /**
     * Escribe la fila.
     *
     * El destinatario y la referencia salen de la cotizacion, como en el
     * sistema actual, pero **sin el `'N/A'`** de alli: un dato que falta se
     * guarda como nulo, no como un texto que luego hay que interpretar.
     */
    private function anotar(
        Cotizacion $cotizacion,
        TipoDeEnvio $tipo,
        ?User $quien,
        ?string $correoDestino = null,
        ?string $fallo = null,
    ): ?ImpresionRegistro {
        $usuario = $quien ?? auth()->user();

        /**
         * Sin usuario no se registra, y se dice por que.
         *
         * La columna no admite nulo: intentarlo lanzaria una excepcion que
         * el catch de abajo convertiria en un log confuso. Pasa cuando algo
         * se dispara desde el cron, sin sesion.
         */
        if ($usuario === null) {
            Log::warning('Envío sin usuario: no se registra.', [
                'cotizacion' => $cotizacion->id,
                'tipo' => $tipo->value,
            ]);

            return null;
        }

        try {
            return ImpresionRegistro::create([
                'cotizacion_id' => $cotizacion->id,
                'tipo' => $tipo->value,
                'fecha_hora' => now(),
                'destinatario' => $this->recortar($cotizacion->contacto) ?? 'Sin contacto',
                'correo_destino' => $this->recortar($correoDestino),
                'usuario_id' => $usuario?->id,
                'referencia' => $this->recortar($cotizacion->referencia) ?? '',
                'fallo' => $this->recortar($fallo),
            ]);
        } catch (\Throwable $e) {
            /**
             * Que falle la bitacora no puede costarle el documento al
             * usuario: se anota y se sigue.
             */
            Log::error('No se pudo registrar el envío.', [
                'cotizacion' => $cotizacion->id,
                'tipo' => $tipo->value,
                'motivo' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Las columnas son de 255: un texto mas largo reventaria la insercion. */
    private function recortar(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, 255);
    }
}
