<?php

namespace Tests\Feature;

use App\Dominio\Mantenimiento\Mantenimiento;
use App\Dominio\Mantenimiento\TareaDeMantenimiento;
use App\Filament\Pages\Mantenimiento as PaginaDeMantenimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El mantenimiento del servidor desde el panel.
 *
 * El hosting es cPanel sin consola: lo que en cualquier otro sitio se
 * resuelve con `php artisan` aquí hay que poder dispararlo desde la propia
 * aplicación, o tras cada despliegue el sitio se queda sirviendo el código
 * viejo desde las cachés.
 *
 * Son acciones sobre el servidor entero, así que el acceso se vigila con
 * el mismo cuidado que los permisos de las cotizaciones.
 */
class MantenimientoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(string $rol): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Solo la administración total entra. */
    public function test_solo_super_admin_entra(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $this->assertTrue(PaginaDeMantenimiento::canAccess());
    }

    /**
     * Ni siquiera un Administrador.
     *
     * Estas acciones afectan al servidor entero, no a una cotización.
     */
    public function test_un_administrador_no_entra(): void
    {
        $this->actingAs($this->usuario('Administrador'));

        $this->assertFalse(PaginaDeMantenimiento::canAccess());
    }

    /** Y el resto tampoco. */
    #[DataProvider('rolesSinAcceso')]
    public function test_los_demas_roles_no_entran(string $rol): void
    {
        $this->actingAs($this->usuario($rol));

        $this->assertFalse(PaginaDeMantenimiento::canAccess());
    }

    /** @return array<string, array{string}> */
    public static function rolesSinAcceso(): array
    {
        return [
            'Administrador' => ['Administrador'],
            'Gerente' => ['Gerente'],
            'Creador' => ['Creador'],
            'Director' => ['Director'],
        ];
    }

    /**
     * No se abre escribiendo la dirección.
     *
     * Ocultarla del menú no es protección.
     */
    public function test_no_se_abre_por_url(): void
    {
        $this->actingAs($this->usuario('Administrador'));

        $this->get('/admin/mantenimiento')->assertForbidden();
    }

    /** La administración total sí la abre. */
    public function test_super_admin_abre_la_pagina(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $this->get('/admin/mantenimiento')->assertSuccessful();
    }

    /** El diagnóstico informa de todo lo que hay que vigilar. */
    public function test_el_diagnostico_cubre_lo_necesario(): void
    {
        $diagnostico = (new Mantenimiento())->diagnostico();

        foreach ([
            'Entorno',
            'Depuración',
            'Base de datos',
            'Migraciones',
            'Cachés',
            'Plantilla de la oferta',
            'Extensiones de PHP',
            'Permisos de escritura',
        ] as $punto) {
            $this->assertArrayHasKey($punto, $diagnostico, "El diagnóstico no comprueba «{$punto}».");
        }

        foreach ($diagnostico as $punto => $datos) {
            $this->assertArrayHasKey('valor', $datos, "«{$punto}» no dice su valor.");
            $this->assertArrayHasKey('bien', $datos);
            $this->assertNotSame('', $datos['nota'], "«{$punto}» no explica qué hacer.");
        }
    }

    /**
     * El diagnóstico no revienta aunque algo falte.
     *
     * Es justamente la pantalla que se usa cuando algo va mal: si fallara
     * al encontrarse un problema, no serviría para nada.
     */
    public function test_el_diagnostico_aguanta_que_falte_la_plantilla(): void
    {
        $plantilla = storage_path('app/plantillas/oferta.docx');
        $respaldo = $plantilla . '.respaldo';

        $habia = is_file($plantilla);

        if ($habia) {
            rename($plantilla, $respaldo);
        }

        try {
            $punto = (new Mantenimiento())->diagnostico()['Plantilla de la oferta'];

            $this->assertFalse($punto['bien']);
            $this->assertStringContainsString('fallan', $punto['nota']);
        } finally {
            if ($habia) {
                rename($respaldo, $plantilla);
            }
        }
    }

    /** Cada tarea dice qué hace y cuándo hace falta. */
    #[DataProvider('todasLasTareas')]
    public function test_cada_tarea_se_explica(string $tarea): void
    {
        $tarea = TareaDeMantenimiento::from($tarea);

        $this->assertNotSame('', $tarea->etiqueta());
        $this->assertNotSame('', $tarea->cuandoHaceFalta());
        $this->assertStringStartsWith('heroicon-', $tarea->icono());
        $this->assertNotEmpty($tarea->comandos(), 'La tarea no ejecuta nada.');
    }

    /** @return array<string, array{string}> */
    public static function todasLasTareas(): array
    {
        $casos = [];

        foreach (TareaDeMantenimiento::cases() as $tarea) {
            $casos[$tarea->value] = [$tarea->value];
        }

        return $casos;
    }

    /**
     * Lo que cambia la base o deja el sitio lento pide confirmación.
     *
     * Las migraciones no se deshacen solas.
     */
    public function test_las_tareas_delicadas_piden_confirmacion(): void
    {
        $this->assertTrue(TareaDeMantenimiento::AplicarMigraciones->pideConfirmacion());
        $this->assertTrue(TareaDeMantenimiento::LimpiarCaches->pideConfirmacion());

        /** Rehacer cachés es inocuo: se hace tras cada despliegue. */
        $this->assertFalse(TareaDeMantenimiento::RehacerCaches->pideConfirmacion());
    }

    /**
     * Las migraciones se aplican sin preguntar por consola.
     *
     * En producción Laravel pide confirmación y aquí no hay nadie para
     * responder: se quedaría esperando para siempre.
     */
    public function test_las_migraciones_van_con_force(): void
    {
        $comandos = TareaDeMantenimiento::AplicarMigraciones->comandos();

        [$comando, $parametros] = $comandos[0];

        $this->assertSame('migrate', $comando);
        $this->assertTrue($parametros['--force'] ?? false, 'Sin --force se quedaría esperando.');
    }

    /** Rehacer las cachés limpia antes de volver a crearlas. */
    public function test_rehacer_caches_limpia_primero(): void
    {
        $comandos = array_column(TareaDeMantenimiento::RehacerCaches->comandos(), 0);

        $this->assertSame('optimize:clear', $comandos[0], 'Debe limpiar antes de rehacer.');
        $this->assertContains('config:cache', $comandos);
        $this->assertContains('route:cache', $comandos);
    }

    /**
     * Una tarea que falla lo cuenta, no revienta.
     *
     * Esta pantalla se usa cuando algo va mal: no puede dejar al usuario
     * ante un error del servidor.
     */
    public function test_un_fallo_se_cuenta_en_vez_de_reventar(): void
    {
        /**
         * Una tarea inventada, para no ejecutar comandos de verdad.
         *
         * `optimize:clear` reinicia la configuracion del proceso y las
         * pruebas siguientes no podrian ni crear un usuario -el hasheo de
         * contraseñas deja de estar configurado-.
         */
        $resultado = \App\Dominio\Mantenimiento\ResultadoDeMantenimiento::fallo(
            TareaDeMantenimiento::AplicarMigraciones,
            'La base rechazó la conexión',
            0.5,
        );

        $this->assertFalse($resultado->salioBien);
        $this->assertStringContainsString('No se pudo', $resultado->titulo());
        $this->assertStringContainsString('rechazó', $resultado->resumen());
    }

    /** Y una que sale bien lo dice igual de claro. */
    public function test_una_tarea_correcta_lo_dice(): void
    {
        $resultado = \App\Dominio\Mantenimiento\ResultadoDeMantenimiento::exito(
            TareaDeMantenimiento::RehacerCaches,
            'Configuration cached successfully.',
            1.2,
        );

        $this->assertTrue($resultado->salioBien);
        $this->assertStringContainsString('hecho', $resultado->titulo());
    }

    /**
     * El detalle largo se recorta para el aviso.
     *
     * La salida completa queda en el registro; en pantalla solo lo justo.
     */
    public function test_el_detalle_largo_se_recorta(): void
    {
        $resultado = \App\Dominio\Mantenimiento\ResultadoDeMantenimiento::exito(
            TareaDeMantenimiento::RehacerCaches,
            str_repeat('línea de salida muy larga. ', 50),
            1.0,
        );

        $this->assertLessThanOrEqual(301, mb_strlen($resultado->resumen()));
    }

    /** La página carga y muestra sus botones. */
    public function test_la_pagina_ofrece_las_tareas(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $componente = Livewire::test(PaginaDeMantenimiento::class)->assertOk();

        foreach (TareaDeMantenimiento::cases() as $tarea) {
            $componente->assertActionExists($tarea->value);
        }
    }

    /** Y cuenta cuántas cosas hay que atender. */
    public function test_la_pagina_cuenta_los_avisos(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $pagina = Livewire::test(PaginaDeMantenimiento::class)->instance();

        $this->assertIsInt($pagina->cuantosAvisos());
        $this->assertLessThanOrEqual(count($pagina->diagnostico()), $pagina->cuantosAvisos());
    }
}
