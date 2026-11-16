<?php

namespace Tests\Feature;

use App\Dominio\Aiu\ParametrosAiu;
use App\Filament\Resources\Cotizacions\Pages\EditCotizacion;
use App\Models\Cotizacion;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El margen del AIU: administracion, imprevistos y utilidad.
 *
 * Se configura desde la pestaña "Calculo de AIU", en un modal. Vive ahi y
 * no entre los datos de cabecera porque es lo ultimo que se cuadra, cuando
 * los recursos ya estan cargados.
 *
 * Estas pruebas cubren lo que antes vigilaba el formulario: que el margen
 * se guarde, que las banderas viajen en el formato de produccion y que
 * guardar la cotizacion no lo altere.
 */
class MargenAiuTest extends TestCase
{
    /**
     * Un usuario que SI puede editar la cotizacion de las pruebas.
     *
     * La 55 espera aprobacion tecnica, y en ese estado solo la toca el
     * gerente de su gerencia.
     */
    private function comoUsuario(): User
    {
        $gerencia = Cotizacion::query()->whereKey(55)->value('gerencia');

        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);
        $usuario->assignRole('Gerente');

        $this->actingAs($usuario);

        return $usuario;
    }

    /** Una copia desechable de la 55, que vigilan los golden masters. */
    private function copiaDeLa55(): Cotizacion
    {
        $copia = Cotizacion::findOrFail(55)->replicate(['id']);
        $copia->codigo = 'MARGEN-' . uniqid();
        $copia->save();

        return $copia;
    }

    /** Se cambia el margen desde la pestaña de AIU. */
    public function test_se_cambia_el_margen(): void
    {
        $this->comoUsuario();
        $copia = $this->copiaDeLa55();

        try {
            Livewire::test(EditCotizacion::class, ['record' => $copia->id])
                ->callAction('cambiarMargen', data: [
                    'administracion' => 10,
                    'imprevistos' => 5,
                    'utilidad' => 8,
                    'contrato_obra' => true,
                    'mostrar_aiu' => true,
                    'contrato_actual' => false,
                ])
                ->assertHasNoActionErrors();

            $aiu = $copia->aiu()->first();

            $this->assertNotNull($aiu, 'No se guardó el margen.');
            $this->assertSame(10.0, $aiu->administracion);
            $this->assertSame(5.0, $aiu->imprevistos);
            $this->assertSame(8.0, $aiu->utilidad);
        } finally {
            $copia->aiu()->delete();
            $copia->delete();
        }
    }

    /**
     * Las banderas se guardan como texto, no como booleano.
     *
     * Asi estan los datos de produccion, y esta tabla la comparte el
     * sistema que sigue en marcha.
     */
    public function test_las_banderas_se_guardan_como_texto(): void
    {
        $this->comoUsuario();
        $copia = $this->copiaDeLa55();

        try {
            Livewire::test(EditCotizacion::class, ['record' => $copia->id])
                ->callAction('cambiarMargen', data: [
                    'administracion' => 5,
                    'imprevistos' => 1,
                    'utilidad' => 7,
                    'contrato_obra' => true,
                    'mostrar_aiu' => false,
                    'contrato_actual' => false,
                ]);

            $aiu = $copia->aiu()->first();

            $this->assertSame('SI', $aiu->contrato_obra);
            $this->assertSame('NO', $aiu->mostrar_aiu);
            $this->assertSame('NO', $aiu->contrato_actual);
            $this->assertTrue($aiu->esContratoDeObra());
        } finally {
            $copia->aiu()->delete();
            $copia->delete();
        }
    }

    /**
     * Una cotizacion sin fila de AIU tambien recibe su margen.
     *
     * Nueve de las cuarenta y ocho reales no la tienen: se crearon antes
     * de que el AIU existiera.
     */
    public function test_el_margen_se_crea_en_una_cotizacion_que_no_lo_tenia(): void
    {
        $this->comoUsuario();
        $copia = $this->copiaDeLa55();
        $copia->aiu()->delete();

        $this->assertNull($copia->fresh()->aiu);

        try {
            Livewire::test(EditCotizacion::class, ['record' => $copia->id])
                ->callAction('cambiarMargen', data: [
                    'administracion' => 12,
                    'imprevistos' => 3,
                    'utilidad' => 7,
                    'contrato_obra' => false,
                    'mostrar_aiu' => false,
                    'contrato_actual' => false,
                ])
                ->assertHasNoActionErrors();

            $this->assertNotNull($copia->fresh()->aiu, 'No se creó la fila del AIU.');
            $this->assertSame(12.0, $copia->aiu()->first()->administracion);
        } finally {
            $copia->aiu()->delete();
            $copia->delete();
        }
    }

    /**
     * El modal entiende las banderas guardadas como '1' y '0'.
     *
     * En los datos reales conviven los dos formatos. Si solo entendiera
     * 'SI'/'NO', abrir una cotizacion guardada con '1' mostraria el
     * interruptor apagado y al guardar lo apagaria de verdad: un cambio
     * de precio silencioso.
     */
    public function test_entiende_las_banderas_guardadas_como_uno(): void
    {
        $this->comoUsuario();
        $copia = $this->copiaDeLa55();

        $copia->aiu()->delete();
        $copia->aiu()->create([
            'contrato_obra' => '1',
            'mostrar_aiu' => '0',
            'administracion' => 10,
            'imprevistos' => 5,
            'utilidad' => 8,
        ]);

        try {
            $this->assertTrue($copia->fresh()->aiu->esContratoDeObra());
            $this->assertFalse($copia->fresh()->aiu->muestraAiuDesglosado());

            /** Y el escenario que de ahi sale es el correcto. */
            $this->assertSame(
                'obra/aiu-oculto',
                ParametrosAiu::desdeModelo($copia->fresh()->aiu)->escenario()
            );
        } finally {
            $copia->aiu()->delete();
            $copia->delete();
        }
    }

    /**
     * Guardar la cabecera NO altera el margen.
     *
     * Fallo real del 2026-11-13: las banderas son texto y el interruptor
     * las recibia sin convertir, de modo que 'NO' se cargaba como activado
     * y al guardar quedaba en 'SI'. Cambiar una observacion invertia el
     * escenario del AIU y con el **el precio final**.
     *
     * Ahora el margen vive fuera del formulario, asi que no deberia poder
     * tocarse al guardar. Esta prueba lo vigila.
     */
    public function test_guardar_la_cabecera_no_altera_el_margen(): void
    {
        $this->comoUsuario();
        $copia = $this->copiaDeLa55();

        $copia->aiu()->delete();
        $copia->aiu()->create([
            'contrato_obra' => 'NO',
            'mostrar_aiu' => 'NO',
            'contrato_actual' => 'NO',
            'administracion' => 5.78,
            'imprevistos' => 0.5,
            'utilidad' => 8,
        ]);

        try {
            Livewire::test(EditCotizacion::class, ['record' => $copia->id])
                ->fillForm(['observaciones' => '<p>Solo cambio esto</p>'])
                ->call('save')
                ->assertHasNoFormErrors();

            $aiu = $copia->aiu()->first();

            $this->assertSame('NO', $aiu->contrato_obra, 'Guardar activó "contrato de obra".');
            $this->assertSame('NO', $aiu->mostrar_aiu, 'Guardar activó "mostrar AIU".');
            $this->assertSame(
                'servicio/aiu-oculto',
                ParametrosAiu::desdeModelo($aiu)->escenario(),
                'Cambió el escenario del AIU, y con él el precio.'
            );
        } finally {
            $copia->aiu()->delete();
            $copia->delete();
        }
    }

    /** La pestaña de AIU ofrece cambiar el margen. */
    public function test_la_pestana_ofrece_cambiar_el_margen(): void
    {
        $this->comoUsuario();

        Livewire::test(EditCotizacion::class, ['record' => 55])
            ->assertActionExists('cambiarMargen');
    }

    /**
     * El resumen de la oferta cuadra con el calculo.
     *
     * Es la misma tabla que lleva el documento Word: si aqui saliera otra
     * cifra, quien revisa daria por bueno un precio que no es el que se
     * manda al cliente.
     */
    public function test_el_resumen_cuadra_con_el_calculo(): void
    {
        $this->comoUsuario();

        $pagina = Livewire::test(EditCotizacion::class, ['record' => 55])->instance();

        $metodo = new \ReflectionMethod($pagina, 'datosDelResumen');
        $datos = $metodo->invoke($pagina);

        $calculado = \App\Dominio\CalcularCotizacion::crear()
            ->para(Cotizacion::findOrFail(55))
            ->totales;

        $this->assertEqualsWithDelta($calculado->total, $datos['totales']['TOTAL'], 0.01);
        $this->assertEqualsWithDelta(
            $calculado->costoDirecto,
            $datos['totales']['Costo directo'],
            0.01
        );
        $this->assertNotEmpty($datos['items'], 'El resumen salió sin ítems.');
    }

    /**
     * El reparto por categoria suma el costo directo.
     *
     * Si no sumara, habria recursos contados dos veces o ninguna, y el
     * reparto diria de donde sale un dinero que no es el que se cobra.
     */
    public function test_el_reparto_por_categoria_suma_el_costo_directo(): void
    {
        $calculo = \App\Dominio\CalcularCotizacion::crear()
            ->para(Cotizacion::findOrFail(55));

        $this->assertEqualsWithDelta(
            $calculo->totales->costoDirecto,
            array_sum($calculo->costoPorCategoria()),
            1.0,
            'El reparto por categoría no suma el costo directo.'
        );
    }

    /** La pestaña de AIU reune todo el cierre. */
    public function test_la_pestana_reune_el_cierre(): void
    {
        $this->comoUsuario();

        $pagina = Livewire::test(EditCotizacion::class, ['record' => 55])->instance();

        $pestanas = array_map(
            fn ($pestana): string => $pestana->getLabel(),
            $pagina->getTabComponents()
        );

        $this->assertContains('Cálculo de AIU', $pestanas);
        $this->assertSame('Cálculo de AIU', end($pestanas), 'El cierre debe ir al final.');
    }
}
