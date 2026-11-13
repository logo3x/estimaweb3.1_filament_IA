<?php

namespace Tests\Feature;

use App\Correo\AvisoDeAprobacion;
use App\Correo\DestinatariosDelAviso;
use App\Correo\NotificadorDeAprobaciones;
use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Los avisos por correo del flujo de aprobacion.
 *
 * Comprueban a QUIEN se avisa en cada transicion, que es lo que el sistema
 * actual tiene entremezclado con los envios dentro de un metodo de 160
 * lineas y no se puede verificar sin mandar correos de verdad.
 *
 * Aqui no sale ningun correo: Mail::fake() los intercepta.
 */
class AvisosDeAprobacionTest extends TestCase
{
    use DatabaseTransactions;

    private const GERENCIA = '4';

    private DestinatariosDelAviso $destinatarios;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->destinatarios = new DestinatariosDelAviso();
    }

    private function usuario(?string $rol = null, ?string $gerencia = self::GERENCIA): User
    {
        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);

        if ($rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    }

    private function cotizacion(array $atributos = []): Cotizacion
    {
        $cotizacion = Cotizacion::findOrFail(55);

        $cotizacion->forceFill(array_merge([
            'gerencia' => self::GERENCIA,
            'aprobacion1' => null,
            'aprobacion2' => null,
        ], $atributos))->save();

        return $cotizacion->refresh();
    }

    /** @return list<string> */
    private function correosDe(Cotizacion $cotizacion, EstadoAprobacion $estado): array
    {
        return $this->destinatarios->para($cotizacion, $estado)
            ->map(fn (array $a): string => (string) $a['usuario']->email)
            ->all();
    }

    /** Al enviar a aprobacion se avisa al gerente de la gerencia. */
    public function test_al_enviar_se_avisa_al_gerente_de_la_gerencia(): void
    {
        $gerente = $this->usuario('Gerente');
        $ajeno = $this->usuario('Gerente', gerencia: '7');

        $correos = $this->correosDe($this->cotizacion(), EstadoAprobacion::EsperandoTecnica);

        $this->assertContains($gerente->email, $correos);
        $this->assertNotContains($ajeno->email, $correos, 'Se avisó a un gerente de otra gerencia.');
    }

    /** Tras la aprobacion tecnica se avisa a la administracion y al autor. */
    public function test_tras_la_tecnica_se_avisa_a_administracion_y_al_autor(): void
    {
        $admin = $this->usuario('Administrador');
        $autor = $this->usuario('Creador');

        $cotizacion = $this->cotizacion(['id_user' => $autor->id, 'aprobacion1' => 'SI', 'aprobacion2' => 'PENDIENTE']);

        $correos = $this->correosDe($cotizacion, EstadoAprobacion::EsperandoFinanciera);

        $this->assertContains($admin->email, $correos);
        $this->assertContains($autor->email, $correos);
    }

    /** Un rechazo tecnico avisa SOLO al autor. */
    public function test_el_rechazo_tecnico_avisa_solo_al_autor(): void
    {
        $autor = $this->usuario('Creador');
        $this->usuario('Administrador');

        $cotizacion = $this->cotizacion(['id_user' => $autor->id, 'aprobacion1' => 'NO']);

        $this->assertSame(
            [$autor->email],
            $this->correosDe($cotizacion, EstadoAprobacion::RechazadaTecnica)
        );
    }

    /** Un rechazo financiero avisa al gerente, que es quien lo corrige. */
    public function test_el_rechazo_financiero_avisa_al_gerente(): void
    {
        $gerente = $this->usuario('Gerente');
        $autor = $this->usuario('Creador');

        $cotizacion = $this->cotizacion(['id_user' => $autor->id, 'aprobacion1' => 'SI', 'aprobacion2' => 'NO']);

        $correos = $this->correosDe($cotizacion, EstadoAprobacion::RechazadaFinanciera);

        $this->assertContains($gerente->email, $correos);
        $this->assertContains($autor->email, $correos);
    }

    /** Volver a borrador no genera avisos: no hay nada que hacer. */
    public function test_el_borrador_no_genera_avisos(): void
    {
        $this->usuario('Gerente');
        $this->usuario('Administrador');

        $this->assertSame([], $this->correosDe($this->cotizacion(), EstadoAprobacion::Borrador));
    }

    /**
     * Quien es a la vez autor y aprobador recibe UN correo, no dos.
     *
     * Sin esto, quien crea una cotizacion de su propia gerencia y ademas es
     * gerente recibiria el mismo aviso por duplicado.
     */
    public function test_no_se_envia_el_mismo_aviso_dos_veces(): void
    {
        $ambos = $this->usuario('Gerente');
        $ambos->assignRole('Administrador');

        $cotizacion = $this->cotizacion(['id_user' => $ambos->id, 'aprobacion1' => 'SI', 'aprobacion2' => 'PENDIENTE']);

        $correos = $this->correosDe($cotizacion, EstadoAprobacion::EsperandoFinanciera);

        $this->assertSame(
            count($correos),
            count(array_unique($correos)),
            'Un mismo destinatario recibiría el aviso por duplicado.'
        );
    }

    /** A quien no tiene correo no se le intenta enviar. */
    public function test_se_omite_a_quien_no_tiene_correo(): void
    {
        $autor = $this->usuario('Creador');
        $autor->forceFill(['email' => ''])->save();

        $cotizacion = $this->cotizacion(['id_user' => $autor->id, 'aprobacion1' => 'NO']);

        $this->assertSame([], $this->correosDe($cotizacion->refresh(), EstadoAprobacion::RechazadaTecnica));
    }

    /** El aviso sale de verdad, con su asunto y su destinatario. */
    public function test_se_envia_el_aviso_con_el_asunto_correcto(): void
    {
        $gerente = $this->usuario('Gerente');
        $cotizacion = $this->cotizacion(['aprobacion1' => 'PENDIENTE']);

        $resultado = NotificadorDeAprobaciones::crear()->avisar(
            cotizacion: $cotizacion,
            estado: EstadoAprobacion::EsperandoTecnica,
            autorDeLaAccion: $this->usuario('Creador'),
        );

        /**
         * Se cuenta contra los destinatarios reales, no contra un numero
         * fijo: la gerencia tiene varios gerentes y el dato puede cambiar.
         */
        $esperados = (new DestinatariosDelAviso())
            ->para($cotizacion, EstadoAprobacion::EsperandoTecnica)
            ->count();

        $this->assertSame($esperados, $resultado->enviados);
        $this->assertGreaterThan(0, $resultado->enviados);
        $this->assertFalse($resultado->huboFallos());

        Mail::assertSent(AvisoDeAprobacion::class, function (AvisoDeAprobacion $aviso) use ($gerente, $cotizacion): bool {
            return $aviso->hasTo($gerente->email)
                && $aviso->cotizacion->is($cotizacion)
                && $aviso->estado === EstadoAprobacion::EsperandoTecnica;
        });
    }

    /** El asunto lleva el codigo y dice que se espera. */
    public function test_el_asunto_dice_que_se_espera(): void
    {
        $cotizacion = $this->cotizacion(['aprobacion1' => 'PENDIENTE']);

        $aviso = new AvisoDeAprobacion($cotizacion, EstadoAprobacion::EsperandoTecnica);

        $asunto = $aviso->envelope()->subject;

        $this->assertStringContainsString((string) $cotizacion->codigo, (string) $asunto);
        $this->assertStringContainsString('aprobación técnica', (string) $asunto);
    }

    /** El correo se puede construir en los seis estados sin romperse. */
    public function test_el_correo_se_construye_en_todos_los_estados(): void
    {
        $cotizacion = $this->cotizacion();
        $usuario = $this->usuario('Gerente');

        foreach (EstadoAprobacion::cases() as $estado) {
            foreach ([true, false] as $paraElAutor) {
                $aviso = new AvisoDeAprobacion(
                    cotizacion: $cotizacion,
                    estado: $estado,
                    autorDeLaAccion: $usuario,
                    comentarios: 'Un comentario.',
                    paraElAutor: $paraElAutor,
                );

                $this->assertNotEmpty($aviso->envelope()->subject, "Sin asunto en '{$estado->value}'.");

                /** El cuerpo se renderiza: una plantilla rota se ve aqui. */
                $cuerpo = $aviso->render();

                $this->assertStringContainsString((string) $cotizacion->codigo, $cuerpo);
                $this->assertStringContainsString('Un comentario.', $cuerpo);
            }
        }
    }

    /**
     * Un fallo de correo NO deshace la aprobacion.
     *
     * La decision ya esta guardada cuando se avisa: que Microsoft no
     * responda no puede dejar al usuario con una pantalla de error.
     */
    public function test_un_fallo_de_correo_no_tumba_la_aprobacion(): void
    {
        $this->usuario('Gerente');
        $cotizacion = $this->cotizacion(['aprobacion1' => 'PENDIENTE']);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Microsoft no responde'));

        $resultado = NotificadorDeAprobaciones::crear()->avisar(
            cotizacion: $cotizacion,
            estado: EstadoAprobacion::EsperandoTecnica,
        );

        $this->assertSame(0, $resultado->enviados);
        $this->assertTrue($resultado->huboFallos());
        $this->assertStringContainsString('no se pudo avisar', $resultado->resumen());
    }

    /**
     * El secreto de Microsoft no aparece en ningun mensaje.
     *
     * Un error de transporte se muestra al usuario y se escribe al registro;
     * si llevara la credencial dentro, quedaria en los logs del hosting.
     */
    public function test_el_secreto_no_se_filtra_en_los_errores(): void
    {
        $transporte = new \App\Correo\TransporteMicrosoftGraph(
            tenantId: 'un-tenant',
            clientId: 'un-cliente',
            clientSecret: 'EL-SECRETO-QUE-NO-DEBE-SALIR',
            remitente: 'remitente@ejemplo.test',
        );

        $codigo = (string) file_get_contents(app_path('Correo/TransporteMicrosoftGraph.php'));

        /** El secreto solo viaja al endpoint de token, nunca a un mensaje. */
        foreach (['TransportException(', 'Log::'] as $salida) {
            foreach (explode($salida, $codigo) as $i => $trozo) {
                if ($i === 0) {
                    continue;
                }

                $this->assertStringNotContainsString(
                    'clientSecret',
                    mb_substr($trozo, 0, 400),
                    "El secreto podria acabar en un mensaje de error (\{$salida})."
                );
            }
        }

        $this->assertSame('microsoft-graph', (string) $transporte);
    }

    /** Si no hay a quien avisar, se dice con claridad. */
    public function test_sin_destinatarios_se_informa(): void
    {
        /** Sin gerentes en esa gerencia, nadie recibe el aviso. */
        $cotizacion = $this->cotizacion(['gerencia' => '999999']);

        $resultado = NotificadorDeAprobaciones::crear()->avisar(
            cotizacion: $cotizacion,
            estado: EstadoAprobacion::EsperandoTecnica,
        );

        $this->assertTrue($resultado->noHabiaAQuienAvisar());
        $this->assertStringContainsString('No había a quién avisar', $resultado->resumen());

        Mail::assertNothingSent();
    }
}
