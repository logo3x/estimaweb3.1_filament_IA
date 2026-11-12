<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras que le dicen al navegador como tratar las respuestas.
 *
 * Son defensas en profundidad: no arreglan un fallo del codigo, pero limitan
 * el dano si existe. Sin ellas, una inyeccion de HTML puede cargar scripts de
 * cualquier dominio, y el panel puede incrustarse en una pagina ajena para
 * enganar a quien lo usa.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        /** Impide que el panel se incruste en un iframe de otro sitio. */
        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');

        /**
         * Impide que el navegador adivine el tipo de un archivo. Sin esto,
         * un .txt con HTML dentro puede acabar ejecutandose como pagina.
         */
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');

        /** No filtrar la ruta completa al salir hacia otro dominio. */
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        /** El panel no necesita camara, microfono ni ubicacion. */
        $respuesta->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        /**
         * Obliga al navegador a usar HTTPS durante un ano.
         *
         * Solo sobre una conexion ya segura: enviarla por HTTP no tiene
         * efecto, y en desarrollo local dejaria el dominio bloqueado para
         * siempre en el navegador.
         */
        if ($request->secure()) {
            $respuesta->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $respuesta->headers->set('Content-Security-Policy', $this->politicaDeContenido());

        return $respuesta;
    }

    /**
     * De donde puede cargar recursos la pagina.
     *
     * Filament y Livewire generan estilos y algunos scripts en linea, de modo
     * que 'unsafe-inline' hace falta para que el panel funcione. Es una
     * politica mas permisiva de lo ideal, pero sigue cerrando lo esencial:
     * ningun script puede venir de un dominio externo.
     */
    private function politicaDeContenido(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            /** Nadie puede incrustar esta aplicacion en un marco. */
            "frame-ancestors 'self'",
            /** Impide inyectar una etiqueta base que reescriba las rutas. */
            "base-uri 'self'",
            /** Los formularios solo pueden enviarse a esta misma aplicacion. */
            "form-action 'self'",
        ]);
    }
}
