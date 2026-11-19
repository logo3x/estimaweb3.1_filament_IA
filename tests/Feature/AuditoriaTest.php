<?php

namespace Tests\Feature;

use App\Dominio\Aprobaciones\FlujoDeAprobacion;
use App\Dominio\Auditoria\AccionAuditada;
use App\Dominio\Auditoria\Auditoria;
use App\Models\Cotizacion;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Quien cambio que, y que quede constancia.
 *
 * Lo que hace util a una auditoria no es que anote "hubo un cambio", sino
 * que guarde el valor ANTERIOR: sin el no se puede reconstruir por que una
 * oferta salio con un numero y no con otro.
 */
class AuditoriaTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol = 'Administrador'): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Un cambio queda con su valor viejo y el nuevo. */
    public function test_un_cambio_guarda_el_antes_y_el_despues(): void
    {
        $this->actingAs($this->usuario());

        $cotizacion = Cotizacion::findOrFail(55);
        $anterior = $cotizacion->nombre;

        $cotizacion->nombre = 'Nombre nuevo de prueba';
        $registro = (new Auditoria())->modificado($cotizacion);

        $this->assertNotNull($registro);
        $this->assertSame('Cotizacion', $registro->entidad);
        $this->assertSame($anterior, $registro->cambios['nombre']['antes']);
        $this->assertSame('Nombre nuevo de prueba', $registro->cambios['nombre']['despues']);
    }

    /** Guardar sin cambiar nada no ensucia la bitacora. */
    public function test_guardar_sin_cambios_no_escribe_nada(): void
    {
        $this->actingAs($this->usuario());

        $this->assertNull((new Auditoria())->modificado(Cotizacion::findOrFail(55)));
    }

    /**
     * Un valor igual escrito distinto tampoco cuenta.
     *
     * La base devuelve "12.00" donde el formulario manda 12: sin
     * normalizar, cada guardado pareceria un cambio.
     */
    public function test_el_mismo_valor_con_otro_formato_no_es_un_cambio(): void
    {
        $this->actingAs($this->usuario());

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->valor_total = (string) $cotizacion->valor_total;

        $this->assertNull((new Auditoria())->modificado($cotizacion));
    }

    /**
     * Las contraseñas NO entran en la auditoria.
     *
     * Es lo mas importante de esta clase: una bitacora con hashes seria un
     * sitio mas del que robarlos.
     */
    public function test_la_contrasena_nunca_se_guarda(): void
    {
        $this->actingAs($this->usuario());

        $usuario = $this->usuario();
        $usuario->password = bcrypt('un-secreto');
        $usuario->name = 'Otro nombre';

        $registro = (new Auditoria())->modificado($usuario);

        $this->assertNotNull($registro);
        $this->assertArrayNotHasKey('password', $registro->cambios);
        $this->assertArrayNotHasKey('remember_token', $registro->cambios);
        $this->assertArrayHasKey('name', $registro->cambios);
    }

    /** Se guarda quien lo hizo y desde donde. */
    public function test_se_guarda_quien_y_desde_donde(): void
    {
        $usuario = $this->usuario();
        $this->actingAs($usuario);

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->nombre = 'Otro nombre más';

        $registro = (new Auditoria())->modificado($cotizacion);

        $this->assertSame($usuario->id, $registro->usuario_id);
        $this->assertNotNull($registro->ip);
    }

    /** Un cambio sobre una cotizacion queda asociado a ella. */
    public function test_el_cambio_apunta_a_su_cotizacion(): void
    {
        $this->actingAs($this->usuario());

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->nombre = 'Para comprobar la relación';

        $this->assertSame(55, (new Auditoria())->modificado($cotizacion)->cotizacion_id);
    }

    /**
     * Las decisiones del flujo quedan registradas.
     *
     * Las columnas `aprobacion*` solo guardan el ULTIMO estado: si algo se
     * devuelve y se vuelve a aprobar, lo anterior se pierde. La auditoria
     * conserva la secuencia.
     */
    public function test_aprobar_queda_auditado(): void
    {
        $admin = $this->usuario('super_admin');
        $this->actingAs($admin);

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->forceFill([
            'aprobacion1' => 'PENDIENTE',
            'aprobacion2' => null,
            'id_user' => $admin->id,
        ])->save();

        $antes = RegistroDeCambio::count();

        FlujoDeAprobacion::crear()->aprobarTecnica($cotizacion->refresh(), $admin, 'Todo correcto');

        $this->assertGreaterThan($antes, RegistroDeCambio::count());

        $ultimo = RegistroDeCambio::recientes()->first();

        $this->assertSame(AccionAuditada::Aprobado->value, $ultimo->accion);
        $this->assertStringContainsString('técnicamente', $ultimo->resumen);
        $this->assertSame(55, $ultimo->cotizacion_id);
    }

    /** Un rechazo se distingue de una aprobacion. */
    public function test_rechazar_queda_auditado_como_tal(): void
    {
        $admin = $this->usuario('super_admin');
        $this->actingAs($admin);

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->forceFill([
            'aprobacion1' => 'PENDIENTE',
            'aprobacion2' => null,
            'id_user' => $admin->id,
        ])->save();

        FlujoDeAprobacion::crear()->rechazarTecnica($cotizacion->refresh(), $admin, 'Falta el AIU');

        $ultimo = RegistroDeCambio::recientes()->first();

        $this->assertSame(AccionAuditada::Rechazado->value, $ultimo->accion);
    }

    /** Lo que toca el dinero o los permisos se marca aparte. */
    public function test_las_acciones_delicadas_se_distinguen(): void
    {
        $this->assertTrue(AccionAuditada::MargenCambiado->esDelicada());
        $this->assertTrue(AccionAuditada::Suplantacion->esDelicada());
        $this->assertTrue(AccionAuditada::Borrado->esDelicada());
        $this->assertFalse(AccionAuditada::Modificado->esDelicada());
    }

    /** Solo la administracion consulta la auditoria. */
    public function test_la_auditoria_no_la_ve_cualquiera(): void
    {
        $this->actingAs($this->usuario('Creador'))->get('/admin/auditoria')->assertForbidden();
        $this->actingAs($this->usuario('Administrador'))->get('/admin/auditoria')->assertOk();
    }

    /**
     * La auditoria no se edita ni se borra desde el panel.
     *
     * En el sistema anterior `Route::resource('auditoria')` exponia
     * store/update/destroy tras `auth` sin rol: cualquiera con sesion podia
     * alterarla, lo que anula su valor como evidencia.
     */
    public function test_la_auditoria_es_solo_de_consulta(): void
    {
        $recurso = \App\Filament\Resources\Auditorias\AuditoriaResource::class;

        $registro = (new Auditoria())->hecho(
            accion: AccionAuditada::Modificado,
            modelo: Cotizacion::findOrFail(55),
            resumen: 'Una línea cualquiera',
        );

        $this->assertFalse($recurso::canCreate());
        $this->assertFalse($recurso::canEdit($registro));
        $this->assertFalse($recurso::canDelete($registro));
        $this->assertFalse($recurso::canDeleteAny());
    }
}
