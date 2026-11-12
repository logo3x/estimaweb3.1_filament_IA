<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Cabeceras de seguridad en TODAS las respuestas.
         *
         * Se registran como middleware global y no en el grupo "web": el
         * panel de Filament define su propia pila de middleware, asi que lo
         * anadido a "web" no llega a sus rutas, que son justamente las que
         * hay que proteger.
         */
        $middleware->append(\App\Http\Middleware\CabecerasDeSeguridad::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
