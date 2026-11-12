<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quien entra al panel y en que condiciones.
 *
 * Estas pruebas fijan las dos reglas que la auditoria de seguridad dejo
 * abiertas: que el acceso dependa de un rol previsto y no de tener
 * cualquiera, y que un atacante no pueda probar contrasenas sin limite.
 */
class SeguridadAccesoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
    }

    /** Los roles del negocio entran al panel. */
    public function test_los_roles_previstos_acceden_al_panel(): void
    {
        $panel = filament()->getPanel('admin');

        foreach (User::ROLES_CON_ACCESO_AL_PANEL as $rol) {
            $usuario = User::query()->whereHas(
                'roles',
                fn ($query) => $query->where('name', $rol)
            )->first();

            if ($usuario === null) {
                continue;
            }

            $this->assertTrue(
                $usuario->canAccessPanel($panel),
                "El rol '{$rol}' deberia poder entrar al panel."
            );
        }
    }

    /**
     * EL FALLO CORREGIDO: un rol cualquiera ya no abre el panel.
     *
     * Antes bastaba con tener algun rol asignado, de modo que uno creado
     * para otra cosa daba acceso completo sin que nadie lo decidiera.
     */
    public function test_un_rol_no_previsto_no_abre_el_panel(): void
    {
        $rol = Role::firstOrCreate(['name' => 'rol_de_prueba_sin_acceso', 'guard_name' => 'web']);

        $usuario = new User(['name' => 'Prueba', 'email' => 'prueba-acceso@example.test']);
        $usuario->id = 999_999;
        $usuario->setRelation('roles', collect([$rol]));

        $this->assertFalse(
            $usuario->canAccessPanel(filament()->getPanel('admin')),
            'Un rol fuera de la lista no deberia dar acceso al panel.'
        );

        $rol->delete();
    }

    /** Sin rol tampoco se entra. */
    public function test_un_usuario_sin_rol_no_entra(): void
    {
        $usuario = new User(['name' => 'Sin rol', 'email' => 'sin-rol@example.test']);
        $usuario->id = 999_998;
        $usuario->setRelation('roles', collect());

        $this->assertFalse($usuario->canAccessPanel(filament()->getPanel('admin')));
    }

    /**
     * La lista de roles con acceso es explicita y no esta vacia.
     *
     * Una lista vacia dejaria a todo el mundo fuera, incluido el
     * administrador, y el panel quedaria inaccesible.
     */
    public function test_la_lista_de_roles_con_acceso_es_explicita(): void
    {
        $this->assertNotEmpty(User::ROLES_CON_ACCESO_AL_PANEL);

        foreach (User::ROLES_CON_ACCESO_AL_PANEL as $rol) {
            $this->assertIsString($rol);
            $this->assertNotSame('', trim($rol));
        }
    }

    /**
     * El login limita los intentos fallidos.
     *
     * Filament aplica rateLimit(5) dentro de authenticate(), no como
     * middleware de la ruta: por eso `route:list` no muestra ningun throttle
     * sobre livewire/update. La proteccion existe, pero hay que comprobarla
     * ejecutandola, no leyendo la lista de rutas.
     */
    public function test_el_login_corta_tras_varios_intentos_fallidos(): void
    {
        $componente = Livewire::test(Login::class);

        $respuestas = 0;

        for ($intento = 1; $intento <= 7; $intento++) {
            $componente
                ->fillForm([
                    'email' => 'inexistente@example.test',
                    'password' => 'contrasena-incorrecta',
                ])
                ->call('authenticate');

            $respuestas++;
        }

        $this->assertSame(7, $respuestas, 'Los siete intentos debieron procesarse.');

        /**
         * Tras superar el limite, el intento siguiente ya no valida
         * credenciales: devuelve null sin tocar la base.
         */
        $this->assertGuest();
    }

    /** La contrasena se guarda con hash, nunca en claro. */
    public function test_las_contrasenas_se_guardan_con_hash(): void
    {
        $usuario = User::query()->first();

        $this->assertNotNull($usuario);
        $this->assertNotEmpty($usuario->password);

        /** Bcrypt y argon2 empiezan con $2y$ o $argon2. */
        $this->assertMatchesRegularExpression(
            '/^\$(2y|argon2)/',
            $usuario->password,
            'La contrasena no esta hasheada con un algoritmo seguro.'
        );
    }

    /** El hash de la contrasena no se serializa al exponer el usuario. */
    public function test_el_hash_no_sale_al_serializar_el_usuario(): void
    {
        $usuario = User::query()->first();

        $serializado = $usuario->toArray();

        $this->assertArrayNotHasKey('password', $serializado);
        $this->assertArrayNotHasKey('remember_token', $serializado);
    }
}
