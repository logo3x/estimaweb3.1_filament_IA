<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * La pantalla de acceso dice a que sistema se entra.
 *
 * Filament solo pone "Entre a su cuenta". Sin mas contexto, quien llega desde
 * un enlace en un correo no reconoce la pantalla antes de escribir su
 * contraseña, que es justo lo que aprovecha una pagina suplantada.
 */
class PantallaDeAccesoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_identifica_el_sistema_y_la_empresa(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sistema de cotizaciones y presupuestos')
            ->assertSee('Confipetrol S.A.S');
    }

    /**
     * La marca va dibujada en la pagina, no como imagen.
     *
     * Asi se lee en modo oscuro y el acceso puede animar el emblema; si
     * alguien la cambiara por una imagen, ambas cosas se perderian sin aviso.
     */
    public function test_muestra_la_marca_de_estimaweb(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('aria-label="EstimaWeb 3.1"', escape: false)
            ->assertSee('fi-marca-emblema', escape: false)
            ->assertSeeInOrder(['ESTIMA', 'WEB', '3.1']);
    }

    /**
     * Dice a quien acudir si se olvida la contraseña.
     *
     * No hay recuperacion automatica: sin esta linea, quien la olvida no sabe
     * por donde empezar.
     */
    public function test_dice_a_quien_acudir_por_la_contrasena(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('área de administración', escape: false);
    }

    /** Los iconos se sirven desde el propio sitio, no desde un tercero. */
    public function test_los_iconos_son_locales(): void
    {
        $respuesta = $this->get('/admin/login')->assertOk();

        foreach (['favicon.ico', 'img/favicon-32.png', 'img/icono-192.png', 'apple-touch-icon.png'] as $icono) {
            $respuesta->assertSee(asset($icono), escape: false);
            $this->assertFileExists(public_path($icono));
        }
    }
}
