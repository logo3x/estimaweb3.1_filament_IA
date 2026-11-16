<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            /**
             * Cada quien cambia su nombre y su clave aqui.
             *
             * Es lo que sustituye a la autoedicion de la ficha: aquella
             * abria el formulario de administracion, con el selector de
             * roles y la gerencia dentro, y permitia ascenderse. Esta
             * pantalla no expone permisos.
             *
             * `isSimple: false` la deja dentro del panel, con su menu, en
             * vez de en una pagina suelta.
             */
            ->profile(isSimple: false)
            /**
             * Avatares generados en local.
             *
             * Filament los pide por defecto a ui-avatars.com, lo que envia el
             * nombre de cada usuario a un tercero en cada carga y obligaria a
             * abrir la politica de contenido para ese dominio.
             */
            ->defaultAvatarProvider(\App\Filament\AvatarLocal::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            /**
             * El menu lateral se puede plegar.
             *
             * Ocupa 320 px fijos. En un portatil de 1366 eso deja 1031 para
             * el contenido, y el listado de cotizaciones necesita 1416:
             * habia que desplazarse en horizontal para ver el total y el
             * estado, que es justo lo que se va a mirar.
             */
            ->sidebarCollapsibleOnDesktop()
            /**
             * Tema propio: solo transiciones y lectura de cifras.
             *
             * Se compila con Vite, asi que `desplegar:preparar` lo incluye
             * en public/build y viaja al servidor como el resto.
             */
            ->viteTheme('resources/css/filament/admin/theme.css')
            /** Los catalogos son nueve: agrupados, el menu cabe sin desplazar. */
            ->navigationGroups([
                'Mis cajas',
                'Catálogos',
                'Administración',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
