{{--
    Mantenimiento del servidor.

    Primero el diagnóstico -lo que se mira cuando algo falla y no hay
    consola- y después qué hace cada botón de arriba, porque quien los
    pulsa no tiene por qué saberlo de memoria.
--}}
<x-filament-panels::page>
    @php
        $avisos = $this->cuantosAvisos();

        $titular = match (true) {
            $avisos === 0 => 'Todo en orden',
            $avisos === 1 => 'Hay 1 cosa que atender',
            default => "Hay {$avisos} cosas que atender",
        };
    @endphp

    {{-- Lo primero: si hay algo que atender. --}}
    <x-filament::section
        :icon="$avisos > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'"
        :icon-color="$avisos > 0 ? 'warning' : 'success'"
        :heading="$titular"
        :description="$avisos > 0 ? 'Mire los puntos marcados en ámbar.' : 'El servidor está como debe estar.'"
    >
        <table class="fi-mant-tabla">
            <tbody>
                @foreach ($this->diagnostico() as $que => $punto)
                    <tr @class(['fi-mant-aviso' => ! $punto['bien']])>
                        <td class="fi-mant-marca">{{ $punto['bien'] ? '✓' : '!' }}</td>
                        <td class="fi-mant-que">{{ $que }}</td>
                        <td class="fi-mant-valor">{{ $punto['valor'] }}</td>
                        <td class="fi-mant-nota">{{ $punto['nota'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    {{-- Qué hace cada botón de arriba. --}}
    <x-filament::section
        icon="heroicon-o-information-circle"
        heading="Qué hace cada botón"
        description="Los de arriba, en la cabecera."
        collapsible
        collapsed
    >
        <table class="fi-mant-tabla">
            <tbody>
                @foreach ($this->tareas() as $tarea)
                    <tr>
                        <td class="fi-mant-que">{{ $tarea->etiqueta() }}</td>
                        <td class="fi-mant-nota">{{ $tarea->cuandoHaceFalta() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    {{--
        Lo que no se puede hacer desde aquí.

        Conviene decirlo: si alguien busca cómo subir la plantilla o cómo
        poner el repositorio en privado, que sepa que no es por aquí.
    --}}
    <x-filament::section
        icon="heroicon-o-hand-raised"
        heading="Lo que hay que hacer a mano"
        description="Desde el panel de cPanel, no desde aquí."
        collapsible
        collapsed
    >
        <ul class="fi-mant-lista">
            <li>
                <strong>Desplegar el código:</strong> en cPanel, Git Version
                Control → «Update from Remote» y después «Deploy HEAD Commit».
                Al terminar, vuelva aquí y pulse «Rehacer las cachés».
            </li>
            <li>
                <strong>Subir la plantilla de la oferta:</strong> por el gestor
                de archivos, a <code>storage/app/plantillas/oferta.docx</code>.
                Lleva el membrete de la empresa, así que no viaja en el código.
            </li>
            <li>
                <strong>Cambiar la configuración:</strong> el archivo
                <code>.env</code> del servidor, también por el gestor de
                archivos. Nunca viaja en el código.
            </li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
