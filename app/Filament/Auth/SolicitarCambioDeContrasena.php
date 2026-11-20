<?php

namespace App\Filament\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Notifications\Notification;
use Filament\Support\Components\Attributes\Unexposed;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;

/**
 * "¿Olvidó su contraseña?": pedir el enlace por correo.
 *
 * Responde SIEMPRE lo mismo, exista o no el correo. La pagina de Filament
 * contesta "no hay ninguna cuenta con ese correo" cuando no existe, y con
 * eso cualquiera puede averiguar quien tiene cuenta en el sistema probando
 * direcciones. Lo mismo con el aviso de "espere antes de volver a
 * intentarlo", que solo sale para las cuentas que existen.
 */
class SolicitarCambioDeContrasena extends RequestPasswordReset
{
    /** De usted, como el resto del acceso: la traduccion de Filament tutea. */
    public function getTitle(): string|Htmlable
    {
        return 'Cambiar la contraseña';
    }

    #[Unexposed]
    public function getHeading(): string|Htmlable|null
    {
        return '¿Olvidó su contraseña?';
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()->label('Enviarme el enlace');
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getSentNotification(Password::RESET_LINK_SENT);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title('Revise su correo')
            ->body('Si el correo corresponde a una cuenta del sistema, en unos minutos le llegará un enlace para cambiar la contraseña. Si no llega, revise el correo no deseado o pídala al área de administración.')
            ->success()
            ->persistent();
    }
}
