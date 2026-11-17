<?php

namespace Tests\Feature;

use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
use App\Models\Cotizacion;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El precio se rehace al cambiar el margen.
 *
 * Si no, quien ajusta el AIU ve el total anterior y da por bueno un precio
 * que no es el que saldria en la oferta.
 */
class RecalculoAlCambiarAiuTest extends TestCase
{
    private function comoUsuario(): User
    {
        $gerencia = Cotizacion::query()->whereKey(55)->value('gerencia');

        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);
        $usuario->assignRole('Gerente');
        $this->actingAs($usuario);

        return $usuario;
    }

    /** Cambiar el margen cambia el total que se muestra. */
    public function test_el_total_cambia_al_cambiar_el_margen(): void
    {
        $this->comoUsuario();

        /**
         * Sobre la 55, que SI tiene recursos cargados.
         *
         * Una copia con `replicate()` no los arrastra: el calculo da cero
         * y la prueba no compararia nada.
         */
        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->aiu?->only([
            'administracion', 'imprevistos', 'utilidad',
            'contrato_obra', 'mostrar_aiu', 'contrato_actual',
        ]);

        try {
            $componente = Livewire::test(EditCotizacion::class, ['record' => 55]);

            $antes = $this->totalQueMuestra($componente->instance());

            $componente->callAction('cambiarMargen', data: [
                'administracion' => 20,
                'imprevistos' => 5,
                'utilidad' => 15,
                'contrato_obra' => false,
                'mostrar_aiu' => false,
                'contrato_actual' => false,
            ]);

            $despues = $this->totalQueMuestra($componente->instance());

            $this->assertGreaterThan(
                $antes,
                $despues,
                'El total no se rehizo: sigue mostrando el precio del margen anterior.'
            );
        } finally {
            if ($original !== null) {
                $cotizacion->aiu()->update($original);
            }
        }
    }

    /** El total que la pantalla tiene calculado en este momento. */
    private function totalQueMuestra(EditCotizacion $pagina): float
    {
        $metodo = new \ReflectionMethod($pagina, 'calculo');

        return $metodo->invoke($pagina)->totales->total;
    }

    /** Y el resumen de la oferta también. */
    public function test_el_resumen_tambien_se_rehace(): void
    {
        $this->comoUsuario();

        $cotizacion = Cotizacion::findOrFail(55);
        $original = $cotizacion->aiu?->only([
            'administracion', 'imprevistos', 'utilidad',
            'contrato_obra', 'mostrar_aiu', 'contrato_actual',
        ]);

        try {
            $componente = Livewire::test(EditCotizacion::class, ['record' => 55]);

            $metodo = new \ReflectionMethod($componente->instance(), 'datosDelResumen');
            $antes = $metodo->invoke($componente->instance())['totales']['TOTAL'];

            $componente->callAction('cambiarMargen', data: [
                'administracion' => 10, 'imprevistos' => 5, 'utilidad' => 10,
                'contrato_obra' => false, 'mostrar_aiu' => false, 'contrato_actual' => false,
            ]);

            $despues = $metodo->invoke($componente->instance())['totales']['TOTAL'];

            $this->assertGreaterThan($antes, $despues, 'El resumen de la oferta no se rehizo.');
        } finally {
            if ($original !== null) {
                $cotizacion->aiu()->update($original);
            }
        }
    }
}
