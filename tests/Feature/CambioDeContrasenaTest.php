<?php

namespace Tests\Feature;

use App\Filament\Auth\SolicitarCambioDeContrasena;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Filament\Auth\Pages\PasswordReset\ResetPassword as PaginaDeCambio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "¿Olvidó su contraseña?" funciona de punta a punta.
 *
 * En el sistema anterior el formulario decia "le hemos enviado el enlace" y
 * el correo no llegaba nunca: la notificacion iba a una cola que nadie
 * atendia. Estas pruebas fijan que sale en el acto y que el enlace sirve.
 */
class CambioDeContrasenaTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): User
    {
        $usuario = User::factory()->create(['email' => 'olvidadiza.prueba@confipetrol.test', 'name' => 'Laura Gómez']);
        $usuario->assignRole('Creador');

        return $usuario;
    }

    public function test_el_acceso_ofrece_recuperar_la_contrasena(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('/admin/password-reset/request', escape: false);
        $this->get('/admin/password-reset/request')->assertOk();
    }

    /** LA PRUEBA CENTRAL: el correo no va a una cola que nadie atiende. */
    public function test_el_correo_sale_en_el_acto(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new RestablecerContrasena('x'));

        Notification::fake();
        $usuario = $this->usuario();

        Livewire::test(SolicitarCambioDeContrasena::class)
            ->fillForm(['email' => $usuario->email])
            ->call('request')
            ->assertNotified('Revise su correo');

        Notification::assertSentTo($usuario, RestablecerContrasena::class, function (RestablecerContrasena $aviso) use ($usuario): bool {
            $correo = $aviso->toMail($usuario);

            return str_contains($aviso->url, '/admin/password-reset/reset')
                && $correo->subject === 'Cambiar su contraseña de EstimaWeb'
                && str_contains(implode(' ', $correo->introLines), 'Confipetrol');
        });
    }

    /** Un correo sin cuenta recibe la misma respuesta: no se puede averiguar quien tiene cuenta. */
    public function test_no_revela_que_correos_tienen_cuenta(): void
    {
        Notification::fake();

        Livewire::test(SolicitarCambioDeContrasena::class)
            ->fillForm(['email' => 'nadie.existe@confipetrol.test'])
            ->call('request')
            ->assertNotified('Revise su correo');

        Notification::assertNothingSent();
    }

    /** El enlace cambia la contraseña de verdad, y sirve una sola vez. */
    public function test_el_enlace_cambia_la_contrasena(): void
    {
        $usuario = $this->usuario();
        $token = Password::broker()->createToken($usuario);

        Livewire::test(PaginaDeCambio::class, ['email' => $usuario->email, 'token' => $token])
            ->fillForm(['password' => 'NuevaClave-2026', 'passwordConfirmation' => 'NuevaClave-2026'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('NuevaClave-2026', $usuario->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($usuario->fresh(), $token), 'El enlace deberia quedar usado.');
    }
}
