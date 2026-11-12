<?php

namespace Tests\Feature;

use App\Filament\Resources\DatosComunicacions\Pages\GestionarDatosComunicacion;
use App\Filament\Resources\DatosConsumibles\Pages\GestionarDatosConsumible;
use App\Filament\Resources\DatosEquipos\Pages\GestionarDatosEquipo;
use App\Filament\Resources\DatosHerramientas\Pages\GestionarDatosHerramienta;
use App\Filament\Resources\Epps\Pages\GestionarEpp;
use App\Filament\Resources\ExamenVacunas\Pages\GestionarExamenVacuna;
use App\Filament\Resources\Gerencias\Pages\GestionarGerencia;
use App\Filament\Resources\Turnos\Pages\GestionarTurno;
use App\Models\DatosHerramienta;
use App\Models\Turno;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las pantallas de catalogos.
 *
 * Herramientas, equipos, consumibles y EPP comparten una sola clase base:
 * en el sistema actual son cuatro archivos casi identicos, de modo que
 * cualquier mejora hay que hacerla cuatro veces. Estas pruebas recorren los
 * ocho catalogos con el mismo caso.
 */
class PanelCatalogosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::ROLES_CON_ACCESO_AL_PANEL))
                ->firstOrFail()
        );
    }

    /** @return array<string, array{class-string, int}> */
    public static function catalogos(): array
    {
        return [
            'herramientas' => [GestionarDatosHerramienta::class, 782],
            'equipos' => [GestionarDatosEquipo::class, 780],
            'consumibles' => [GestionarDatosConsumible::class, 3409],
            'EPP' => [GestionarEpp::class, 892],
            'comunicaciones' => [GestionarDatosComunicacion::class, 11],
            'exámenes' => [GestionarExamenVacuna::class, 30],
            'turnos' => [GestionarTurno::class, 16],
            'gerencias' => [GestionarGerencia::class, 5],
        ];
    }

    /** Cada catalogo carga con sus datos. */
    #[DataProvider('catalogos')]
    public function test_el_catalogo_carga(string $pagina, int $registrosEsperados): void
    {
        Livewire::test($pagina)->assertOk();

        $modelo = $pagina::getResource()::getModel();

        $this->assertSame(
            $registrosEsperados,
            $modelo::query()->count(),
            "Cambio el numero de registros del catalogo {$modelo}."
        );
    }

    /** La busqueda funciona en todos. */
    #[DataProvider('catalogos')]
    public function test_el_catalogo_se_puede_buscar(string $pagina): void
    {
        Livewire::test($pagina)
            ->searchTable('a')
            ->assertOk();
    }

    /**
     * Un catalogo de miles de filas no las carga todas.
     *
     * Consumibles tiene 3.409 registros: traerlos todos en cada visita
     * cargaria la tabla entera en memoria.
     */
    public function test_los_catalogos_grandes_estan_paginados(): void
    {
        $componente = Livewire::test(GestionarDatosConsumible::class)->assertOk();

        /**
         * Se mide cuantas filas llegan a la pagina, no el total: la tabla
         * tiene 3.409 registros y traerlos todos cargaria la tabla entera en
         * memoria en cada visita.
         */
        $enPantalla = $componente->instance()->getTableRecords();

        $this->assertLessThanOrEqual(
            25,
            $enPantalla->count(),
            'El catalogo de consumibles esta trayendo mas filas de las que muestra.'
        );

        $this->assertGreaterThan(0, $enPantalla->count());
    }

    /** Se puede crear un registro desde el listado. */
    public function test_se_puede_crear_en_el_catalogo(): void
    {
        $antes = DatosHerramienta::query()->count();

        Livewire::test(GestionarDatosHerramienta::class)
            ->callAction('create', data: [
                'codigo' => 'PRUEBA-001',
                'nombre' => 'Herramienta de prueba',
                'grupo' => 'PRUEBAS',
                'precio' => 150000,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($antes + 1, DatosHerramienta::query()->count());

        DatosHerramienta::query()->where('codigo', 'PRUEBA-001')->delete();
    }

    /** El formulario exige los campos obligatorios. */
    public function test_el_formulario_valida_los_campos_obligatorios(): void
    {
        Livewire::test(GestionarDatosHerramienta::class)
            ->callAction('create', data: [
                'codigo' => '',
                'nombre' => '',
                'grupo' => '',
                'precio' => null,
            ])
            ->assertHasActionErrors(['codigo', 'nombre', 'precio']);
    }

    /**
     * Un turno no puede tener cero dias trabajados al mes.
     *
     * Ese valor divide el costo mensual para obtener el del dia habil.
     */
    public function test_un_turno_exige_dias_trabajados_mayores_que_cero(): void
    {
        Livewire::test(GestionarTurno::class)
            ->callAction('create', data: [
                'nombre' => 'Turno invalido',
                'd_trabajados' => 15,
                'd_descansados' => 15,
                'd_trabajadosmes' => 0,
                'hedo' => 0, 'heno' => 0, 'hedf' => 0, 'henf' => 0,
                'donfesoca' => 0, 'donhabitual' => 0, 'rn' => 0,
            ])
            ->assertHasActionErrors(['d_trabajadosmes']);

        $this->assertNull(Turno::query()->where('nombre', 'Turno invalido')->first());
    }

    /** Los catalogos aparecen agrupados en la navegacion. */
    public function test_los_catalogos_estan_agrupados_en_la_navegacion(): void
    {
        foreach (self::catalogos() as [$pagina]) {
            $recurso = $pagina::getResource();

            $this->assertSame(
                'Catálogos',
                $recurso::getNavigationGroup(),
                "{$recurso} deberia estar en el grupo Catálogos."
            );
        }
    }
}
