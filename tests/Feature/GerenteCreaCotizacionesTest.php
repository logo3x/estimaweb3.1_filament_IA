<?php

namespace Tests\Feature;

use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Un Gerente puede cotizar por si mismo.
 *
 * No siempre tiene a mano un Creador de su gerencia que le arme la
 * cotizacion, y esperar a que aparezca no es una opcion cuando hay una
 * oferta que entregar.
 *
 * El Gerente es un SUPERCONJUNTO del Creador: todo lo que aquel puede, mas
 * el margen, los catalogos y el borrado. Esta prueba fija esa relacion,
 * para que al recortar permisos del Gerente no se le quite sin querer lo
 * que necesita para cotizar.
 */
class GerenteCreaCotizacionesTest extends TestCase
{
    use DatabaseTransactions;

    private function gerente(): User
    {
        $usuario = User::factory()->create(['id_gerencia' => '8', 'email' => 'gerente.prueba.' . uniqid() . '@example.test']);
        $usuario->assignRole('Gerente');

        return $usuario;
    }

    /**
     * Una cotizacion nueva a nombre de quien se indique.
     *
     * Se clona una existente porque la tabla tiene diez columnas
     * obligatorias sin valor por defecto -codigo, cliente, alcance...- y
     * no hay factory: lo que se prueba aqui son los permisos, no el alta
     * de datos.
     */
    private function cotizacionDe(User $usuario, ?string $gerencia): Cotizacion
    {
        $nueva = Cotizacion::query()->firstOrFail()->replicate();
        $nueva->id_user = $usuario->id;
        $nueva->gerencia = $gerencia;
        $nueva->aprobacion1 = null;
        $nueva->aprobacion2 = null;
        $nueva->save();

        return $nueva;
    }

    /** Llega a la pantalla de alta. */
    public function test_el_gerente_abre_el_formulario_de_nueva_cotizacion(): void
    {
        $this->actingAs($this->gerente())
            ->get('/admin/cotizaciones/create')
            ->assertOk();
    }

    /** Y la crea de verdad. */
    public function test_el_gerente_crea_una_cotizacion(): void
    {
        $gerente = $this->gerente();
        $antes = Cotizacion::count();

        $cotizacion = $this->cotizacionDe($gerente, (string) $gerente->id_gerencia);

        $this->assertSame($antes + 1, Cotizacion::count());
        $this->assertTrue($cotizacion->puedeSerEditadaPor($gerente), 'Debe poder seguir trabajando lo que acaba de crear.');
    }

    /**
     * Lo que crea sigue siendo suyo aunque deje la gerencia vacia.
     *
     * El campo es texto libre y no se autocompleta: si al filtrar por
     * gerencia se perdiera lo propio, el Gerente crearia una cotizacion
     * que desaparece del listado al guardarla.
     */
    public function test_lo_que_crea_no_desaparece_si_la_gerencia_queda_vacia(): void
    {
        $gerente = $this->gerente();

        $cotizacion = $this->cotizacionDe($gerente, null);

        $this->assertTrue($cotizacion->puedeSerEditadaPor($gerente));

        $visibles = (new \App\Dominio\Permisos\AlcanceDeCotizaciones())
            ->aplicar(Cotizacion::query(), $gerente)
            ->pluck('id');

        $this->assertContains($cotizacion->id, $visibles->all(), 'La cotización recién creada debe seguir en su listado.');
    }

    /** Puede llevarla el mismo hasta aprobacion. */
    public function test_el_gerente_envia_a_aprobacion_lo_que_creo(): void
    {
        $gerente = $this->gerente();

        $cotizacion = $this->cotizacionDe($gerente, (string) $gerente->id_gerencia);

        $this->assertTrue(
            (new \App\Dominio\Aprobaciones\ReglasDeAprobacion())->puedeEnviarAAprobacion($cotizacion, $gerente),
        );
    }

    /**
     * El Gerente puede todo lo que puede un Creador.
     *
     * Es la relacion que importa conservar: si una capacidad se le diera
     * al Creador y no al Gerente, el de mas autoridad quedaria sin poder
     * hacer lo que hace su equipo.
     */
    public function test_el_gerente_puede_todo_lo_que_puede_un_creador(): void
    {
        $permisos = new \App\Dominio\Permisos\Permisos();

        $gerente = $this->gerente();
        $creador = User::factory()->create(['id_gerencia' => '8', 'email' => 'creador.prueba.' . uniqid() . '@example.test']);
        $creador->assignRole('Creador');

        foreach (\App\Dominio\Permisos\Capacidad::cases() as $capacidad) {
            if ($permisos->puede($creador, $capacidad)) {
                $this->assertTrue(
                    $permisos->puede($gerente, $capacidad),
                    "El Creador puede {$capacidad->name} y el Gerente no.",
                );
            }
        }
    }
}
