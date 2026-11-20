<?php

namespace Tests\Feature;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\CalcularCotizacion;
use App\Dominio\DuplicarCotizacion;
use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use App\Filament\Resources\Cotizacions\Pages\ViewCotizacion;
use App\Models\Cotizacion;
use App\Models\Gerencia;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Duplicar una cotizacion copia todo lo que la compone y no toca la original.
 *
 * Nace de la cotizacion 33: estaba aprobada y enviada, y alguien la
 * sobrescribio para preparar otra oferta porque no habia forma de copiarla.
 */
class DuplicarCotizacionTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol = 'Administrador', string $gerencia = '4'): User
    {
        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** @return array<string, array{int}> */
    public static function cotizaciones(): array
    {
        $ids = array_keys(json_decode((string) file_get_contents(__DIR__.'/../golden/lineas.json'), true)['cotizaciones']);

        return array_combine(array_map(fn ($id): string => "cotizacion {$id}", $ids), array_map(fn ($id): array => [(int) $id], $ids));
    }

    /**
     * LA PRUEBA CENTRAL: la copia vale lo mismo, linea por linea.
     *
     * Si una revision quedara apuntando a un recurso equivocado, o se
     * perdiera un componente o un auxilio, alguna linea cambiaria.
     */
    #[DataProvider('cotizaciones')]
    public function test_la_copia_calcula_lo_mismo_que_la_original(int $id): void
    {
        $original = Cotizacion::query()->findOrFail($id);
        $calculo = CalcularCotizacion::crear();
        $antes = $calculo->para($original);

        $copia = DuplicarCotizacion::crear()->duplicar($original, $this->usuario(), "COPIA-{$id}", 'Copia');
        $despues = $calculo->para($copia->fresh());

        $this->assertEqualsWithDelta($antes->totales->total, $despues->totales->total, 0.01, "Cotizacion {$id}: el total cambio.");
        $this->assertSame(count($antes->detalle), count($despues->detalle), "Cotizacion {$id}: cambio el numero de lineas.");

        $firma = fn ($linea): string => $linea->categoria.'|'.$linea->descripcion.'|'.$linea->cantidad.'|'.$linea->unidad.'|'.round($linea->aporte, 2);
        $lineasAntes = array_map($firma, $antes->detalle);
        $lineasDespues = array_map($firma, $despues->detalle);
        sort($lineasAntes);
        sort($lineasDespues);

        $this->assertSame($lineasAntes, $lineasDespues, "Cotizacion {$id}: alguna linea cambio.");
        $this->assertSame(count($antes->avisos), count($despues->avisos));

        /** Ninguna revision de la copia apunta a recursos de la original. */
        foreach ($copia->revisiones()->get() as $revision) {
            $recurso = $revision->recurso();
            if ($recurso !== null && isset($recurso->id_cotizacion)) {
                $this->assertSame((int) $copia->id, (int) $recurso->id_cotizacion, "Revision {$revision->id} apunta a un recurso de otra cotizacion.");
            }
        }
    }

    /** La copia nace en borrador, es de quien la hizo y guarda sus totales. */
    public function test_la_copia_es_un_borrador_de_quien_la_hizo(): void
    {
        $aprobada = Cotizacion::query()->findOrFail(33);
        $this->assertSame(EstadoAprobacion::Aprobada, EstadoAprobacion::desdeColumnas($aprobada->aprobacion1, $aprobada->aprobacion2));

        $autor = $this->usuario('Creador');
        $copia = DuplicarCotizacion::crear()->duplicar($aprobada, $autor, 'COPIA-33', 'Otra oferta CBM');

        $this->assertSame(EstadoAprobacion::Borrador, EstadoAprobacion::desdeColumnas($copia->aprobacion1, $copia->aprobacion2));
        $this->assertNull($copia->aprobacion1_user_id);
        $this->assertNull($copia->aprobacion2_fecha);
        $this->assertSame($autor->id, (int) $copia->id_user);
        $this->assertSame('COPIA-33', $copia->codigo);
        $this->assertSame('Otra oferta CBM', $copia->nombre);
        $this->assertSame($aprobada->cliente, $copia->cliente);
        $this->assertEqualsWithDelta(
            CalcularCotizacion::crear()->para($copia)->totales->total,
            (float) $copia->fresh()->aiu_valortotal_con_iva,
            0.01,
            'El listado mostraria la copia en cero.'
        );
    }

    /** La original no cambia: ni sus filas, ni su aprobacion. */
    public function test_la_original_no_cambia(): void
    {
        $original = Cotizacion::query()->findOrFail(7);
        $foto = fn (): array => [
            $original->fresh()->only(['codigo', 'nombre', 'aprobacion1', 'aprobacion2', 'id_user']),
            $original->items()->count(),
            $original->revisiones()->count(),
            $original->personals()->count(),
            $original->personals()->withCount(['epps', 'examenesVacunas', 'auxiliosAdicionales', 'auxiliosConvencionales'])->get()
                ->map(fn ($p): array => [$p->epps_count, $p->examenes_vacunas_count, $p->auxilios_adicionales_count, $p->auxilios_convencionales_count])->all(),
        ];

        $antes = $foto();
        DuplicarCotizacion::crear()->duplicar($original, $this->usuario(), 'COPIA-7', 'Copia');

        $this->assertSame($antes, $foto());
    }

    /**
     * La 7 tiene 15 personas con 40 EPP, 26 examenes y un auxilio
     * convencional; la 51, auxilios adicionales. Juntas cubren los cuatro.
     *
     * @return array<string, array{int}>
     */
    public static function conPersonalCompleto(): array
    {
        return ['cotizacion 7' => [7], 'cotizacion 51' => [51]];
    }

    /** Lo que cuelga del personal se copia completo. */
    #[DataProvider('conPersonalCompleto')]
    public function test_copia_auxilios_epp_y_examenes_del_personal(int $id): void
    {
        $original = Cotizacion::query()->findOrFail($id);
        $copia = DuplicarCotizacion::crear()->duplicar($original, $this->usuario(), "COPIA-{$id}", 'Copia');

        $contar = fn (Cotizacion $c): array => $c->personals()->withCount(['epps', 'examenesVacunas', 'auxiliosAdicionales', 'auxiliosConvencionales'])->get()
            ->map(fn ($p): array => [$p->nombre, $p->epps_count, $p->examenes_vacunas_count, $p->auxilios_adicionales_count, $p->auxilios_convencionales_count])
            ->sortBy(fn (array $f): string => json_encode($f))->values()->all();

        $this->assertNotEmpty($contar($original));
        $this->assertSame($contar($original), $contar($copia));
    }

    /** Desde la pantalla de consulta, tambien de una aprobada; el codigo no se puede repetir. */
    public function test_se_duplica_desde_la_pantalla_y_no_repite_codigos(): void
    {
        $this->actingAs($admin = $this->usuario());
        $existente = Cotizacion::query()->findOrFail(55)->codigo;

        Livewire::test(ViewCotizacion::class, ['record' => 33])
            ->callAction('duplicar', ['codigo' => $existente, 'nombre' => 'Copia'])
            ->assertHasActionErrors(['codigo' => 'unique']);

        $cuantas = Cotizacion::query()->count();

        Livewire::test(ViewCotizacion::class, ['record' => 33])
            ->callAction('duplicar', ['codigo' => 'EOEP-BOG-PRUEBA-COPIA', 'nombre' => 'Copia de prueba'])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $this->assertSame($cuantas + 1, Cotizacion::query()->count());
        $this->assertSame($admin->id, (int) Cotizacion::query()->where('codigo', 'EOEP-BOG-PRUEBA-COPIA')->value('id_user'));
    }

    /** Quien no ve la original no puede copiarla. */
    public function test_no_se_duplica_lo_que_no_se_ve(): void
    {
        $original = Cotizacion::query()->findOrFail(55);
        $alcance = new AlcanceDeCotizaciones;

        $deOtraGerencia = null;
        foreach (Gerencia::query()->pluck('id') as $gerencia) {
            $candidato = $this->usuario('Creador', (string) $gerencia);
            if (! $alcance->puedeVer($original, $candidato)) {
                $deOtraGerencia = $candidato;
                break;
            }
        }

        $this->assertNotNull($deOtraGerencia, 'Ninguna gerencia queda fuera del alcance de la 55.');

        $this->actingAs($deOtraGerencia);
        $this->get(CotizacionResource::getUrl('view', ['record' => 55]))->assertNotFound();
    }

    /** El codigo sugerido sigue el formato de la casa y no esta en uso. */
    public function test_el_codigo_sugerido_esta_libre(): void
    {
        $codigo = DuplicarCotizacion::codigoSugerido();

        $this->assertMatchesRegularExpression('/^EOEP-BOG-\d{8}-\d{3,}$/', $codigo);
        $this->assertFalse(Cotizacion::query()->where('codigo', $codigo)->exists());
    }
}
