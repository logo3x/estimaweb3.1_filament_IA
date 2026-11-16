<?php

namespace Tests\Feature;

use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Filament\Resources\Cotizacions\CotizacionResource;
use App\Filament\Resources\Cotizacions\Pages\ListCotizacions;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cada quien ve las cotizaciones de su gerencia.
 *
 * Hasta aqui el panel mostraba las cuarenta y ocho a cualquiera que
 * entrara: un creador de una zona veia los precios y margenes de todas las
 * demas. La administracion si las ve todas, porque aprueba el segundo nivel.
 */
class AlcancePorGerenciaTest extends TestCase
{
    use DatabaseTransactions;

    private AlcanceDeCotizaciones $alcance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alcance = new AlcanceDeCotizaciones();
    }

    private function usuario(string $rol, ?string $gerencia): User
    {
        $usuario = User::factory()->create(['id_gerencia' => $gerencia]);
        $usuario->assignRole($rol);

        return $usuario;
    }

    /** Una gerencia que existe en los datos y tiene mas de una cotizacion. */
    private function gerenciaConDatos(): string
    {
        return (string) Cotizacion::query()
            ->whereNotNull('gerencia')
            ->selectRaw('gerencia, COUNT(*) as total')
            ->groupBy('gerencia')
            ->orderByDesc('total')
            ->value('gerencia');
    }

    /** Un creador solo ve las de su gerencia. */
    public function test_un_creador_solo_ve_su_gerencia(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $usuario = $this->usuario('Creador', $gerencia);

        $visibles = $this->alcance
            ->aplicar(Cotizacion::query(), $usuario)
            ->pluck('gerencia', 'id');

        $this->assertNotEmpty($visibles, 'No ve ninguna cotización de su propia gerencia.');

        foreach ($visibles as $id => $suya) {
            $this->assertSame(
                $gerencia,
                (string) $suya,
                "La cotización {$id} es de otra gerencia y se está mostrando."
            );
        }
    }

    /** Y no ve las de otra. */
    public function test_un_creador_no_ve_otras_gerencias(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $usuario = $this->usuario('Creador', $gerencia);

        $ajena = Cotizacion::query()
            ->whereNotNull('gerencia')
            ->where('gerencia', '!=', $gerencia)
            ->firstOrFail();

        $visibles = $this->alcance
            ->aplicar(Cotizacion::query(), $usuario)
            ->pluck('id');

        $this->assertNotContains($ajena->id, $visibles);
    }

    /** Un gerente tampoco: el limite no depende del rol, sino de la gerencia. */
    public function test_un_gerente_tampoco_ve_otras_gerencias(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $usuario = $this->usuario('Gerente', $gerencia);

        $ajena = Cotizacion::query()
            ->whereNotNull('gerencia')
            ->where('gerencia', '!=', $gerencia)
            ->firstOrFail();

        $this->assertFalse($this->alcance->puedeVer($ajena, $usuario));
    }

    /** La administracion las ve todas: aprueba el segundo nivel. */
    public function test_la_administracion_ve_todas(): void
    {
        $admin = $this->usuario('Administrador', '4');

        $this->assertTrue($this->alcance->veTodas($admin));
        $this->assertSame(
            Cotizacion::query()->count(),
            $this->alcance->aplicar(Cotizacion::query(), $admin)->count()
        );
    }

    /** super_admin tambien. */
    public function test_super_admin_ve_todas(): void
    {
        $this->assertTrue($this->alcance->veTodas($this->usuario('super_admin', '4')));
    }

    /** Sin sesion no se ve nada. */
    public function test_sin_usuario_no_se_ve_nada(): void
    {
        $this->assertSame(0, $this->alcance->aplicar(Cotizacion::query(), null)->count());
        $this->assertFalse($this->alcance->veTodas(null));
    }

    /**
     * Lo propio se ve siempre.
     *
     * Hay cotizaciones guardadas sin gerencia; su autor debe poder seguir
     * trabajandolas.
     */
    public function test_cada_uno_ve_lo_que_creo_aunque_no_tenga_gerencia(): void
    {
        $usuario = $this->usuario('Creador', '4');

        $propia = Cotizacion::findOrFail(55)->replicate(['id']);
        $propia->codigo = 'MIA-' . uniqid();
        $propia->gerencia = null;
        $propia->id_user = $usuario->id;
        $propia->save();

        try {
            $this->assertTrue($this->alcance->puedeVer($propia, $usuario));

            $visibles = $this->alcance->aplicar(Cotizacion::query(), $usuario)->pluck('id');
            $this->assertContains($propia->id, $visibles);
        } finally {
            $propia->delete();
        }
    }

    /**
     * Un usuario sin gerencia no hereda las cotizaciones sin gerencia.
     *
     * Hay tres asi en los datos reales. Comparar nulo con nulo se las
     * daria a cualquiera que no tuviera gerencia asignada.
     */
    public function test_un_usuario_sin_gerencia_no_ve_las_cotizaciones_sin_gerencia(): void
    {
        $usuario = $this->usuario('Creador', null);

        $huerfana = Cotizacion::query()->whereNull('gerencia')->first();
        $this->assertNotNull($huerfana, 'Ya no hay cotizaciones sin gerencia en los datos.');

        $this->assertFalse($this->alcance->puedeVer($huerfana, $usuario));
    }

    /**
     * El listado aplica el filtro de verdad.
     *
     * La regla puede estar bien y no llegar a la pantalla.
     */
    public function test_el_listado_oculta_las_de_otra_gerencia(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $this->actingAs($this->usuario('Creador', $gerencia));

        $ajena = Cotizacion::query()
            ->whereNotNull('gerencia')
            ->where('gerencia', '!=', $gerencia)
            ->firstOrFail();

        $propia = Cotizacion::query()->where('gerencia', $gerencia)->firstOrFail();

        /**
         * Contra la consulta entera, no contra la pagina: el listado
         * muestra diez por pagina y la propia puede caer en la segunda.
         */
        $alcanzables = Livewire::test(ListCotizacions::class)
            ->assertOk()
            ->instance()
            ->getFilteredTableQuery()
            ->pluck('id');

        $this->assertContains($propia->id, $alcanzables);
        $this->assertNotContains(
            $ajena->id,
            $alcanzables,
            'El listado alcanza una cotización de otra gerencia.'
        );
    }

    /**
     * No se abre por direccion lo que no se ve en el listado.
     *
     * Ocultar una fila no es proteccion: los numeros de cotizacion son
     * correlativos y se adivinan.
     */
    public function test_no_se_abre_por_url_una_de_otra_gerencia(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $usuario = $this->usuario('Creador', $gerencia);
        $this->actingAs($usuario);

        $ajena = Cotizacion::query()
            ->whereNotNull('gerencia')
            ->where('gerencia', '!=', $gerencia)
            ->firstOrFail();

        $this->assertFalse(Gate::forUser($usuario)->allows('view', $ajena));

        $this->get(CotizacionResource::getUrl('view', ['record' => $ajena]))
            ->assertNotFound();
    }

    /** Tampoco se edita la de otra gerencia. */
    public function test_no_se_edita_una_de_otra_gerencia(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $usuario = $this->usuario('Gerente', $gerencia);

        $ajena = Cotizacion::query()
            ->whereNotNull('gerencia')
            ->where('gerencia', '!=', $gerencia)
            ->whereRaw("COALESCE(aprobacion1, '') = ''")
            ->first();

        if (! $ajena) {
            $this->markTestSkipped('No hay borradores de otra gerencia en los datos.');
        }

        $this->assertFalse(Gate::forUser($usuario)->allows('update', $ajena));
    }

    /** El contador del menu cuenta solo lo que el usuario ve. */
    public function test_el_contador_del_menu_respeta_la_gerencia(): void
    {
        $gerencia = $this->gerenciaConDatos();
        $this->actingAs($this->usuario('Creador', $gerencia));

        $suyas = $this->alcance
            ->aplicar(Cotizacion::query(), auth()->user())
            ->count();

        $this->assertSame((string) $suyas, CotizacionResource::getNavigationBadge());
        $this->assertLessThan(Cotizacion::query()->count(), $suyas);
    }
}
