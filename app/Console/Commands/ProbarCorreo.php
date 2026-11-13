<?php

namespace App\Console\Commands;

use App\Correo\AvisoDeAprobacion;
use App\Correo\DestinatariosDelAviso;
use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Models\Cotizacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Comprueba que el correo funciona, sin tocar ninguna cotizacion.
 *
 * Sirve para validar la configuracion tras desplegar: en cPanel no hay
 * acceso por SSH para depurar, de modo que conviene poder preguntarle al
 * propio sistema si puede enviar.
 *
 * Sin --enviar solo informa de como esta configurado y a quien avisaria;
 * no manda nada.
 */
class ProbarCorreo extends Command
{
    protected $signature = 'correo:probar
        {--enviar= : Dirección a la que mandar un correo de prueba}
        {--cotizacion=55 : Cotización de la que tomar los datos}';

    protected $description = 'Revisa la configuración del correo y, si se pide, envía una prueba';

    public function handle(): int
    {
        $this->mostrarConfiguracion();

        $cotizacion = Cotizacion::find((int) $this->option('cotizacion'));

        if (! $cotizacion) {
            $this->error('No existe esa cotización.');

            return self::FAILURE;
        }

        $this->mostrarDestinatarios($cotizacion);

        $destino = $this->option('enviar');

        if (! $destino) {
            $this->newLine();
            $this->line('No se envió nada. Use --enviar=alguien@dominio.com para mandar una prueba.');

            return self::SUCCESS;
        }

        return $this->enviarPrueba($cotizacion, (string) $destino);
    }

    private function mostrarConfiguracion(): void
    {
        $transporte = (string) config('mail.default');

        $this->components->twoColumnDetail('Transporte', $transporte);
        $this->components->twoColumnDetail('Remitente', (string) config('mail.from.address'));

        if ($transporte !== 'graph') {
            $this->newLine();
            $this->warn("El transporte es '{$transporte}', no 'graph'.");
            $this->line('En producción debe ser graph: MAIL_MAILER=graph en el .env.');

            return;
        }

        foreach ([
            'MAIL_GRAPH_TENANT_ID' => config('mail.mailers.graph.tenant_id'),
            'MAIL_GRAPH_CLIENT_ID' => config('mail.mailers.graph.client_id'),
            'MAIL_GRAPH_CLIENT_SECRET' => config('mail.mailers.graph.client_secret'),
            'MAIL_GRAPH_SENDER' => config('mail.mailers.graph.sender'),
        ] as $variable => $valor) {
            $this->components->twoColumnDetail(
                $variable,
                /** El secreto no se imprime. */
                blank($valor) ? '<fg=red>FALTA</>' : '<fg=green>definida</>'
            );
        }
    }

    /** A quien se avisaria en cada estado, sin enviar nada. */
    private function mostrarDestinatarios(Cotizacion $cotizacion): void
    {
        $this->newLine();
        $this->line("Avisos de la cotización {$cotizacion->id} ({$cotizacion->codigo}):");

        $destinatarios = new DestinatariosDelAviso();

        foreach (EstadoAprobacion::cases() as $estado) {
            $correos = $destinatarios->para($cotizacion, $estado)
                ->map(fn (array $a): string => (string) $a['usuario']->email)
                ->all();

            $this->components->twoColumnDetail(
                '  ' . $estado->etiquetaCorta(),
                $correos === [] ? '<fg=gray>nadie</>' : implode(', ', $correos)
            );
        }
    }

    private function enviarPrueba(Cotizacion $cotizacion, string $destino): int
    {
        $this->newLine();
        $this->line("Enviando una prueba a {$destino}...");

        try {
            Mail::to($destino)->send(new AvisoDeAprobacion(
                cotizacion: $cotizacion,
                estado: EstadoAprobacion::EsperandoTecnica,
                autorDeLaAccion: $cotizacion->autor,
                comentarios: 'Correo de prueba: la cotización no cambió de estado.',
            ));
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('No se pudo enviar: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Enviado. Revise la bandeja de entrada y la de no deseado.');

        return self::SUCCESS;
    }
}
