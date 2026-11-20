<?php

use App\Dominio\Suplantacion\Suplantacion;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Volver a la propia cuenta tras entrar como otro.
 *
 * Va aqui y no como accion de Filament a proposito: la vuelta tiene que
 * funcionar desde CUALQUIER pantalla del panel, incluso si la pantalla en
 * la que se esta falla. Si la unica salida dependiera del propio panel,
 * un error dejaria a quien suplanta atrapado dentro del otro usuario.
 *
 * POST porque cambia quien eres: un GET lo dispararia cualquier enlace o
 * precarga del navegador.
 */
Route::post('/volver-a-mi-cuenta', function () {
    try {
        $autor = app(Suplantacion::class)->volver();
    } catch (DomainException $e) {
        return redirect('/admin')->with('error', $e->getMessage());
    }

    \Filament\Notifications\Notification::make()
        ->success()
        ->title('Ha vuelto a su cuenta')
        ->body('De nuevo como ' . $autor->name . '.')
        ->send();

    return redirect('/admin');
})
    ->middleware(['web', 'auth'])
    ->name('suplantacion.volver');
