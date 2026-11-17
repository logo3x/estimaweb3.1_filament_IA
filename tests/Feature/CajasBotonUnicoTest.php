<?php

namespace Tests\Feature;

use App\Filament\Resources\CajasEpps\Pages\GestionarCajaEpp;
use App\Models\Epp;
use App\Models\PrecargadoEpp;
use App\Models\User;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un solo boton para crear una caja, y que sea el que guarda los articulos.
 *
 * Habia dos: uno en la cabecera de la pagina y otro en la de la tabla. No era
 * solo cosmetico: el de la tabla creaba la caja **vacia**, porque los
 * articulos no son una columna -viven en otra tabla- y hay que escribirlos a
 * mano despues de guardar. Quien usara ese boton perdia su seleccion sin
 * aviso.
 */
class CajasBotonUnicoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Creador');

        return $usuario;
    }

    /**
     * La pantalla trae un solo boton de crear.
     *
     * Se cuenta sobre la pagina ya dibujada, que es donde se veia el fallo:
     * uno venia de la cabecera de la pagina y otro de la de la tabla.
     */
    public function test_la_pantalla_trae_un_solo_boton_de_crear(): void
    {
        $this->actingAs($this->usuario());

        $html = Livewire::test(GestionarCajaEpp::class)->html();

        /**
         * Se cuenta la etiqueta suelta, no `>Nueva caja<`.
         *
         * Filament la dibuja rodeada de saltos de linea y espacios -el icono
         * va delante-, de modo que nunca toca las etiquetas.
         */
        $this->assertSame(
            1,
            substr_count($html, 'Nueva caja'),
            'Debe haber exactamente un boton «Nueva caja» en la pantalla.',
        );
    }

    /** El boton que queda guarda los articulos, no solo el nombre. */
    public function test_crear_una_caja_guarda_sus_articulos(): void
    {
        $this->actingAs($usuario = $this->usuario());

        $articulos = Epp::query()->limit(2)->pluck('id')->all();

        $this->assertCount(2, $articulos, 'El catálogo de EPP debe tener artículos.');

        Livewire::test(GestionarCajaEpp::class)
            ->callAction(CreateAction::class, [
                'nombre' => 'Caja de prueba',
                'articulos' => $articulos,
            ])
            ->assertHasNoFormErrors();

        $caja = PrecargadoEpp::query()
            ->where('nombre', 'Caja de prueba')
            ->where('id_user', $usuario->id)
            ->first();

        $this->assertNotNull($caja, 'La caja no se creó.');
        $this->assertCount(2, $caja->elementos, 'La caja se creó vacía: se perdieron los artículos.');
    }
}
