<?php

namespace Tests\Feature;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Aprobaciones\RelatoDelFlujo;
use App\Models\Cotizacion;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * El flujo, contado para que se entienda.
 *
 * La pantalla responde a dos preguntas: «¿por qué no puedo editar esto?» y
 * «¿a quién le toca ahora?». Estas pruebas fijan que las responda con los
 * datos que ya están en la base y que antes no se mostraban: cuándo se
 * decidió cada nivel y qué escribió quien decidió.
 */
class RelatoDelFlujoTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Una cotización real, con el flujo puesto a mano.
     *
     * No hay factory de cotizaciones: las pruebas trabajan sobre los datos
     * reales dentro de una transacción, como el resto de la suite. Se
     * limpian ambos niveles para partir siempre del mismo sitio.
     */
    private function cotizacion(array $atributos = []): Cotizacion
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $cotizacion->forceFill(array_merge([
            'aprobacion1' => null,
            'aprobacion2' => null,
            'aprobacion1_user_id' => null,
            'aprobacion2_user_id' => null,
            'aprobacion1_fecha' => null,
            'aprobacion2_fecha' => null,
            'aprobacion1_comentarios' => null,
            'aprobacion2_comentarios' => null,
        ], $atributos))->save();

        return $cotizacion->refresh();
    }

    /** Siempre son tres pasos, pase lo que pase. */
    public function test_siempre_hay_tres_pasos(): void
    {
        $pasos = RelatoDelFlujo::de($this->cotizacion())->pasos();

        $this->assertCount(3, $pasos);
        $this->assertSame('Elaboración', $pasos[0]->titulo);
        $this->assertSame('Revisión técnica', $pasos[1]->titulo);
        $this->assertSame('Aprobación financiera', $pasos[2]->titulo);
    }

    /** El primero está hecho: si la cotización existe, alguien la escribió. */
    public function test_la_elaboracion_siempre_esta_hecha(): void
    {
        $this->assertTrue(RelatoDelFlujo::de($this->cotizacion())->pasos()[0]->estaHecho());
    }

    /** En borrador, nadie ha decidido nada todavía. */
    public function test_un_borrador_no_tiene_ningun_nivel_decidido(): void
    {
        $relato = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => null,
            'aprobacion2' => null,
        ]));

        $this->assertSame(EstadoAprobacion::Borrador, $relato->estado());
        $this->assertFalse($relato->pasos()[1]->esElActual());
        $this->assertStringContainsString('autor', $relato->aQuienLeToca());
    }

    /**
     * `PENDIENTE` marca el paso como el actual.
     *
     * Es un valor real que escribe el propio flujo al enviar a aprobación.
     * Antes se pintaba crudo y en mayúsculas en mitad de la pantalla.
     */
    public function test_pendiente_marca_el_paso_actual_sin_mostrarse_crudo(): void
    {
        $paso = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'PENDIENTE',
        ]))->pasos()[1];

        $this->assertTrue($paso->esElActual());
        $this->assertStringNotContainsString('PENDIENTE', $paso->quien);
        $this->assertNotSame('', $paso->queFalta, 'El paso actual debe decir qué falta.');
    }

    /** Aprobada la técnica, le toca a la administración. */
    public function test_tras_la_tecnica_le_toca_a_la_administracion(): void
    {
        $relato = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'SI',
            'aprobacion2' => 'PENDIENTE',
        ]));

        $this->assertTrue($relato->pasos()[1]->estaHecho());
        $this->assertTrue($relato->pasos()[2]->esElActual());
        $this->assertStringContainsString('administración', $relato->aQuienLeToca());
    }

    /** Un rechazo se ve como tal. */
    public function test_un_rechazo_se_marca_en_su_paso(): void
    {
        $paso = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'NO',
        ]))->pasos()[1];

        $this->assertTrue($paso->fueRechazado());
        $this->assertSame('✕', $paso->marca(2));
    }

    /**
     * El comentario de quien decidió se muestra.
     *
     * Es el motivo de la devolución: lo que dice qué corregir. Estaba
     * guardado en la base y no aparecía en ninguna pantalla.
     */
    public function test_el_comentario_de_quien_decidio_llega_a_la_pantalla(): void
    {
        $paso = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'NO',
            'aprobacion1_comentarios' => 'Falta incluir el incremento del AIU',
        ]))->pasos()[1];

        $this->assertSame('Falta incluir el incremento del AIU', $paso->comentario);
    }

    /** Y cuándo se decidió, en palabras. */
    public function test_la_fecha_de_la_decision_se_cuenta_en_palabras(): void
    {
        $paso = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'SI',
            'aprobacion1_fecha' => now()->subDays(3),
        ]))->pasos()[1];

        $this->assertStringContainsString('3 días', $paso->cuando);
    }

    /** Lo que aún no ha pasado no lleva fecha. */
    public function test_un_paso_pendiente_no_inventa_fecha(): void
    {
        $this->assertSame(
            '',
            RelatoDelFlujo::de($this->cotizacion())->pasos()[2]->cuando,
        );
    }

    /**
     * Una decisión vieja sin autor no inventa un nombre.
     *
     * 22 cotizaciones aprobadas vienen del sistema anterior sin registrar
     * quién decidió.
     */
    public function test_una_decision_sin_autor_lo_dice_en_vez_de_inventarlo(): void
    {
        $paso = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'SI',
            'aprobacion1_user_id' => null,
        ]))->pasos()[1];

        $this->assertStringContainsString('Aprobada', $paso->quien);
        $this->assertStringContainsString('no se registró', $paso->quien);
    }

    /** Una aprobada ya no espera a nadie. */
    public function test_una_aprobada_no_espera_a_nadie(): void
    {
        $relato = RelatoDelFlujo::de($this->cotizacion([
            'aprobacion1' => 'SI',
            'aprobacion2' => 'SI',
        ]));

        $this->assertSame('', $relato->aQuienLeToca());
        $this->assertStringContainsString('no admite cambios', $relato->resumen());
    }

    /** El resumen nunca queda vacío, en ningún estado. */
    public function test_todos_los_estados_tienen_resumen(): void
    {
        $casos = [
            [null, null],
            ['PENDIENTE', null],
            ['SI', 'PENDIENTE'],
            ['NO', null],
            ['SI', 'NO'],
            ['SI', 'SI'],
        ];

        foreach ($casos as [$tecnica, $financiera]) {
            $relato = RelatoDelFlujo::de($this->cotizacion([
                'aprobacion1' => $tecnica,
                'aprobacion2' => $financiera,
            ]));

            $this->assertNotSame('', $relato->resumen());
        }
    }

    /** Sin usuario, a nadie le toca decidir. */
    public function test_sin_usuario_no_le_toca_a_nadie(): void
    {
        $this->assertFalse(
            RelatoDelFlujo::de($this->cotizacion(['aprobacion1' => 'PENDIENTE']))->leTocaA(null),
        );
    }
}
