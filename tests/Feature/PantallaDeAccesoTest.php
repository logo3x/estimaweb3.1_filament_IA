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
            ->assertSee('Sistema de cotizaciones')
            ->assertSee('Confipetrol S.A.S');
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

    /** El isotipo se sirve desde el propio sitio, no desde un tercero. */
    public function test_el_logo_es_local(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('img/isotipo-confipetrol.jpg', escape: false);

        $this->assertFileExists(public_path('img/isotipo-confipetrol.jpg'));
    }
}
