<?php

namespace Tests\Feature;

use App\Dominio\CalcularCotizacion;
use App\Dominio\ExportarCotizaciones;
use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Filament\Resources\Cotizacions\Pages\ListCotizacions;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El listado exportado se abre en Excel y trae los totales de la oferta.
 */
class ExportarCotizacionesTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol = 'Administrador'): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** @return list<list<string>> */
    private function filas(string $csv): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'Sin la marca BOM, Excel rompe las tildes.');

        return array_map(
            fn (string $linea): array => str_getcsv($linea, ';', '"', ''),
            array_values(array_filter(explode("\n", substr($csv, 3)), fn (string $l): bool => trim($l) !== '')),
        );
    }

    public function test_una_fila_por_cotizacion_con_el_total_de_la_oferta(): void
    {
        $filas = $this->filas(ExportarCotizaciones::crear()->aCsv(Cotizacion::query()->orderBy('id')));

        $this->assertSame('Código', $filas[0][0]);
        $this->assertSame('Total con IVA', end($filas[0]));
        $this->assertCount(Cotizacion::query()->count() + 1, $filas);

        $calculo = CalcularCotizacion::crear();
        $indice = array_search('Total con IVA', $filas[0], true);

        foreach (array_slice($filas, 1) as $fila) {
            $cotizacion = Cotizacion::query()->where('codigo', $fila[0])->firstOrFail();
            $this->assertSame((string) (int) round($calculo->para($cotizacion)->totales->total), $fila[$indice], "Total de {$fila[0]}.");
        }
    }

    /** La 10 guarda $579.642 de recursos que ya no tiene: se exporta su valor real, cero. */
    public function test_no_exporta_totales_guardados_obsoletos(): void
    {
        $filas = $this->filas(ExportarCotizaciones::crear()->aCsv(Cotizacion::query()->whereKey(10)));

        $this->assertSame('0', end($filas[1]));
    }

    /** Una celda que empieza por "=" no se ejecuta como formula al abrir el archivo. */
    public function test_neutraliza_textos_que_parecen_formulas(): void
    {
        Cotizacion::query()->whereKey(55)->update(['cliente' => '=HYPERLINK("http://malo")']);

        $filas = $this->filas(ExportarCotizaciones::crear()->aCsv(Cotizacion::query()->whereKey(55)));

        $this->assertStringStartsWith("'=", $filas[1][2]);
    }

    /** Desde el listado: descarga, y solo lo que el alcance deja ver. */
    public function test_se_descarga_desde_el_listado_respetando_el_alcance(): void
    {
        $gerente = $this->usuario('Gerente');
        $this->actingAs($gerente);

        $visibles = (new AlcanceDeCotizaciones)->aplicar(Cotizacion::query(), $gerente)->count();
        $this->assertLessThan(Cotizacion::query()->count(), $visibles);

        $respuesta = Livewire::test(ListCotizacions::class)->callAction('exportar');
        $respuesta->assertFileDownloaded(contentType: 'text/csv; charset=UTF-8');

        $csv = ExportarCotizaciones::crear()->aCsv(
            (new AlcanceDeCotizaciones)->aplicar(Cotizacion::query(), $gerente)
        );
        $this->assertCount($visibles + 1, $this->filas($csv));
    }
}
