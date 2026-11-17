<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * El rol, visible sin abrir el menu.
 *
 * En la barra superior Filament pinta el boton del perfil solo con el avatar:
 * quien entra no ve con que permisos trabaja. Importa porque los roles SUMAN
 * -seis personas acumulan varios, una llega a cuatro- y lo que cada quien
 * alcanza depende del de mas autoridad, no del primero que devuelva la base.
 */
class RolEnLaBarraTest extends TestCase
{
    use DatabaseTransactions;

    private function usuarioCon(string ...$roles): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($roles);

        return $usuario;
    }

    /** El rol aparece en el panel, sin desplegar nada. */
    public function test_el_rol_se_ve_en_la_barra(): void
    {
        $this->actingAs($this->usuarioCon('Creador'))
            ->get('/admin')
            ->assertOk()
            ->assertSee('fi-rol-barra-chip', escape: false)
            ->assertSee('Creador');
    }

    /**
     * Con varios roles se muestra el de mas autoridad.
     *
     * Un gerente que ademas crea cotizaciones salia como "Creador" pese a ser
     * quien aprueba tecnicamente.
     */
    public function test_con_varios_roles_manda_el_de_mas_autoridad(): void
    {
        $respuesta = $this->actingAs($this->usuarioCon('Creador', 'Gerente'))
            ->get('/admin')
            ->assertOk();

        $html = $respuesta->getContent();
        $chip = $this->chipDelRol($html);

        $this->assertSame('Gerente', $chip);
    }

    /** Sin rol no se pinta un hueco vacio. */
    public function test_sin_rol_no_se_pinta_la_etiqueta(): void
    {
        /** Sin rol no se entra al panel, de modo que se mira el metodo. */
        $this->assertNull($this->usuarioCon()->rolPrincipal());
    }

    /** Lo que hay dentro de la etiqueta del rol. */
    private function chipDelRol(string $html): string
    {
        $encontrado = preg_match(
            '/fi-rol-barra-chip[^>]*>\s*([^<]+?)\s*</',
            $html,
            $coincidencias,
        );

        $this->assertSame(1, $encontrado, 'No se encontró la etiqueta del rol.');

        return $coincidencias[1];
    }
}
