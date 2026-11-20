{{--
    El aviso de que se está actuando como otra persona.

    Es la pieza que hace segura la suplantación: sin un camino de vuelta
    visible en TODAS las pantallas, quien entra como otro queda atrapado y
    la única salida sería borrar su sesión a mano — y en el servidor no hay
    consola para eso.

    Se pinta en ámbar y ocupando el ancho porque lo peligroso no es entrar,
    sino olvidar que se entró: cualquier cosa que se haga quedará a nombre
    del suplantado.
--}}
@php
    $suplantacion = app(\App\Dominio\Suplantacion\Suplantacion::class);
    $autor = $suplantacion->estaEnCurso() ? $suplantacion->quienSuplanta() : null;
@endphp

@if ($autor)
    <div class="fi-suplantacion" role="status">
        <div class="fi-suplantacion-texto">
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="fi-suplantacion-icono" />

            <span>
                Está viendo el panel como
                <strong>{{ filament()->auth()->user()?->name }}</strong>.
                Lo que haga quedará a su nombre.
            </span>
        </div>

        <form method="POST" action="{{ route('suplantacion.volver') }}">
            @csrf

            <button type="submit" class="fi-suplantacion-boton">
                Volver a mi cuenta ({{ $autor->name }})
            </button>
        </form>
    </div>
@endif
