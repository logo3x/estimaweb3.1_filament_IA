<?php

namespace Tests\Feature;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Aprobaciones\FlujoDeAprobacion;
use App\Dominio\Aprobaciones\ReglasDeAprobacion;
use App\Models\Cotizacion;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El flujo de aprobacion de dos niveles.
 *
 * Tecnica (el gerente de la gerencia) y financiera (la administracion).
 * El sistema actual lo reparte entre mas de cuarenta comparaciones de
 * cadenas y no tiene ninguna prueba sobre el.
 *
 * Los cambios se deshacen al terminar cada prueba: estas cotizaciones son
 * datos reales de produccion copiados en local.
 */
class FlujoDeAprobacionTest extends TestCase
{
    use DatabaseTransactions;

    private const GERENCIA = '4';

    private FlujoDeAprobacion $flujo;

    private ReglasDeAprobacion $reglas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->flujo = FlujoDeAprobacion::crear();
        $this->reglas = new ReglasDeAprobacion();
    }

    /** Un usuario con el rol y la gerencia que se pidan. */
    private function usuario(?string $rol = null, ?string $gerencia = self::GERENCIA): User
    {
        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);

        if ($rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    }

    /** Una cotizacion en el estado que se pida. */
    private function cotizacion(array $atributos = []): Cotizacion
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $cotizacion->forceFill(array_merge([
            'gerencia' => self::GERENCIA,
            'aprobacion1' => null,
            'aprobacion2' => null,
            'aprobacion1_user_id' => null,
            'aprobacion2_user_id' => null,
        ], $atributos))->save();

        return $cotizacion->refresh();
    }

    /** El recorrido completo, de borrador a aprobada. */
    public function test_el_ciclo_completo_llega_a_aprobada(): void
    {
        $autor = $this->usuario('Creador');
        $cotizacion = $this->cotizacion(['id_user' => $autor->id]);

        $this->assertSame(EstadoAprobacion::Borrador, $this->reglas->estado($cotizacion));

        $cotizacion = $this->flujo->enviarAAprobacion($cotizacion, $autor);
        $this->assertSame(EstadoAprobacion::EsperandoTecnica, $this->reglas->estado($cotizacion));

        $gerente = $this->usuario('Gerente');
        $cotizacion = $this->flujo->aprobarTecnica($cotizacion, $gerente);
        $this->assertSame(EstadoAprobacion::EsperandoFinanciera, $this->reglas->estado($cotizacion));

        $admin = $this->usuario('Administrador');
        $cotizacion = $this->flujo->aprobarFinanciera($cotizacion, $admin);
        $this->assertSame(EstadoAprobacion::Aprobada, $this->reglas->estado($cotizacion));

        $this->assertSame($gerente->id, $cotizacion->aprobacion1_user_id);
        $this->assertSame($admin->id, $cotizacion->aprobacion2_user_id);
        $this->assertNotNull($cotizacion->aprobacion1_fecha);
        $this->assertNotNull($cotizacion->aprobacion2_fecha);
    }

    /**
     * EL FALLO DE 2026-11-06: el autor que ademas es Administrador.
     *
     * Antes no podia editar su propio borrador, porque se comprobaba el rol
     * antes que la autoria.
     */
    public function test_el_autor_edita_su_borrador_aunque_tenga_rol_de_aprobador(): void
    {
        /** De otra gerencia, para que solo la autoria lo habilite. */
        $autor = $this->usuario('Administrador', gerencia: '8');
        $cotizacion = $this->cotizacion(['id_user' => $autor->id]);

        $this->assertTrue($this->reglas->puedeEditar($cotizacion, $autor));
    }

    /** Lo aprobado no se toca, ni siquiera por la administracion. */
    public function test_una_cotizacion_aprobada_no_admite_cambios(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'SI', 'aprobacion2' => 'SI']);

        $this->assertFalse($this->reglas->puedeEditar($cotizacion, $this->usuario('Administrador')));
        $this->assertFalse($this->reglas->puedeEditar($cotizacion, $this->usuario('Gerente')));
        $this->assertStringContainsString('no admite cambios', $this->reglas->motivoDelBloqueo($cotizacion, $this->usuario('Gerente')));
    }

    /** Mientras espera al gerente, el autor ya no la toca. */
    public function test_en_aprobacion_tecnica_solo_el_gerente_edita(): void
    {
        $autor = $this->usuario('Creador');
        $cotizacion = $this->cotizacion(['id_user' => $autor->id, 'aprobacion1' => 'PENDIENTE']);

        $this->assertFalse($this->reglas->puedeEditar($cotizacion, $autor));
        $this->assertTrue($this->reglas->puedeEditar($cotizacion, $this->usuario('Gerente')));
    }

    /** El gerente de OTRA gerencia no decide. */
    public function test_un_gerente_de_otra_gerencia_no_puede_aprobar(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'PENDIENTE']);
        $ajeno = $this->usuario('Gerente', gerencia: '7');

        $this->assertFalse($this->reglas->puedeDecidirTecnica($cotizacion, $ajeno));

        $this->expectException(DomainException::class);
        $this->flujo->aprobarTecnica($cotizacion, $ajeno);
    }

    /** La aprobacion financiera no esta disponible antes de la tecnica. */
    public function test_no_se_puede_saltar_la_aprobacion_tecnica(): void
    {
        $cotizacion = $this->cotizacion();
        $admin = $this->usuario('Administrador');

        $this->assertFalse($this->reglas->puedeDecidirFinanciera($cotizacion, $admin));

        $this->expectException(DomainException::class);
        $this->flujo->aprobarFinanciera($cotizacion, $admin);
    }

    /** Un rechazo tecnico devuelve la cotizacion al autor. */
    public function test_el_rechazo_tecnico_la_devuelve_al_autor(): void
    {
        $autor = $this->usuario('Creador');
        $cotizacion = $this->cotizacion(['id_user' => $autor->id, 'aprobacion1' => 'PENDIENTE']);

        $cotizacion = $this->flujo->rechazarTecnica($cotizacion, $this->usuario('Gerente'), 'Faltan los consumibles.');

        $this->assertSame(EstadoAprobacion::RechazadaTecnica, $this->reglas->estado($cotizacion));
        $this->assertTrue($this->reglas->puedeEditar($cotizacion, $autor));
        $this->assertSame('Faltan los consumibles.', $cotizacion->aprobacion1_comentarios);
    }

    /**
     * Un rechazo tecnico limpia el segundo nivel.
     *
     * En produccion hay una cotizacion rechazada tecnicamente con la
     * aprobacion2 en 'PENDIENTE': un estado que no significa nada.
     */
    public function test_el_rechazo_tecnico_limpia_el_segundo_nivel(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'PENDIENTE', 'aprobacion2' => 'PENDIENTE']);

        $cotizacion = $this->flujo->rechazarTecnica($cotizacion, $this->usuario('Gerente'), 'Rehacer.');

        $this->assertNull($cotizacion->aprobacion2);
    }

    /** Un rechazo financiero la devuelve al gerente, no al autor. */
    public function test_el_rechazo_financiero_la_devuelve_al_gerente(): void
    {
        $autor = $this->usuario('Creador');
        $cotizacion = $this->cotizacion([
            'id_user' => $autor->id,
            'aprobacion1' => 'SI',
            'aprobacion2' => 'PENDIENTE',
        ]);

        $cotizacion = $this->flujo->rechazarFinanciera($cotizacion, $this->usuario('Administrador'), 'Margen insuficiente.');

        $this->assertSame(EstadoAprobacion::RechazadaFinanciera, $this->reglas->estado($cotizacion));
        $this->assertTrue($this->reglas->puedeEditar($cotizacion, $this->usuario('Gerente')));
        $this->assertFalse($this->reglas->puedeEditar($cotizacion, $autor));
    }

    /** Un rechazo sin motivo deja al siguiente sin saber que corregir. */
    public function test_un_rechazo_exige_motivo(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'PENDIENTE']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Debe indicar el motivo');

        $this->flujo->rechazarTecnica($cotizacion, $this->usuario('Gerente'), '   ');
    }

    /** Reabrir devuelve la cotizacion a borrador y borra el rastro. */
    public function test_reabrir_vuelve_a_borrador(): void
    {
        $cotizacion = $this->cotizacion([
            'aprobacion1' => 'SI',
            'aprobacion2' => 'SI',
            'aprobacion1_user_id' => $this->usuario('Gerente')->id,
        ]);

        $cotizacion = $this->flujo->reabrir($cotizacion, $this->usuario('Administrador'), 'El cliente cambió el alcance.');

        $this->assertSame(EstadoAprobacion::Borrador, $this->reglas->estado($cotizacion));
        $this->assertNull($cotizacion->aprobacion1_user_id);
        $this->assertNull($cotizacion->aprobacion2);
    }

    /** Un gerente no puede reabrir lo que ya se aprobo. */
    public function test_un_gerente_no_puede_reabrir(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'SI', 'aprobacion2' => 'SI']);

        $this->expectException(DomainException::class);
        $this->flujo->reabrir($cotizacion, $this->usuario('Gerente'), 'Quiero cambiarla.');
    }

    /**
     * El estado tolera los valores tal como estan guardados.
     *
     * Cuatro fallos reales de este sistema salen del mismo descuido: un
     * dato guardado en un formato y leido en otro.
     *
     */
    #[DataProvider('variantesDeSi')]
    public function test_el_estado_tolera_variantes_del_valor_guardado(string $si): void
    {
        $this->assertSame(
            EstadoAprobacion::Aprobada,
            EstadoAprobacion::desdeColumnas($si, $si)
        );
    }

    /** @return array<string, array{string}> */
    public static function variantesDeSi(): array
    {
        return [
            'mayusculas' => ['SI'],
            'capitalizada' => ['Si'],
            'minusculas' => ['si'],
            'con espacios' => ['  SI  '],
        ];
    }

    /** Una cadena vacia es lo mismo que nulo: nadie decidio nada. */
    public function test_una_cadena_vacia_es_borrador(): void
    {
        $this->assertSame(EstadoAprobacion::Borrador, EstadoAprobacion::desdeColumnas('', ''));
        $this->assertSame(EstadoAprobacion::Borrador, EstadoAprobacion::desdeColumnas(null, null));
    }

    /** super_admin pasa por encima de todo, incluso de lo aprobado. */
    public function test_super_admin_edita_siempre(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'SI', 'aprobacion2' => 'SI']);

        $this->assertTrue($this->reglas->puedeEditar($cotizacion, $this->usuario('super_admin')));
    }

    /** Sin sesion no se hace nada. */
    public function test_sin_usuario_no_se_puede_nada(): void
    {
        $cotizacion = $this->cotizacion();

        $this->assertFalse($this->reglas->puedeEditar($cotizacion, null));
        $this->assertFalse($this->reglas->puedeEnviarAAprobacion($cotizacion, null));
        $this->assertFalse($this->reglas->puedeDecidirTecnica($cotizacion, null));
        $this->assertFalse($this->reglas->puedeDecidirFinanciera($cotizacion, null));
        $this->assertFalse($this->reglas->puedeReabrir($cotizacion, null));
    }

    /**
     * El filtro SQL coincide con el estado calculado en PHP.
     *
     * El listado filtra en la base y el detalle calcula en PHP. Si las dos
     * reglas se separan, el listado dice una cosa y la ficha otra, que es
     * justo el tipo de desajuste que ya ha costado cuatro fallos aqui.
     */
    public function test_el_filtro_sql_coincide_con_el_estado_calculado(): void
    {
        $todas = Cotizacion::query()->get(['id', 'aprobacion1', 'aprobacion2']);

        foreach (EstadoAprobacion::cases() as $estado) {
            $segunSql = $estado->aplicarFiltro(Cotizacion::query())
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $segunPhp = $todas
                ->filter(fn (Cotizacion $c): bool => EstadoAprobacion::desdeColumnas($c->aprobacion1, $c->aprobacion2) === $estado)
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            $this->assertSame(
                $segunPhp,
                $segunSql,
                "El filtro de '{$estado->value}' no devuelve lo mismo que el calculo en PHP."
            );
        }
    }

    /** Entre todos los filtros se reparten las 48, sin solapes ni huecos. */
    public function test_los_filtros_cubren_todas_las_cotizaciones_una_sola_vez(): void
    {
        $total = Cotizacion::query()->count();
        $vistas = [];

        foreach (EstadoAprobacion::cases() as $estado) {
            $vistas = array_merge($vistas, $estado->aplicarFiltro(Cotizacion::query())->pluck('id')->all());
        }

        $this->assertCount($total, $vistas, 'Alguna cotización cae en dos filtros o en ninguno.');
        $this->assertCount($total, array_unique($vistas), 'Hay cotizaciones que aparecen en dos estados.');
    }

    /**
     * Los 48 estados reales de produccion se interpretan sin error.
     *
     * Ninguna combinacion guardada debe caer en un hueco del enum.
     */
    public function test_todas_las_cotizaciones_reales_tienen_un_estado_valido(): void
    {
        $cotizaciones = Cotizacion::query()->get(['id', 'aprobacion1', 'aprobacion2']);

        $this->assertGreaterThan(40, $cotizaciones->count());

        foreach ($cotizaciones as $cotizacion) {
            $estado = EstadoAprobacion::desdeColumnas($cotizacion->aprobacion1, $cotizacion->aprobacion2);

            $this->assertInstanceOf(EstadoAprobacion::class, $estado, "La cotización {$cotizacion->id} no tiene estado.");
            $this->assertNotSame('', $estado->etiqueta());
        }
    }
}
