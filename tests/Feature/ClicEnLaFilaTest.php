<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pulsar una fila lleva a editar, que es a lo que se entra.
 *
 * Filament abria por defecto la vista de solo lectura y obligaba a buscar
 * despues el boton de editar. Pero no todas se pueden editar -lo aprobado no
 * se toca, y lo que espera decision solo lo toca quien decide-, asi que esas
 * siguen abriendo la vista: mandarlas a editar daria una pantalla que rebota
 * con una explicacion.
 */
class ClicEnLaFilaTest extends TestCase
{
    use DatabaseTransactions;

    private function administrador(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Administrador');

        return $usuario;
    }

    /** Una que se puede editar, abre en editar. */
    public function test_una_editable_abre_en_editar(): void
    {
        $usuario = $this->administrador();

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->forceFill(['aprobacion1' => null, 'aprobacion2' => null])->save();

        $this->assertTrue(
            $cotizacion->refresh()->puedeSerEditadaPor($usuario),
            'Para esta prueba la cotización debe ser editable.',
        );

        $this->assertStringEndsWith('/edit', $this->destinoDelClic($usuario, $cotizacion));
    }

    /** Una aprobada no se toca: abre en la vista. */
    public function test_una_aprobada_abre_en_la_vista(): void
    {
        $usuario = $this->administrador();

        $cotizacion = Cotizacion::findOrFail(55);
        $cotizacion->forceFill(['aprobacion1' => 'SI', 'aprobacion2' => 'SI'])->save();

        $this->assertFalse(
            $cotizacion->refresh()->puedeSerEditadaPor($usuario),
            'Una aprobada no debería poder editarse.',
        );

        $this->assertStringEndsNotWith('/edit', $this->destinoDelClic($usuario, $cotizacion));
    }

    /**
     * A donde lleva pulsar la fila.
     *
     * Se saca de la propia tabla y no de una ruta escrita a mano, para que la
     * prueba siga lo que hace la pantalla.
     */
    private function destinoDelClic(User $usuario, Cotizacion $cotizacion): string
    {
        $this->actingAs($usuario);

        $tabla = \App\Filament\Resources\Cotizacions\CotizacionResource::table(
            \Filament\Tables\Table::make(
                new \App\Filament\Resources\Cotizacions\Pages\ListCotizacions(),
            ),
        );

        return $tabla->getRecordUrl($cotizacion) ?? '';
    }
}
