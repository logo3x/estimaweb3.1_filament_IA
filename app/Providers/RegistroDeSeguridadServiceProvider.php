<?php

namespace App\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Deja rastro de los accesos al sistema.
 *
 * Sin esto no hay forma de saber si alguien intento entrar: el sistema actual
 * no registra ni un intento fallido. Con cotizaciones de cientos de millones
 * y un flujo de aprobaciones, ante una sospecha no habria nada que revisar.
 *
 * Lo que se registra: quien, desde donde y cuando. Nunca la contrasena ni
 * ningun otro secreto, porque los registros suelen acabar en sitios menos
 * protegidos que la base de datos.
 */
class RegistroDeSeguridadServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(function (Failed $evento): void {
            Log::channel('seguridad')->warning('Intento de acceso fallido', [
                'correo' => $this->correoDe($evento->credentials),
                'ip' => request()->ip(),
                'navegador' => request()->userAgent(),
                'usuario_existe' => $evento->user !== null,
            ]);
        });

        Event::listen(function (Login $evento): void {
            Log::channel('seguridad')->info('Acceso correcto', [
                'usuario_id' => $evento->user->getAuthIdentifier(),
                'correo' => $evento->user->email ?? null,
                'ip' => request()->ip(),
            ]);
        });

        Event::listen(function (Logout $evento): void {
            Log::channel('seguridad')->info('Cierre de sesion', [
                'usuario_id' => $evento->user?->getAuthIdentifier(),
                'ip' => request()->ip(),
            ]);
        });

        /** Se supero el limite de intentos: es la senal de una fuerza bruta. */
        Event::listen(function (Lockout $evento): void {
            Log::channel('seguridad')->alert('Acceso bloqueado por exceso de intentos', [
                'ip' => $evento->request->ip(),
                'correo' => $this->correoDe($evento->request->only('email')),
            ]);
        });
    }

    /**
     * Extrae el correo de unas credenciales, descartando todo lo demas.
     *
     * Las credenciales traen la contrasena; dejarlas pasar enteras al
     * registro la escribiria en texto plano en un archivo.
     *
     * @param  array<string, mixed>  $credenciales
     */
    private function correoDe(array $credenciales): ?string
    {
        $correo = $credenciales['email'] ?? null;

        return is_string($correo) ? $correo : null;
    }
}
