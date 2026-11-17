{{--
    El flujo de aprobación y dónde está la cotización ahora.

    Tres pasos: la escribe quien cotiza, la revisa el gerente de su gerencia
    y la cierra la administración. Verlos juntos responde de un vistazo a
    "¿por qué no puedo editar esto?" y "¿a quién le toca ahora?", que es lo
    que se pregunta quien abre una cotización ajena.

    Se muestran dos datos que estaban en la base y antes no se veían: cuándo
    se tomó cada decisión y qué escribió quien la tomó. Ese comentario suele
    ser la razón por la que algo volvió atrás.
--}}
@php
    /** Cuando le toca a quien mira, la pantalla se lo dice en primera persona. */
    $esSuTurno = $leTocaAlQueMira ?? false;
@endphp

<div class="fi-flujo-caja">
    {{-- Lo primero: en qué punto está y quién tiene la pelota. --}}
    <div @class(['fi-flujo-cabecera', 'fi-flujo-cabecera-turno' => $esSuTurno])>
        <div class="fi-flujo-estado">
            <span class="fi-flujo-chip fi-flujo-chip-{{ $color }}">{{ $etiqueta }}</span>
            <span class="fi-flujo-resumen">{{ $resumen }}</span>
        </div>

        @if ($esSuTurno)
            <div class="fi-flujo-turno">
                <x-filament::icon icon="heroicon-m-hand-raised" class="fi-flujo-turno-icono" />
                Le toca a usted decidir
            </div>
        @elseif ($aQuienLeToca !== '')
            <div class="fi-flujo-espera">{{ $aQuienLeToca }}</div>
        @endif
    </div>

    {{-- Los tres pasos, en orden. --}}
    <ol class="fi-flujo">
        @foreach ($pasos as $paso)
            <li @class([
                'fi-flujo-paso',
                'fi-flujo-paso-hecho' => $paso->estaHecho(),
                'fi-flujo-paso-actual' => $paso->esElActual(),
                'fi-flujo-paso-rechazado' => $paso->fueRechazado(),
            ])>
                <div class="fi-flujo-marca" aria-hidden="true">{{ $paso->marca($loop->iteration) }}</div>

                <div class="fi-flujo-texto">
                    <div class="fi-flujo-titulo">
                        {{ $paso->titulo }}

                        @if ($paso->cuando !== '')
                            <span class="fi-flujo-cuando">{{ $paso->cuando }}</span>
                        @endif
                    </div>

                    <div class="fi-flujo-detalle">{{ $paso->quien }}</div>

                    @if ($paso->queFalta !== '')
                        <div class="fi-flujo-falta">{{ $paso->queFalta }}</div>
                    @endif

                    {{--
                        Lo que escribió quien decidió.

                        Casi siempre es el motivo de una devolución, así que
                        es justo lo que hay que leer para saber qué corregir.
                    --}}
                    @if ($paso->comentario !== '')
                        <blockquote class="fi-flujo-nota">{{ $paso->comentario }}</blockquote>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
