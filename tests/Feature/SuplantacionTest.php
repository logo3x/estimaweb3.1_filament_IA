<?php

namespace Tests\Feature;

use App\Dominio\Auditoria\AccionAuditada;
use App\Dominio\Suplantacion\Suplantacion;
use App\Models\RegistroDeCambio;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Entrar al panel como otra persona.
 *
 * Resuelve un "a mi no me aparece" sin pedir la contraseña ajena, que es
 * la alternativa y es peor: una clave compartida deja de identificar a
 * quien actua.
 *
 * Lo que de verdad hay que fijar aqui no es que se pueda entrar, sino que
 * se pueda **volver**: si quien suplanta queda atrapado, la unica salida
 * seria borrarle la sesion a mano, y en cPanel no hay consola.
 */
class SuplantacionTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Se entra y se cambia de identidad de verdad. */
    public function test_se_entra_como_otra_persona(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');

        $this->actingAs($super);

        (new Suplantacion())->entrarComo($creador);

        $this->assertTrue(Auth::user()->is($creador), 'Debería estar dentro del otro usuario.');
    }

    /**
     * Y se puede VOLVER.
     *
     * Es la prueba mas importante del archivo.
     */
    public function test_se_puede_volver_a_la_propia_cuenta(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');

        $this->actingAs($super);

        $suplantacion = new Suplantacion();
        $suplantacion->entrarComo($creador);

        $vuelto = $suplantacion->volver();

        $this->assertTrue($vuelto->is($super));
        $this->assertTrue(Auth::user()->is($super));
        $this->assertFalse($suplantacion->estaEnCurso(), 'No debe quedar rastro en la sesión.');
    }

    /** Mientras dura, se sabe quien esta detras. */
    public function test_se_sabe_quien_esta_detras(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');

        $this->actingAs($super);

        $suplantacion = new Suplantacion();
        $suplantacion->entrarComo($creador);

        $this->assertTrue($suplantacion->estaEnCurso());
        $this->assertTrue($suplantacion->quienSuplanta()->is($super));
    }

    /**
     * No se encadena.
     *
     * Saltar de uno a otro volveria imposible reconstruir quien hizo que,
     * que es justo lo que esto debe conservar.
     */
    public function test_no_se_pueden_encadenar_suplantaciones(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');
        $gerente = $this->usuario('Gerente');

        $this->actingAs($super);

        $suplantacion = new Suplantacion();
        $suplantacion->entrarComo($creador);

        $this->expectException(DomainException::class);

        $suplantacion->entrarComo($gerente);
    }

    /**
     * Ni siquiera un Administrador puede suplantar.
     *
     * Es mas delicado que administrar usuarios: durante ese rato se actua
     * con los permisos del otro.
     */
    public function test_un_administrador_no_puede_suplantar(): void
    {
        $this->actingAs($this->usuario('Administrador'));

        $this->expectException(DomainException::class);

        (new Suplantacion())->entrarComo($this->usuario('Creador'));
    }

    /** Un Creador tampoco, por supuesto. */
    public function test_un_creador_no_puede_suplantar(): void
    {
        $this->actingAs($this->usuario('Creador'));

        $this->assertFalse((new Suplantacion())->puedeEntrarComo($this->usuario('Gerente')));
    }

    /** No tiene sentido entrar como uno mismo. */
    public function test_no_se_entra_como_uno_mismo(): void
    {
        $super = $this->usuario('super_admin');
        $this->actingAs($super);

        $this->expectException(DomainException::class);

        (new Suplantacion())->entrarComo($super);
    }

    /**
     * Ni como alguien que no entra al panel.
     *
     * Dejaria la sesion en una pantalla de 403 sin enseñar nada.
     */
    public function test_no_se_entra_como_quien_no_accede_al_panel(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $sinRol = User::factory()->create(['id_gerencia' => '4']);

        $this->assertFalse((new Suplantacion())->puedeEntrarComo($sinRol));
    }

    /** Volver sin haber entrado no hace nada raro. */
    public function test_volver_sin_suplantar_avisa(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $this->expectException(DomainException::class);

        (new Suplantacion())->volver();
    }

    /**
     * Queda auditado, a nombre de QUIEN SUPLANTA.
     *
     * Si se registrara a nombre del suplantado, el registro diria
     * justamente lo contrario de lo que paso.
     */
    public function test_queda_auditado_a_nombre_de_quien_suplanta(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');

        $this->actingAs($super);

        $antes = RegistroDeCambio::count();

        $suplantacion = new Suplantacion();
        $suplantacion->entrarComo($creador);
        $suplantacion->volver();

        /** Dos lineas: la entrada y la salida. */
        $this->assertSame($antes + 2, RegistroDeCambio::count());

        $registros = RegistroDeCambio::where('accion', AccionAuditada::Suplantacion->value)
            ->recientes()
            ->limit(2)
            ->get();

        foreach ($registros as $registro) {
            $this->assertSame(
                $super->id,
                $registro->usuario_id,
                'La auditoría debe apuntar a quien suplanta, no al suplantado.',
            );
        }
    }

    /**
     * Durante la suplantacion se actua con los permisos del OTRO.
     *
     * Es el objetivo -ver lo que el ve- y a la vez el motivo de que esto
     * se audite.
     */
    public function test_durante_la_suplantacion_mandan_los_permisos_del_otro(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');

        $this->actingAs($super);

        /** Antes de entrar, el super_admin si alcanza los respaldos. */
        $this->get('/admin/respaldos')->assertOk();

        (new Suplantacion())->entrarComo($creador);

        $this->get('/admin/respaldos')->assertForbidden();
    }

    /** La vuelta funciona desde la ruta, que es el camino real. */
    public function test_la_ruta_de_vuelta_funciona(): void
    {
        $super = $this->usuario('super_admin');
        $creador = $this->usuario('Creador');

        $this->actingAs($super);
        (new Suplantacion())->entrarComo($creador);

        $this->post(route('suplantacion.volver'))->assertRedirect('/admin');

        $this->assertTrue(Auth::user()->is($super));
    }
}
