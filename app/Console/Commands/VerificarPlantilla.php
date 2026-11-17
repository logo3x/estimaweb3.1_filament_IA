<?php

namespace App\Console\Commands;

use App\Dominio\Documentos\GeneradorDeOferta;
use App\Models\Cotizacion;
use Illuminate\Console\Command;
use Throwable;
use ZipArchive;

/**
 * Comprueba que una plantilla encaja con lo que el sistema rellena.
 *
 * La plantilla real lleva el membrete de la empresa y vive solo en el
 * servidor, asi que no se puede probar hasta tenerla delante. Este comando
 * la revisa en un paso: que marcadores tiene, cuales faltan, cuales sobran,
 * y si genera una oferta de verdad.
 *
 * Mejor descubrir aqui que el marcador se llama distinto, que enviarle al
 * cliente una oferta sin la tabla de precios.
 */
class VerificarPlantilla extends Command
{
    protected $signature = 'plantilla:verificar
        {archivo? : Ruta del .docx a revisar. Por defecto, la instalada}
        {--instalar : Si encaja, la deja como plantilla del sistema}
        {--cotizacion=55 : Cotización con la que probar}';

    protected $description = 'Revisa que una plantilla de Word encaje con el sistema';

    /**
     * Los marcadores que el sistema sabe rellenar.
     *
     * Si la plantilla tiene uno que no esta aqui, se quedara literal en el
     * documento -un `${vigencia}` suelto que el cliente ve-.
     *
     * @var list<string>
     */
    private const QUE_RELLENAMOS = [
        'fecha', 'codigo', 'cliente', 'contacto', 'cargo',
        'nombre', 'referencia', 'alcance', 'observaciones', 'tabla',
    ];

    /**
     * Sin estos la oferta sale mal de verdad.
     *
     * `tabla` es el mas delicado: si falta, el documento se genera **sin
     * precios** y sin avisar.
     *
     * @var list<string>
     */
    private const IMPRESCINDIBLES = ['tabla', 'observaciones', 'cliente', 'alcance'];

    public function handle(): int
    {
        $archivo = (string) ($this->argument('archivo')
            ?: storage_path('app/plantillas/oferta.docx'));

        if (! is_file($archivo)) {
            $this->components->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }

        $this->components->info('Revisando ' . basename($archivo));
        $this->newLine();

        $marcadores = $this->marcadoresDe($archivo);

        if ($marcadores === null) {
            return self::FAILURE;
        }

        $faltan = array_diff(self::IMPRESCINDIBLES, $marcadores);
        $sobran = array_diff($marcadores, self::QUE_RELLENAMOS);

        $this->mostrarMarcadores($marcadores, $faltan, $sobran);
        $this->mostrarAspecto($archivo);

        if ($faltan !== []) {
            $this->newLine();
            $this->components->error(
                'Faltan marcadores imprescindibles: ${' . implode('}, ${', $faltan) . '}'
            );
            $this->line('  La oferta saldría incompleta. Revise que sea la plantilla correcta.');

            return self::FAILURE;
        }

        if (! $this->probarGeneracion($archivo)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('La plantilla encaja: el sistema rellena todo lo que pide.');

        if ($sobran !== []) {
            $this->components->warn(
                'Estos marcadores se quedarán literales en el documento: ${'
                . implode('}, ${', $sobran) . '}'
            );
            $this->line('  O se quitan de la plantilla, o hay que añadirlos al generador.');
        }

        return $this->option('instalar')
            ? $this->instalar($archivo)
            : $this->recordarComoInstalar($archivo);
    }

    /**
     * Los marcadores que trae la plantilla.
     *
     * @return list<string>|null
     */
    private function marcadoresDe(string $archivo): ?array
    {
        $zip = new ZipArchive;

        if ($zip->open($archivo) !== true) {
            $this->components->error('No es un .docx válido: no se pudo abrir.');

            return null;
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === '') {
            $this->components->error('El archivo no parece un documento de Word.');

            return null;
        }

        preg_match_all('/\$\{([a-z_]+)\}/i', $xml, $encontrados);

        return array_values(array_unique($encontrados[1]));
    }

    /** @param list<string> $marcadores */
    private function mostrarMarcadores(array $marcadores, array $faltan, array $sobran): void
    {
        $filas = [];

        foreach (self::QUE_RELLENAMOS as $marcador) {
            $esta = in_array($marcador, $marcadores, true);

            $filas[] = [
                '${' . $marcador . '}',
                $esta ? '<fg=green>está</>' : '<fg=gray>no está</>',
                in_array($marcador, self::IMPRESCINDIBLES, true)
                    ? ($esta ? '' : '<fg=red>IMPRESCINDIBLE</>')
                    : 'opcional',
            ];
        }

        foreach ($sobran as $marcador) {
            $filas[] = ['${' . $marcador . '}', '<fg=yellow>sobra</>', 'el sistema no lo rellena'];
        }

        $this->table(['Marcador', 'En la plantilla', 'Nota'], $filas);
    }

    /** Si lleva membrete, que es lo que distingue la real de la de prueba. */
    private function mostrarAspecto(string $archivo): void
    {
        $zip = new ZipArchive;
        $zip->open($archivo);

        $imagenes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_contains((string) $zip->getNameIndex($i), 'media/')) {
                $imagenes++;
            }
        }

        $zip->close();

        $this->components->twoColumnDetail('Tamaño', number_format(filesize($archivo) / 1024, 1, ',', '.') . ' KB');
        $this->components->twoColumnDetail(
            'Imágenes (membrete)',
            $imagenes > 0
                ? "<fg=green>{$imagenes}</>"
                : '<fg=yellow>ninguna: parece la de prueba</>'
        );
    }

    /** Genera una oferta de verdad con ella. */
    private function probarGeneracion(string $archivo): bool
    {
        $cotizacion = Cotizacion::find((int) $this->option('cotizacion'));

        if (! $cotizacion) {
            $this->components->warn('No existe esa cotización: no se pudo probar la generación.');

            return true;
        }

        $this->newLine();
        $this->components->info("Generando una oferta de prueba con la cotización {$cotizacion->id}...");

        try {
            $documento = GeneradorDeOferta::crear($archivo)->generar($cotizacion);

            $this->components->twoColumnDetail(
                'Documento generado',
                number_format(filesize($documento) / 1024, 1, ',', '.') . ' KB'
            );

            @unlink($documento);

            return true;
        } catch (Throwable $e) {
            $this->components->error('No se pudo generar: ' . $e->getMessage());

            return false;
        }
    }

    private function instalar(string $archivo): int
    {
        $destino = storage_path('app/plantillas/oferta.docx');

        if (! is_dir(dirname($destino))) {
            mkdir(dirname($destino), 0775, true);
        }

        if (is_file($destino)) {
            $respaldo = $destino . '.' . date('Ymd-His') . '.anterior';
            copy($destino, $respaldo);
            $this->components->info('La anterior se guardó como ' . basename($respaldo));
        }

        if (! copy($archivo, $destino)) {
            $this->components->error('No se pudo copiar a ' . $destino);

            return self::FAILURE;
        }

        $this->components->info('Instalada en storage/app/plantillas/oferta.docx');
        $this->line('  Recuerde: NO viaja en el repositorio. En el servidor hay que subirla a mano.');

        return self::SUCCESS;
    }

    private function recordarComoInstalar(string $archivo): int
    {
        if (realpath($archivo) !== realpath(storage_path('app/plantillas/oferta.docx'))) {
            $this->newLine();
            $this->line('  Para dejarla como plantilla del sistema:');
            $this->line("  php artisan plantilla:verificar \"{$archivo}\" --instalar");
        }

        return self::SUCCESS;
    }
}
