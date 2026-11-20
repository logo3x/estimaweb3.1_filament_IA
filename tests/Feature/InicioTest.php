<?php

namespace Tests\Feature;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Aprobaciones\ReglasDeAprobacion;
use App\Filament\Widgets\Bienvenida;
use App\Models\Cotizacion;
use App\Models\Gerencia;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El inicio saluda y dice que hay pendiente para quien entra.
 */
class InicioTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function usuario(string $rol, string $nombre = 'María Fernanda Ruiz'): User
    {
        $gerencia = Gerencia::query()->firstOrFail();
        $usuario = User::factory()->create(['name' => $nombre, 'id_gerencia' => $gerencia->id]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    public function test_saluda_por_el_nombre_con_la_gerencia(): void
    {
        $usuario = $this->usuario('Creador');
        $this->actingAs($usuario);

        Livewire::test(Bienvenida::class)
            ->assertSee('María')
            ->assertDontSee('María Fernanda Ruiz')
            ->assertSee($usuario->gerenciaRelacion->nombre);

        $this->get('/admin')->assertOk()->assertSee('Esperan tu decisión');
    }

    /** La hora es la de Bogota: a las 19:30 de alli (00:30 UTC) son "buenas noches", no "buenos dias". */
    public function test_el_saludo_usa_la_hora_de_bogota(): void
    {
        $this->actingAs($this->usuario('Creador'));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-21 00:30:00', 'UTC'));
        Livewire::test(Bienvenida::class)->assertSee('Buenas noches')->assertSee('Viernes 20 de noviembre de 2026');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-20 13:00:00', 'UTC'));
        Livewire::test(Bienvenida::class)->assertSee('Buenos días');
    }

    /** La administracion ve las que esperan su decision, las mismas que el flujo le deja aprobar. */
    public function test_lista_lo_que_espera_la_decision_de_quien_entra(): void
    {
        $admin = $this->usuario('Administrador', 'Admin Prueba');
        $this->actingAs($admin);

        $reglas = new ReglasDeAprobacion;
        $esperadas = Cotizacion::query()->get()
            ->filter(fn (Cotizacion $c): bool => $reglas->puedeDecidirTecnica($c, $admin) || $reglas->puedeDecidirFinanciera($c, $admin));

        $this->assertNotEmpty($esperadas, 'Los datos deberian tener cotizaciones esperando a la administracion.');

        $datos = Livewire::test(Bienvenida::class)->viewData('porDecidir');
        $this->assertSame($esperadas->count(), $datos['total']);
        $this->assertLessThanOrEqual(5, count($datos['lista']));
    }

    /** Un creador ve sus borradores; las aprobadas no le aparecen como pendientes. */
    public function test_un_creador_ve_sus_borradores(): void
    {
        $creador = $this->usuario('Creador', 'Creador Prueba');
        $this->actingAs($creador);

        $mia = Cotizacion::query()->findOrFail(33)->replicate(['aprobacion1', 'aprobacion2']);
        $mia->id_user = $creador->id;
        $mia->codigo = 'BORRADOR-PRUEBA';
        $mia->gerencia = $creador->gerenciaRelacion->nombre;
        $mia->save();
        $this->assertSame(EstadoAprobacion::Borrador, EstadoAprobacion::desdeColumnas($mia->aprobacion1, $mia->aprobacion2));

        $datos = Livewire::test(Bienvenida::class)->viewData('mias');

        $this->assertSame(1, $datos['total']);
        $this->assertSame('BORRADOR-PRUEBA', $datos['lista'][0]['codigo']);
        $this->assertSame(0, Livewire::test(Bienvenida::class)->viewData('porDecidir')['total']);
    }
}
