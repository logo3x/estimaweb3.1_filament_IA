{{--
    El flujo de aprobación y dónde está la cotización ahora.

    Tres pasos: la escribe quien cotiza, la revisa el gerente de su gerencia
    y la cierra la administración. Verlos juntos responde de un vistazo a
    "¿por qué no puedo editar esto?" y "¿a quién le toca ahora?", que es lo
    que se pregunta quien abre una cotización ajena.
--}}
<div class="fi-flujo">
    @foreach ($pasos as $paso)
        <div @class([
            'fi-flujo-paso',
            'fi-flujo-paso-hecho' => $paso['estado'] === 'hecho',
            'fi-flujo-paso-actual' => $paso['estado'] === 'actual',
            'fi-flujo-paso-rechazado' => $paso['estado'] === 'rechazado',
        ])>
            <div class="fi-flujo-marca">
                @if ($paso['estado'] === 'hecho')
                    ✓
                @elseif ($paso['estado'] === 'rechazado')
                    ✕
                @else
                    {{ $loop->iteration }}
                @endif
            </div>

            <div class="fi-flujo-texto">
                <div class="fi-flujo-titulo">{{ $paso['titulo'] }}</div>
                <div class="fi-flujo-detalle">{{ $paso['detalle'] }}</div>
            </div>
        </div>

        @unless ($loop->last)
            <div @class([
                'fi-flujo-union',
                'fi-flujo-union-hecha' => $paso['estado'] === 'hecho',
            ])></div>
        @endunless
    @endforeach
</div>
