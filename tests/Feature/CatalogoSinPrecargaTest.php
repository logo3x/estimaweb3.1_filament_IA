<?php

namespace Tests\Feature;

use App\Filament\Resources\CajasConsumibles\CajaConsumibleResource;
use App\Filament\Resources\CajasEpps\CajaEppResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * El selector de articulos busca en la base, no precarga el catalogo.
 *
 * Con ->preload() Filament traia el catalogo ENTERO en cada apertura del
 * modal: en consumibles son 3.409 articulos y 199 KB que el navegador tenia
 * que recibir y dibujar antes de que la lista respondiera.
 */
class CatalogoSinPrecargaTest extends TestCase
{
    use DatabaseTransactions;

    /** Al abrir no se vuelca el catalogo entero. */
    public function test_al_abrir_solo_llegan_unos_pocos(): void
    {
        $opciones = $this->invocar(CajaConsumibleResource::class, 'primerosDelCatalogo');

        $this->assertLessThanOrEqual(50, count($opciones));
        $this->assertNotEmpty($opciones, 'La lista no debe abrir vacía.');
    }

    /** Hay bastante mas en la base que lo que se manda de golpe. */
    public function test_el_catalogo_real_es_mucho_mayor(): void
    {
        $total = \App\Models\DatosConsumible::query()->count();

        $this->assertGreaterThan(
            500,
            $total,
            'Sin un catálogo grande esta prueba no demuestra nada.',
        );

        $this->assertLessThan(
            $total,
            count($this->invocar(CajaConsumibleResource::class, 'primerosDelCatalogo')),
        );
    }

    /** Lo que se escribe se busca en la base, no entre las primeras. */
    public function test_la_busqueda_encuentra_mas_alla_de_las_primeras(): void
    {
        $resultados = $this->invocar(CajaConsumibleResource::class, 'buscarEnElCatalogo', 'guante');

        $this->assertNotEmpty($resultados, 'No encontró nada para «guante».');

        foreach ($resultados as $etiqueta) {
            $this->assertStringContainsStringIgnoringCase('guante', $etiqueta);
        }
    }

    /** Una busqueda vacia no deja la lista en blanco. */
    public function test_una_busqueda_vacia_devuelve_los_primeros(): void
    {
        $this->assertNotEmpty($this->invocar(CajaEppResource::class, 'buscarEnElCatalogo', '  '));
    }

    /**
     * Al editar, lo ya elegido se ve con su nombre.
     *
     * Sin esto los articulos guardados apareceran como numeros.
     */
    public function test_lo_ya_elegido_se_muestra_con_su_nombre(): void
    {
        $ids = \App\Models\Epp::query()->limit(2)->pluck('id')->all();

        $etiquetas = $this->invocar(CajaEppResource::class, 'etiquetasDe', $ids);

        $this->assertCount(2, $etiquetas);

        foreach ($etiquetas as $etiqueta) {
            $this->assertStringContainsString('$', $etiqueta, 'Debe llevar el precio al lado.');
        }
    }

    /** Sin ids no se consulta la base. */
    public function test_sin_ids_no_hay_etiquetas(): void
    {
        $this->assertSame([], $this->invocar(CajaEppResource::class, 'etiquetasDe', []));
    }

    /**
     * Llama a un metodo protegido del Resource.
     *
     * Son protegidos a proposito -solo los usa el propio formulario-, pero
     * aqui interesa fijar su comportamiento.
     */
    private function invocar(string $clase, string $metodo, mixed ...$argumentos): array
    {
        $reflexion = new \ReflectionMethod($clase, $metodo);
        $reflexion->setAccessible(true);

        return $reflexion->invoke(null, ...$argumentos);
    }
}
