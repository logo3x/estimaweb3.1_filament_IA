<?php

namespace Tests\Feature;

use App\Dominio\Respaldos\Respaldos;
use App\Dominio\Respaldos\VolcadoDeLaBase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Los respaldos de la base.
 *
 * Se vuelca en PHP, sin `mysqldump`: en cPanel compartido ese binario no
 * esta garantizado y no hay terminal para comprobarlo ni para arreglarlo si
 * falla.
 *
 * Lo que de verdad importa de un respaldo no es que se cree, sino que se
 * pueda **restaurar**. Aqui se fija que el .sql contenga la estructura y
 * los datos, que es lo que lo hace restaurable.
 */
class RespaldosTest extends TestCase
{
    use DatabaseTransactions;

    /** Carpeta aparte, para no tocar los respaldos de verdad. */
    private string $temporal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporal = sys_get_temp_dir() . '/respaldo_prueba_' . uniqid() . '.sql';
    }

    protected function tearDown(): void
    {
        @unlink($this->temporal);

        parent::tearDown();
    }

    /** El volcado trae estructura y datos. */
    public function test_el_volcado_lleva_las_tablas_y_sus_datos(): void
    {
        $resumen = (new VolcadoDeLaBase())->escribirEn($this->temporal);

        $this->assertGreaterThan(40, $resumen['tablas'], 'Deberían salir todas las tablas.');
        $this->assertGreaterThan(1000, $resumen['filas'], 'Deberían salir los datos.');

        $sql = file_get_contents($this->temporal);

        $this->assertStringContainsString('CREATE TABLE `cotizaciones`', $sql);
        $this->assertStringContainsString('INSERT INTO `cotizaciones`', $sql);
    }

    /**
     * Las claves foraneas se desactivan durante la importacion.
     *
     * Sin esto el respaldo NO se puede restaurar: las tablas se crean en
     * orden alfabetico y una clave apuntaria a algo que aun no existe.
     */
    public function test_el_volcado_se_puede_importar_de_corrido(): void
    {
        (new VolcadoDeLaBase())->escribirEn($this->temporal);

        $sql = file_get_contents($this->temporal);

        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0;', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=1;', $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $sql);
    }

    /** Los valores con comillas o acentos no rompen el SQL. */
    public function test_los_textos_se_escapan(): void
    {
        (new VolcadoDeLaBase())->escribirEn($this->temporal);

        $sql = file_get_contents($this->temporal);

        /** Si el escapado fallara, el SQL tendria lineas INSERT truncadas. */
        $inserts = substr_count($sql, 'INSERT INTO');
        $finales = substr_count($sql, ");\n");

        $this->assertSame(
            $inserts,
            $finales,
            'Hay INSERT sin cerrar: algún valor rompió el escapado.',
        );
    }

    /** Un respaldo de verdad: comprimido y en la lista. */
    public function test_crear_deja_el_archivo_comprimido(): void
    {
        $respaldos = new Respaldos();

        $antes = $respaldos->listar()->count();
        $resumen = $respaldos->crear();

        $this->assertSame($antes + 1, $respaldos->listar()->count());
        $this->assertStringEndsWith('.sql.gz', $resumen['archivo']);
        $this->assertGreaterThan(0, $resumen['bytes']);

        /** Se puede descomprimir y sigue siendo SQL. */
        $ruta = $respaldos->rutaDe($resumen['archivo']);
        $this->assertNotNull($ruta);
        $this->assertStringContainsString('CREATE TABLE', (string) gzdecode(file_get_contents($ruta)));

        $respaldos->borrar($resumen['archivo']);
    }

    /**
     * No se puede pedir un archivo de fuera de la carpeta.
     *
     * El nombre llega desde la pantalla, asi que nunca se usa tal cual para
     * abrir un archivo.
     */
    public function test_no_se_sale_de_la_carpeta_de_respaldos(): void
    {
        $respaldos = new Respaldos();

        $this->assertNull($respaldos->rutaDe('../../../.env'));
        $this->assertNull($respaldos->rutaDe('../../.env'));
        $this->assertNull($respaldos->rutaDe('cualquier-cosa.sql.gz'));
    }

    /** Solo la administracion. */
    public function test_quien_puede_gestionar_respaldos(): void
    {
        $esperado = [
            'super_admin' => true,
            'Administrador' => true,
            'Gerente' => false,
            'Director' => false,
            'Creador' => false,
        ];

        foreach ($esperado as $rol => $puede) {
            $usuario = User::factory()->create(['id_gerencia' => '4']);
            $usuario->assignRole($rol);

            $this->assertSame(
                $puede,
                (new \App\Dominio\Permisos\Permisos())->puede(
                    $usuario,
                    \App\Dominio\Permisos\Capacidad::GestionarRespaldos,
                ),
                "El rol «{$rol}» no coincide con lo esperado.",
            );
        }
    }

    /** Y la pantalla rebota a quien no puede, aunque escriba la direccion. */
    public function test_la_pantalla_rebota_a_quien_no_puede(): void
    {
        $creador = User::factory()->create(['id_gerencia' => '4']);
        $creador->assignRole('Creador');

        $this->actingAs($creador)->get('/admin/respaldos')->assertForbidden();
    }

    public function test_la_administracion_si_entra(): void
    {
        $admin = User::factory()->create(['id_gerencia' => '4']);
        $admin->assignRole('Administrador');

        $this->actingAs($admin)->get('/admin/respaldos')->assertOk();
    }
}
