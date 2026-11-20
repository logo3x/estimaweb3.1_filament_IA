<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * El correo con el enlace para cambiar la contraseña.
 *
 * Sustituye a la notificacion de Filament por una razon concreta: aquella
 * implementa ShouldQueue, y en el servidor no hay un proceso que atienda la
 * cola. El correo se quedaba esperando para siempre y el usuario veia "le
 * hemos enviado el enlace" sin recibir nada. Es exactamente lo que paso en
 * el sistema anterior. Esta se envia en el acto, como el resto de los
 * correos del sistema.
 *
 * Ademas el texto va en español y dice de que sistema viene, para que no se
 * confunda con un intento de suplantacion.
 */
class RestablecerContrasena extends ResetPassword
{
    /** Filament la rellena con la direccion de su pantalla de cambio. */
    public string $url = '';

    public function toMail(mixed $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Cambiar su contraseña de EstimaWeb')
            ->greeting('Hola, '.(strtok(trim((string) ($notifiable->name ?? '')), ' ') ?: 'buen día').'.')
            ->line('Recibimos una solicitud para cambiar la contraseña de su cuenta en EstimaWeb, el sistema de cotizaciones de Confipetrol.')
            ->action('Cambiar la contraseña', $this->url)
            ->line("El enlace vale durante {$minutos} minutos y sirve una sola vez.")
            ->line('Si usted no lo pidió, ignore este correo: su contraseña sigue siendo la misma.')
            ->salutation('EstimaWeb · Confipetrol');
    }
}
