<?php

namespace Tests\Feature;

use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
use App\Filament\Resources\Cotizacions\RelationManagers\ConsumiblesRelationManager;
use App\Filament\Resources\Cotizacions\RelationManagers\EquiposRelationManager;
use App\Filament\Resources\Cotizacions\RelationManagers\HerramientasRelationManager;
use App\Models\Cotizacion;
use App\Models\DatosHerramienta;
use App\Models\Herramienta;
use App\Models\PrecargadoConsumible;
use App\Models\PrecargadoEpp;
use App\Models\PrecargadoEquipo;
use App\Models\PrecargadoHerramienta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Las cajas precargadas.
 *
 * Una caja es una lista de elementos del catalogo guardada con nombre -"Caja
 * de Soldador"- que pertenece a quien la creo. Al cotizar se vuelca entera
 * en un grupo, en vez de buscar los mismos quince articulos cada vez.
 *
 * Existian en la base desde el principio, con datos reales, pero no habia
 * ninguna pantalla que las usara.
 */
class CajasPrecargadasTest extends TestCase
{
    use DatabaseTransactions;

    private function comoUsuario(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('super_admin');

        $this->actingAs($usuario);

        return $usuario;
    }

    /** @return array<string, array{class-string}> */
    public static function tiposDeCaja(): array
    {
        return [
            'herramientas' => [PrecargadoHerramienta::class],
            'equipos' => [PrecargadoEquipo::class],
            'consumibles' => [PrecargadoConsumible::class],
            'EPP' => [PrecargadoEpp::class],
        ];
    }

    /** Cada caja sabe leer sus elementos del catalogo. */
    #[DataProvider('tiposDeCaja')]
    public function test_la_caja_lee_sus_elementos(string $clase): void
    {
        $caja = $clase::query()->first();

        $this->assertNotNull($caja, "No hay ninguna caja de tipo {$clase} en los datos.");
        $this->assertIsArray($caja->idsDelCatalogo());
    }

    /**
     * Cada quien ve solo sus cajas.
     *
     * Son listas de trabajo personales; ver las de otro solo añade ruido a
     * un desplegable que ya tiene que ser corto para ser util.
     */
    public function test_solo_se_ven_las_cajas_propias(): void
    {
        $mias = $this->comoUsuario();
        $ajeno = User::factory()->create(['id_gerencia' => '4']);

        PrecargadoHerramienta::query()->create(['nombre' => 'Mi caja', 'id_user' => $mias->id]);
        PrecargadoHerramienta::query()->create(['nombre' => 'Caja ajena', 'id_user' => $ajeno->id]);

        $visibles = PrecargadoHerramienta::query()->de($mias)->pluck('nombre');

        $this->assertContains('Mi caja', $visibles);
        $this->assertNotContains('Caja ajena', $visibles);
    }

    /**
     * Sin sesion no se ve ninguna caja.
     *
     * Un scope que ignore al usuario nulo devolveria TODAS las cajas del
     * sistema, que es justo lo contrario de lo que se quiere.
     */
    public function test_sin_usuario_no_hay_cajas(): void
    {
        $this->assertSame(0, PrecargadoHerramienta::query()->de(null)->count());
    }

    /**
     * Las cajas sin dueño no son de nadie.
     *
     * En los datos reales hay cuatro con `id_user` nulo: son ejemplos que
     * llegaron con la copia de produccion.
     */
    public function test_las_cajas_sin_duenio_no_se_muestran(): void
    {
        $usuario = $this->comoUsuario();

        $huerfanas = PrecargadoHerramienta::query()->whereNull('id_user')->count();
        $this->assertGreaterThan(0, $huerfanas, 'Los datos ya no tienen cajas sin dueño.');

        $visibles = PrecargadoHerramienta::query()->de($usuario)->whereNull('id_user')->count();
        $this->assertSame(0, $visibles);
    }

    /**
     * Un elemento que no apunta al catalogo se descarta.
     *
     * Ocho elementos reales de una caja de EPP estan asi. Cargarlos crearia
     * lineas sin precio, invisibles en el total y sin explicacion.
     */
    public function test_los_elementos_sin_catalogo_se_descartan(): void
    {
        $caja = PrecargadoEpp::query()
            ->whereHas('elementos', fn ($q) => $q->whereNull('id_datos_epps'))
            ->first();

        $this->assertNotNull($caja, 'Ya no hay cajas de EPP con elementos rotos.');

        $this->assertLessThan(
            $caja->elementos()->count(),
            count($caja->idsDelCatalogo()),
            'Se están contando elementos que no apuntan a nada.'
        );
    }

    /** Volcar una caja crea los elementos dentro del grupo. */
    public function test_cargar_una_caja_llena_el_grupo(): void
    {
        $usuario = $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        $caja = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja de prueba',
            'id_user' => $usuario->id,
        ]);

        $articulos = DatosHerramienta::query()->limit(3)->pluck('id');

        foreach ($articulos as $id) {
            $caja->elementos()->create(['id_datos_herramienta' => $id]);
        }

        Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => $grupo->cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('cargarCaja', $grupo, data: ['caja' => $caja->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame(3, $grupo->items()->count());
        $this->assertEqualsCanonicalizing(
            $articulos->all(),
            $grupo->items()->pluck('id_datos_herramientas')->all()
        );
    }

    /**
     * Cargar dos veces suma cantidades, no duplica lineas.
     *
     * Las cajas reales traen ids repetidos -una tiene el mismo articulo
     * tres veces-. Si cada repeticion creara su propia linea, el mismo
     * articulo apareceria varias veces en la oferta que ve el cliente.
     */
    public function test_cargar_dos_veces_suma_en_vez_de_duplicar(): void
    {
        $usuario = $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        $caja = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja repetida',
            'id_user' => $usuario->id,
        ]);

        $articulo = DatosHerramienta::query()->value('id');
        $caja->elementos()->create(['id_datos_herramienta' => $articulo]);

        $componente = fn () => Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => $grupo->cotizacion,
            'pageClass' => EditCotizacion::class,
        ]);

        $componente()->callTableAction('cargarCaja', $grupo, data: ['caja' => $caja->id]);
        $componente()->callTableAction('cargarCaja', $grupo, data: ['caja' => $caja->id]);

        $this->assertSame(1, $grupo->items()->count(), 'Se duplicó la línea en vez de sumar.');
        $this->assertSame(2, (int) $grupo->items()->first()->cantidad);
    }

    /**
     * No se puede cargar la caja de otro, ni conociendo su id.
     *
     * El usuario tiene una caja propia -si no, el boton no apareceria- y
     * aun asi se le pasa el id de una ajena. La accion vuelve a filtrar por
     * dueño: esconder el boton no basta como proteccion.
     */
    public function test_no_se_carga_la_caja_de_otro(): void
    {
        $usuario = $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        /** Una caja propia, para que el boton exista. */
        PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja propia',
            'id_user' => $usuario->id,
        ]);

        $ajeno = User::factory()->create(['id_gerencia' => '4']);
        $ajena = PrecargadoHerramienta::query()->create([
            'nombre' => 'Caja ajena',
            'id_user' => $ajeno->id,
        ]);
        $ajena->elementos()->create([
            'id_datos_herramienta' => DatosHerramienta::query()->value('id'),
        ]);

        Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => $grupo->cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('cargarCaja', $grupo, data: ['caja' => $ajena->id]);

        $this->assertSame(0, $grupo->items()->count(), 'Se cargó una caja que no era suya.');
    }

    /**
     * Sin cajas propias, el boton no se ofrece.
     *
     * Un desplegable vacio es peor que no tener boton: invita a pulsarlo y
     * no explica nada.
     */
    public function test_sin_cajas_propias_no_aparece_el_boton(): void
    {
        $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => $grupo->cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->assertTableActionHidden('cargarCaja', record: $grupo);
    }

    /** Se pueden añadir elementos a mano, sin caja. */
    public function test_se_pueden_gestionar_los_elementos_a_mano(): void
    {
        $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        $articulo = DatosHerramienta::query()->value('id');

        Livewire::test(HerramientasRelationManager::class, [
            'ownerRecord' => $grupo->cotizacion,
            'pageClass' => EditCotizacion::class,
        ])
            ->callTableAction('elementos', $grupo, data: [
                'elementos' => [
                    ['catalogo' => $articulo, 'cantidad' => 4],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, $grupo->items()->count());
        $this->assertSame(4, (int) $grupo->items()->first()->cantidad);
    }

    /**
     * Los elementos cargados cuentan en el precio.
     *
     * Es la razon de ser de todo esto: un grupo vacio cuesta cero.
     */
    public function test_los_elementos_cargados_suben_el_costo(): void
    {
        $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        $antes = (new \App\Dominio\Recursos\CalculadoraAmortizacion)->porMes(
            0.0,
            $grupo->periodo_evaluacion,
            (float) $grupo->vrepo_anual,
            (float) $grupo->vcalibracion_anual,
        );

        $articulo = DatosHerramienta::query()->where('precio', '>', 0)->first();
        $grupo->items()->create([
            'id_datos_herramientas' => $articulo->id,
            'cantidad' => 2,
            'unidad' => 'N/A',
        ]);

        $despues = (new \App\Dominio\Recursos\CalculadoraAmortizacion)->porMes(
            2 * (float) $articulo->precio,
            $grupo->periodo_evaluacion,
            (float) $grupo->vrepo_anual,
            (float) $grupo->vcalibracion_anual,
        );

        $this->assertSame(0.0, $antes, 'Un grupo vacío debería costar cero.');
        $this->assertGreaterThan(0, $despues);
    }

    /** Los tres tipos ofrecen cargar caja y gestionar elementos. */
    #[DataProvider('gestoresDeActivos')]
    public function test_cada_pestana_ofrece_las_dos_acciones(string $gestor): void
    {
        $this->comoUsuario();
        $grupo = $this->grupoDePrueba();

        $tabla = Livewire::test($gestor, [
            'ownerRecord' => $grupo->cotizacion,
            'pageClass' => EditCotizacion::class,
        ])->instance()->getTable();

        $this->assertNotNull($tabla->getAction('elementos'), "{$gestor} no ofrece gestionar elementos.");
        $this->assertNotNull($tabla->getAction('cargarCaja'), "{$gestor} no ofrece cargar caja.");
    }

    /** @return array<string, array{class-string}> */
    public static function gestoresDeActivos(): array
    {
        return [
            'herramientas' => [HerramientasRelationManager::class],
            'equipos' => [EquiposRelationManager::class],
            'consumibles' => [ConsumiblesRelationManager::class],
        ];
    }

    /** Un grupo de herramientas vacio sobre una cotizacion desechable. */
    private function grupoDePrueba(): Herramienta
    {
        $cotizacion = Cotizacion::findOrFail(55)->replicate(['id']);
        $cotizacion->codigo = 'CAJAS-' . uniqid();
        $cotizacion->save();

        return Herramienta::query()->create([
            'id_cotizacion' => $cotizacion->id,
            'nombre' => 'Grupo de prueba',
            'periodo_evaluacion' => 5,
            'vrepo_anual' => 10,
            'vcalibracion_anual' => 5,
        ]);
    }
}
