{{--
    El rol de quien está dentro, junto a su avatar.

    En la barra superior Filament pinta el botón del perfil **solo con el
    avatar**: ni el nombre ni el rol aparecen hasta abrir el menú. Pero lo
    que una persona puede hacer aquí depende de su rol, y seis acumulan
    varios -una llega a cuatro-, así que verlo exige adivinarlo por las
    opciones que salen o no salen.

    Se muestra el de mayor autoridad, que es el que explica lo que alcanza.
    Oculto en pantallas estrechas: ahí el espacio es para el contenido.
--}}
@php
    $usuario = filament()->auth()->user();
    $rol = $usuario?->rolPrincipal();
@endphp

@if (filled($rol))
    <span class="fi-rol-barra" title="Su rol en el sistema: {{ $rol }}">
        <span class="fi-rol-barra-nombre">{{ $usuario->name }}</span>
        <span class="fi-rol-barra-chip">{{ $rol }}</span>
    </span>
@endif
