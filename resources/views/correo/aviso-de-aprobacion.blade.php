{{--
    Aviso del flujo de aprobacion.

    Una sola plantilla para las cinco transiciones: lo que cambia viene
    resuelto desde AvisoDeAprobacion. El sistema actual tiene cinco
    plantillas que repiten los mismos estilos escritos a mano.

    Se usa el Markdown de Laravel en vez de HTML propio porque sus estilos
    ya estan probados en los clientes de correo, que son mucho menos
    tolerantes que un navegador.
--}}
<x-mail::message>
# {{ $titular }}

{{ $explicacion }}

<x-mail::panel>
**{{ $cotizacion->codigo ?: 'Sin código' }}**

{{ $cotizacion->nombre ?: 'Sin descripción del servicio' }}

Cliente: {{ $cotizacion->cliente ?: 'Sin cliente' }}
</x-mail::panel>

@if ($autorDeLaAccion)
{{--
    Quien tomo la decision: es lo primero que se pregunta quien lo recibe.
    Se redacta como accion y no con la etiqueta del estado, que daria
    frases torpes del tipo "Esperando aprobacion tecnica por Fulano".
--}}
{{ $accionDelAutor }} por **{{ $autorDeLaAccion->name }}** el {{ now()->translatedFormat('d \d\e F \d\e Y') }}.
@endif

@if (filled($comentarios))
{{--
    Los comentarios son el motivo de un rechazo: sin ellos, quien recibe el
    aviso no sabe que corregir.
--}}
**Comentarios**

> {{ $comentarios }}
@endif

<x-mail::button :url="$urlDeLaCotizacion">
Ver la cotización
</x-mail::button>

@if (! $paraElAutor && $estado->estaEnTramite())
Mientras esté en este estado, solo usted puede modificarla.
@endif

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
