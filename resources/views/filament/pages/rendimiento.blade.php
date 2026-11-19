{{--
    Qué pantallas van lentas.

    Primero cuatro números para saber si hay algo que mirar, después las
    sospechosas de N+1 -que es el problema que crece con los datos- y al
    final la lista completa.
--}}
<x-filament-panels::page>
    @php
        $resumen = $this->resumen();
        $sospechosas = $this->sospechosas();
        $rutas = $this->rutasMasLentas();
    @endphp

    <x-filament::section
        :icon="$resumen['lentas'] === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-bolt'"
        :icon-color="$resumen['lentas'] === 0 ? 'success' : 'warning'"
        :heading="$resumen['lentas'] === 0 ? 'Nada va lento' : 'Hay ' . number_format($resumen['lentas'], 0, ',', '.') . ' peticiones lentas'"
        :description="'Se guardan los últimos ' . $this->diasQueSeGuardan() . ' días.'"
    >
        @if ($resumen['lentas'] === 0)
            <p class="fi-rend-vacio">
                Ninguna petición ha pasado de medio segundo. Es lo que debe salir
                aquí: la tabla solo se llena cuando algo va mal.
            </p>
        @else
            <table class="fi-mant-tabla">
                <tbody>
                    <tr>
                        <td class="fi-mant-que">Peticiones lentas</td>
                        <td class="fi-mant-valor">{{ number_format($resumen['lentas'], 0, ',', '.') }}</td>
                        <td class="fi-mant-nota">Por encima de medio segundo</td>
                    </tr>
                    <tr>
                        <td class="fi-mant-que">La peor</td>
                        <td class="fi-mant-valor">{{ number_format($resumen['peor_ms'] / 1000, 1, ',', '.') }} s</td>
                        <td class="fi-mant-nota">Lo que tardó la más lenta de todas</td>
                    </tr>
                    <tr @class(['fi-mant-aviso' => $resumen['con_muchas_consultas'] > 0])>
                        <td class="fi-mant-que">Con muchas consultas</td>
                        <td class="fi-mant-valor">{{ $resumen['con_muchas_consultas'] }}</td>
                        <td class="fi-mant-nota">Más de {{ $this->umbralDeConsultas() }} consultas: suele ser un N+1</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </x-filament::section>

    {{-- Lo que de verdad hay que arreglar. --}}
    @if ($sospechosas !== [])
        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="danger"
            heading="Pantallas con demasiadas consultas"
            description="Estas son las que conviene revisar primero: su coste crece con los datos."
        >
            <table class="fi-mant-tabla">
                <tbody>
                    @foreach ($sospechosas as $fila)
                        <tr class="fi-mant-aviso">
                            <td class="fi-rend-ruta">{{ $fila->ruta }}</td>
                            <td class="fi-rend-cifra">{{ $fila->consultas }} consultas</td>
                            <td class="fi-rend-cifra">{{ number_format($fila->media_ms / 1000, 1, ',', '.') }} s</td>
                            <td class="fi-mant-nota">{{ $fila->veces }} vez/veces</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    @if ($rutas !== [])
        <x-filament::section
            icon="heroicon-o-clock"
            heading="Las más lentas"
            description="Agrupadas por pantalla: importa si siempre va lenta, no que una vez tardara."
            collapsible
        >
            <table class="fi-mant-tabla fi-rend-tabla">
                <thead>
                    <tr>
                        <th>Pantalla</th>
                        <th>Media</th>
                        <th>Peor</th>
                        <th>Consultas</th>
                        <th>Veces</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rutas as $fila)
                        <tr>
                            <td class="fi-rend-ruta">
                                {{ $fila->ruta }}
                                @if ($fila->es_livewire)
                                    <span class="fi-rend-etiqueta">Livewire</span>
                                @endif
                            </td>
                            <td class="fi-rend-cifra">{{ number_format($fila->media_ms / 1000, 1, ',', '.') }} s</td>
                            <td class="fi-rend-cifra">{{ number_format($fila->peor_ms / 1000, 1, ',', '.') }} s</td>
                            <td @class(['fi-rend-cifra', 'fi-mant-marca' => $fila->peor_consultas >= $this->umbralDeConsultas()])>
                                {{ $fila->media_consultas }}
                            </td>
                            <td class="fi-rend-cifra">{{ $fila->veces }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    <x-filament::section
        icon="heroicon-o-information-circle"
        heading="Cómo se lee esto"
        collapsible
        collapsed
    >
        <div class="fi-mant-lista">
            <p>
                Solo se anotan las peticiones que pasan de <strong>medio segundo</strong>.
                Una tabla vacía es buena señal.
            </p>
            <p>
                El número que más importa es el de <strong>consultas</strong>. Si una
                pantalla hace más de {{ $this->umbralDeConsultas() }}, lo normal es que
                esté repitiendo la misma consulta por cada fila (un «N+1»): irá bien con
                pocos datos y se degradará según crezca la base.
            </p>
            <p>
                Para medir a fondo una pantalla concreta hay un comando:
                <code>php artisan rendimiento:medir</code>.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
