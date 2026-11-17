<?php

namespace Tests\Feature;

use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Quien cambia el precio y quien emite la oferta.
 *
 * Dos reglas que el sistema actual SI tiene y que al reconstruir se habian
 * perdido, de modo que cualquiera podia cambiar el margen -y con el, lo que
 * se le cobra al cliente- o descargar la oferta formal de la empresa.
 *
 * En el sistema actual:
 * - margen: `AiuStep::handle()` → «Solo Administradores y Gerentes pueden
 *   configurar el AIU»
 * - documentos: `AiuStep.php:284` → `->visible(fn () => self::isAdmin())`
 */
class MargenYDocumentosTest extends TestCase
{
    use DatabaseTransactions;

    private function usuarioCon(string $rol): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function puede(User $usuario, Capacidad $capacidad): bool
    {
        return (new Permisos())->puede($usuario, $capacidad);
    }

    /**
     * El margen lo cambian la administración y los gerentes.
     *
     * Son quienes responden por el precio.
     */
    #[DataProvider('quienConfiguraElMargen')]
    public function test_quien_puede_cambiar_el_margen(string $rol, bool $esperado): void
    {
        $this->assertSame(
            $esperado,
            $this->puede($this->usuarioCon($rol), Capacidad::ConfigurarElMargen),
            "El rol «{$rol}» no coincide con lo esperado para cambiar el margen.",
        );
    }

    /** @return array<string, array{string, bool}> */
    public static function quienConfiguraElMargen(): array
    {
        return [
            'super_admin puede' => ['super_admin', true],
            'Administrador puede' => ['Administrador', true],
            'Gerente puede' => ['Gerente', true],
            'Director NO' => ['Director', false],
            'Creador NO' => ['Creador', false],
        ];
    }

    /**
     * La oferta al cliente la emite la administración.
     *
     * Lleva membrete y firma del Presidente: no es un borrador interno.
     */
    #[DataProvider('quienGeneraDocumentos')]
    public function test_quien_puede_generar_la_oferta(string $rol, bool $esperado): void
    {
        $this->assertSame(
            $esperado,
            $this->puede($this->usuarioCon($rol), Capacidad::GenerarDocumentos),
            "El rol «{$rol}» no coincide con lo esperado para generar la oferta.",
        );
    }

    /** @return array<string, array{string, bool}> */
    public static function quienGeneraDocumentos(): array
    {
        return [
            'super_admin puede' => ['super_admin', true],
            'Administrador puede' => ['Administrador', true],
            'Gerente NO' => ['Gerente', false],
            'Director NO' => ['Director', false],
            'Creador NO' => ['Creador', false],
        ];
    }

    /**
     * Un Creador que además es Gerente sí puede.
     *
     * Los roles suman: seis personas reales acumulan varios.
     */
    public function test_los_roles_siguen_sumando(): void
    {
        $usuario = User::factory()->create(['id_gerencia' => '4']);
        $usuario->assignRole(['Creador', 'Gerente']);

        $this->assertTrue($this->puede($usuario, Capacidad::ConfigurarElMargen));
    }

    /** Cada capacidad nueva explica por qué bloquea. */
    public function test_las_capacidades_nuevas_se_explican(): void
    {
        foreach ([Capacidad::ConfigurarElMargen, Capacidad::GenerarDocumentos] as $capacidad) {
            $this->assertNotSame('', $capacidad->motivoDelBloqueo());
        }
    }
}
