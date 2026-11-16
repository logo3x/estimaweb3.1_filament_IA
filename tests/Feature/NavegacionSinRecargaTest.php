<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Cambiar de seccion sin recargar la pagina.
 *
 * El panel no vuelve a pedir la pagina entera al pulsar un enlace del
 * menu: sustituye el contenido y deja en su sitio los estilos, los scripts
 * y el propio menu, que son la mayor parte del peso. Ademas empieza a
 * cargar **al poner el raton encima**, antes del clic.
 *
 * Importa mas de lo habitual aqui: el servidor sera un hosting compartido,
 * donde cada arranque de Laravel se paga.
 */
class NavegacionSinRecargaTest extends TestCase
{
    use DatabaseTransactions;

    private function entrar(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Administrador');

        $this->actingAs($usuario);

        return $usuario;
    }

    /** El panel navega sin recargar. */
    public function test_el_panel_navega_sin_recargar(): void
    {
        $this->assertTrue(
            Filament::getPanel('admin')->hasSpaMode(),
            'El panel volvería a cargar la página entera en cada clic del menú.'
        );
    }

    /**
     * Y precarga al pasar el raton.
     *
     * Es lo que hace que el cambio se sienta inmediato: cuando se pulsa,
     * la pagina ya suele estar pedida.
     */
    public function test_precarga_al_pasar_el_raton(): void
    {
        $this->assertTrue(
            Filament::getPanel('admin')->hasSpaPrefetching(),
            'Los enlaces no se precargan: el cambio de sección se notará.'
        );
    }

    /** Los enlaces del menu llevan la marca que lo activa. */
    public function test_los_enlaces_del_menu_se_precargan(): void
    {
        $this->entrar();

        $this->get('/admin/cotizaciones')
            ->assertSuccessful()
            ->assertSee('wire:navigate.hover', escape: false);
    }

    /**
     * La barra de progreso se enciende al navegar.
     *
     * Livewire avisa con eventos, no con un atributo, asi que hace falta
     * la vista que los traduce. Sin ella el CSS no se entera de nada y la
     * barra no aparece nunca.
     */
    public function test_la_barra_de_progreso_esta_enganchada(): void
    {
        $this->entrar();

        $respuesta = $this->get('/admin/cotizaciones')->assertSuccessful();

        $respuesta->assertSee('livewire:navigate', escape: false);
        $respuesta->assertSee('data-navegando', escape: false);
    }

    /** Y el tema que la pinta esta cargado. */
    public function test_el_tema_del_panel_esta_cargado(): void
    {
        $this->entrar();

        $this->get('/admin/cotizaciones')
            ->assertSuccessful()
            ->assertSee('theme', escape: false);
    }

    /**
     * El tema se compila y llega a public/build.
     *
     * Si no, en el servidor el panel se veria sin ninguna de las
     * transiciones y sin la barra: alli no hay forma de compilar.
     */
    public function test_el_tema_esta_compilado(): void
    {
        $manifiesto = public_path('build/manifest.json');

        $this->assertFileExists($manifiesto, 'Falta public/build: ejecute npm run build.');

        $entradas = json_decode((string) file_get_contents($manifiesto), true);

        $this->assertArrayHasKey(
            'resources/css/filament/admin/theme.css',
            $entradas,
            'El tema del panel no está compilado. Añádalo a vite.config.js.'
        );
    }

    /**
     * La barra usa el color de la marca.
     *
     * `--primary-500` de Filament es un color completo -oklch(...)-, no
     * los tres componentes sueltos: envolverlo en `rgb(var(...))` lo deja
     * transparente y la barra no se ve, que fue lo que paso al escribirla.
     */
    public function test_la_barra_usa_el_color_de_la_marca_sin_envolverlo(): void
    {
        $tema = (string) file_get_contents(
            resource_path('css/filament/admin/theme.css')
        );

        $this->assertStringContainsString('var(--primary-500', $tema);
        $this->assertStringNotContainsString(
            'rgb(var(--primary-500',
            $tema,
            'Envolver --primary-500 en rgb() lo deja transparente.'
        );
    }

    /**
     * Las animaciones no obligan a rehacer el diseño de la pagina.
     *
     * Solo `opacity` y `transform` se resuelven en la tarjeta grafica.
     * Animar `height` o `width` obliga a recalcular posiciones en cada
     * fotograma, y dentro de las secciones plegables hay tablas.
     */
    public function test_las_animaciones_no_animan_medidas(): void
    {
        $tema = (string) file_get_contents(
            resource_path('css/filament/admin/theme.css')
        );

        foreach (['@keyframes'] as $bloque) {
            $this->assertStringContainsString($bloque, $tema);
        }

        /** Dentro de los keyframes no puede haber medidas. */
        preg_match_all('/@keyframes[^{]*\{(.*?)\n\}/s', $tema, $coincidencias);

        foreach ($coincidencias[1] as $cuerpo) {
            foreach (['height:', 'width:', 'margin:', 'padding:'] as $prohibido) {
                $this->assertStringNotContainsString(
                    $prohibido,
                    $cuerpo,
                    "Una animación modifica '{$prohibido}', que obliga a rehacer el diseño."
                );
            }
        }
    }

    /**
     * Quien pide menos movimiento no ve animaciones.
     *
     * A algunas personas el movimiento en pantalla les produce mareo, y el
     * sistema operativo ya recoge esa preferencia.
     */
    public function test_se_respeta_la_preferencia_de_menos_movimiento(): void
    {
        $tema = (string) file_get_contents(
            resource_path('css/filament/admin/theme.css')
        );

        $this->assertStringContainsString('prefers-reduced-motion', $tema);
    }
}
