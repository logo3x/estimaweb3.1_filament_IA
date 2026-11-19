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
             * La marca, en el acceso y en la barra.
             *
             * El isotipo sale de la propia plantilla de la oferta, que es la
             * imagen corporativa que ya usa la empresa. Se usa el simbolo
             * solo y no el logotipo completo porque aquel lleva "Sucursal
             * Perú" y este panel es el de Colombia.
             */
            ->brandName('EstimaWeb 3.1')
            ->brandLogo(asset('img/isotipo-confipetrol.jpg'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('img/isotipo-confipetrol.jpg'))
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
            /**
             * Cambiar de seccion sin recargar la pagina.
             *
             * Livewire sustituye el contenido en vez de pedir la pagina
             * entera: no se vuelven a descargar los estilos, los scripts ni
             * el menu, que es la mayor parte del peso de cada carga.
             *
             * Con `hasPrefetching` la pagina empieza a cargarse **al poner
             * el raton encima del enlace**, antes del clic. Para cuando se
             * pulsa, lo normal es que ya este lista.
             *
             * Importa aqui mas que en otros paneles: el servidor sera un
             * hosting compartido, donde cada arranque de Laravel cuesta.
             */
            ->spa(hasPrefetching: true)
            /**
             * Enciende la barra de progreso al cambiar de seccion.
             *
             * Livewire avisa de la navegacion con eventos, no con un
             * atributo, asi que el CSS no puede reaccionar solo: esta vista
             * traduce el evento a un atributo en <html> y el tema se
             * encarga de pintarla.
             */
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.barra-de-progreso')->render(),
            )
            /**
             * El rol, visible sin abrir el menu.
             *
             * En la barra superior Filament pinta el boton del perfil solo
             * con el avatar: quien entra no ve con que permisos trabaja. Y
             * los roles SUMAN -seis personas acumulan varios-, de modo que
             * adivinarlo por las opciones que salen no es fiable.
             */
            /**
             * El acceso dice a que sistema se entra y a quien acudir.
             *
             * Filament solo pone "Entre a su cuenta". Sin decir de que
             * sistema es, quien llega desde un enlace no reconoce la
             * pantalla antes de escribir su contraseña, que es justo lo que
             * aprovecha una pagina suplantada.
             */
            ->renderHook(
                \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.acceso-encabezado')->render(),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.acceso-pie')->render(),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.rol-en-la-barra')->render(),
            )
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
