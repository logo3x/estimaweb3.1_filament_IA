<?php

namespace Tests\Feature;

use App\Correo\NotificadorDeAprobaciones;
use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Envios\RegistroDeEnvios;
use App\Dominio\Envios\TipoDeEnvio;
use App\Models\Cotizacion;
use App\Models\ImpresionRegistro;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * La bitacora de envios: que salio, cuando y a quien.
 *
 * Es el respaldo ante un cliente que discute una oferta recibida. El
 * sistema anterior solo anotaba la generacion del Word, asi que un correo
 * enviado no dejaba ningun rastro.
 */
class RegistroDeEnviosTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole('Administrador');

        return $usuario;
    }

    /** Generar el Word deja constancia. */
    public function test_un_documento_generado_queda_registrado(): void
    {
        $usuario = $this->usuario();
        $cotizacion = Cotizacion::findOrFail(55);

        $registro = (new RegistroDeEnvios())->documentoGenerado(
            $cotizacion,
            TipoDeEnvio::Word,
            $usuario,
        );

        $this->assertNotNull($registro);
        $this->assertSame(TipoDeEnvio::Word, $registro->tipo);
        $this->assertSame($cotizacion->id, $registro->cotizacion_id);
        $this->assertSame($usuario->id, $registro->usuario_id);
        $this->assertNull($registro->fallo);
    }

    /** El Word y el PDF se distinguen. */
    public function test_el_word_y_el_pdf_no_se_confunden(): void
    {
        $usuario = $this->usuario();
        $cotizacion = Cotizacion::findOrFail(55);
        $registro = new RegistroDeEnvios();

        $word = $registro->documentoGenerado($cotizacion, TipoDeEnvio::Word, $usuario);
        $pdf = $registro->documentoGenerado($cotizacion, TipoDeEnvio::Pdf, $usuario);

        $this->assertSame('Word generado', $word->tipo->etiqueta());
        $this->assertSame('PDF generado', $pdf->tipo->etiqueta());
    }

    /**
     * Un correo queda registrado con su direccion.
     *
     * Es lo que el sistema anterior no hacia: sin la direccion no se puede
     * demostrar un envio.
     */
    public function test_un_correo_queda_registrado_con_su_direccion(): void
    {
        $usuario = $this->usuario();

        $registro = (new RegistroDeEnvios())->correoEnviado(
            cotizacion: Cotizacion::findOrFail(55),
            correoDestino: 'cliente@ejemplo.com',
            quien: $usuario,
        );

        $this->assertNotNull($registro);
        $this->assertSame('cliente@ejemplo.com', $registro->correo_destino);
        $this->assertTrue($registro->salioAlCliente());
    }

    /**
     * Un correo que fallo tambien se registra, con el motivo.
     *
     * Un envio fallido que no deja rastro es peor que no intentarlo: se da
     * por enviado.
     */
    public function test_un_correo_fallido_se_registra_con_el_motivo(): void
    {
        $registro = (new RegistroDeEnvios())->correoEnviado(
            cotizacion: Cotizacion::findOrFail(55),
            correoDestino: 'nadie@ejemplo.com',
            quien: $this->usuario(),
            fallo: 'Buzón inexistente',
        );

        $this->assertSame('Buzón inexistente', $registro->fallo);
        $this->assertFalse($registro->salioAlCliente(), 'Lo que falló no salió al cliente.');
    }

    /** Los avisos internos del flujo quedan anotados al enviarse. */
    public function test_los_avisos_de_aprobacion_quedan_anotados(): void
    {
        Mail::fake();

        $cotizacion = Cotizacion::findOrFail(55);
        $autor = $this->usuario();

        $antes = ImpresionRegistro::where('cotizacion_id', $cotizacion->id)->count();

        $resultado = NotificadorDeAprobaciones::crear()->avisar(
            cotizacion: $cotizacion,
            estado: EstadoAprobacion::EsperandoTecnica,
            autorDeLaAccion: $autor,
        );

        $this->assertGreaterThan(0, $resultado->enviados, 'La prueba necesita al menos un destinatario.');

        $ahora = ImpresionRegistro::where('cotizacion_id', $cotizacion->id)->count();

        $this->assertSame(
            $antes + $resultado->enviados,
            $ahora,
            'Cada correo enviado debe dejar su registro.',
        );

        $ultimo = ImpresionRegistro::where('cotizacion_id', $cotizacion->id)
            ->orderByDesc('id')
            ->first();

        $this->assertSame(TipoDeEnvio::AvisoInterno, $ultimo->tipo);
        $this->assertNotNull($ultimo->correo_destino);
    }

    /**
     * Si la bitacora falla, el usuario no pierde su documento.
     *
     * Es deliberado: perder la oferta por no poder escribir una fila seria
     * peor que perder la fila.
     */
    public function test_un_fallo_al_registrar_no_revienta(): void
    {
        $cotizacion = Cotizacion::findOrFail(55);

        /** Sin usuario la columna no admite nulo: no debe lanzar. */
        $registro = (new RegistroDeEnvios())->documentoGenerado(
            $cotizacion,
            TipoDeEnvio::Word,
            null,
        );

        $this->assertNull($registro, 'Sin usuario no se registra, pero tampoco revienta.');
    }

    /**
     * Un correo larguisimo no rompe la insercion.
     *
     * Las columnas son de 255. En la practica no deberia llegar un texto
     * asi, pero si llegara, el registro tiene que recortarlo en vez de
     * lanzar una excepcion en mitad de una descarga.
     */
    public function test_los_textos_largos_se_recortan(): void
    {
        $registro = (new RegistroDeEnvios())->correoEnviado(
            cotizacion: Cotizacion::findOrFail(55),
            correoDestino: str_repeat('a', 400) . '@ejemplo.com',
            quien: $this->usuario(),
            fallo: str_repeat('b', 400),
        );

        $this->assertNotNull($registro, 'No debe lanzar por un texto largo.');
        $this->assertLessThanOrEqual(255, mb_strlen((string) $registro->fallo));
    }

    /** Solo la administracion consulta la bitacora. */
    public function test_la_bitacora_no_la_ve_cualquiera(): void
    {
        $creador = User::factory()->create(['id_gerencia' => '4']);
        $creador->assignRole('Creador');

        $this->actingAs($creador)->get('/admin/envios')->assertForbidden();
        $this->actingAs($this->usuario())->get('/admin/envios')->assertOk();
    }

    /**
     * La bitacora no se edita ni se borra desde el panel.
     *
     * En el sistema anterior habia rutas que lo permitian, lo que anula su
     * valor como evidencia.
     */
    public function test_la_bitacora_es_solo_de_consulta(): void
    {
        $recurso = \App\Filament\Resources\Envios\EnvioResource::class;
        $registro = ImpresionRegistro::first();

        $this->assertFalse($recurso::canCreate());
        $this->assertFalse($recurso::canEdit($registro));
        $this->assertFalse($recurso::canDelete($registro));
        $this->assertFalse($recurso::canDeleteAny());
    }
}
