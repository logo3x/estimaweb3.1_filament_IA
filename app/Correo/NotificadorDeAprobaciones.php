<?php

namespace App\Correo;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envia los avisos del flujo de aprobacion.
 *
 * Dos decisiones que conviene tener presentes:
 *
 * 1. Se envia EN EL MOMENTO, no en cola. El alojamiento es cPanel
 *    compartido, sin acceso por SSH ni un proceso permanente que vacie la
 *    cola: encolar seria acumular correos que nadie llega a enviar. Ya paso
 *    con la recuperacion de contraseña, cuya notificacion quedaba en una
 *    cola sin trabajador y no llegaba nunca.
 *
 * 2. Un fallo de correo NO tumba la aprobacion. La decision ya esta
 *    guardada cuando se llama aqui; que Microsoft no responda no puede
 *    deshacerla ni dejar al usuario con una pantalla de error. Se registra
 *    y se informa de cuantos avisos salieron.
 */
final class NotificadorDeAprobaciones
{
    public function __construct(
        private readonly DestinatariosDelAviso $destinatarios,
    ) {}

    public static function crear(): self
    {
        return new self(new DestinatariosDelAviso());
    }

    /**
     * Avisa a quien corresponda del estado al que llego la cotizacion.
     *
     * @return ResultadoDelAviso que salio y que no, para poder decirselo al usuario
     */
    public function avisar(
        Cotizacion $cotizacion,
        EstadoAprobacion $estado,
        ?User $autorDeLaAccion = null,
        ?string $comentarios = null,
    ): ResultadoDelAviso {
        $avisos = $this->destinatarios->para($cotizacion, $estado);

        $enviados = 0;
        $fallidos = [];

        foreach ($avisos as $aviso) {
            $usuario = $aviso['usuario'];

            try {
                Mail::to($usuario->email)->send(new AvisoDeAprobacion(
                    cotizacion: $cotizacion,
                    estado: $estado,
                    autorDeLaAccion: $autorDeLaAccion,
                    comentarios: $comentarios,
                    paraElAutor: $aviso['paraElAutor'],
                ));

                $enviados++;
            } catch (Throwable $e) {
                $fallidos[] = (string) $usuario->email;

                /**
                 * Se registra con el detalle necesario para reenviarlo a
                 * mano: el sistema actual traga estos fallos en silencio.
                 */
                Log::error('No se pudo enviar el aviso de aprobación.', [
                    'cotizacion' => $cotizacion->id,
                    'estado' => $estado->value,
                    'destinatario' => $usuario->email,
                    'motivo' => $e->getMessage(),
                ]);
            }
        }

        return new ResultadoDelAviso($enviados, $fallidos);
    }
}
