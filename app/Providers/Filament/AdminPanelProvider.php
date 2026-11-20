<?php

namespace App\Providers\Filament;

use App\Filament\Auth\SolicitarCambioDeContrasena;
use App\Filament\AvatarLocal;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
             * "¿Olvido su contraseña?", con el correo de Microsoft 365.
             *
             * La pagina propia no revela que correos tienen cuenta, y la
             * notificacion se envia en el acto (no hay cola en el servidor).
             */
            ->passwordReset(SolicitarCambioDeContrasena::class)
            /**
             * La marca, en el acceso y en la barra.
             *
             * Es el logo propio de EstimaWeb 3.1, dibujado en SVG y texto
             * (ver la vista): asi se lee igual en modo oscuro y el acceso
             * puede animar cada pieza del emblema.
             */
            ->brandName('EstimaWeb 3.1')
            ->brandLogo(fn (): HtmlString => new HtmlString(view('filament.marca')->render()))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.ico'))
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
            ->defaultAvatarProvider(AvatarLocal::class)
            /**
             * Los colores del logo: azul corporativo para lo principal y
             * naranja para lo que pide atencion.
             *
             * El azul va con la paleta escrita a mano: `Color::hex()` solo
             * toma el tono y lo reparte en luminosidades fijas, y con ellas
             * el 600 (el de los botones) salia celeste con texto oscuro. Asi
             * el 600 es exactamente #0B4A8B.
             */
            ->colors([
                'primary' => [
                    50 => 'oklch(0.970 0.014 254.211)',
                    100 => 'oklch(0.935 0.030 254.211)',
                    200 => 'oklch(0.880 0.058 254.211)',
                    300 => 'oklch(0.790 0.090 254.211)',
                    400 => 'oklch(0.660 0.120 254.211)',
                    500 => 'oklch(0.530 0.130 254.211)',
                    600 => 'oklch(0.411 0.123 254.211)',
                    700 => 'oklch(0.360 0.105 254.211)',
                    800 => 'oklch(0.310 0.088 254.211)',
                    900 => 'oklch(0.265 0.072 254.211)',
                    950 => 'oklch(0.200 0.052 254.211)',
                ],
                'warning' => Color::hex('#F08A1F'),
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
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.barra-de-progreso')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.iconos')->render(),
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
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.acceso-encabezado')->render(),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.acceso-pie')->render(),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('filament.rol-en-la-barra')->render(),
            )
            /**
             * El aviso de estar actuando como otra persona.
             *
             * Bajo la barra y en TODAS las pantallas: lo peligroso no es
             * entrar como otro, sino olvidar que se entro. Ademas es el
             * camino de vuelta, que tiene que estar siempre a la vista.
             */
            ->renderHook(
                PanelsRenderHook::TOPBAR_AFTER,
                fn (): string => view('filament.aviso-de-suplantacion')->render(),
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
            /** El saludo con lo pendiente (Widgets\Bienvenida) reemplaza al generico de Filament. */
            ->widgets([])
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
