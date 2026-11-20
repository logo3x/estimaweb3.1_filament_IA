{{--
    Estadísticas de cotizaciones.

    Arriba cuatro cifras para saber cómo vamos de un vistazo; después cómo
    se reparte en el año, por estado y por cliente; al final lo último que
    entró y lo que tiene recursos sin calcular.

    Las barras son CSS: no hace falta una librería de gráficas para doce
    columnas, y así no se descarga nada más.
--}}
<x-filament-panels::page>
    @php
        $c = $this->cifras();
        $conteos = $c['conteos'];
        $valores = $c['valores'];
        $aprobacion = $c['aprobacion'];
        $maxMes = max(1, ...array_column($c['por_mes'], 'valor'));
        $maxCliente = max(1, ...(array_column($c['clientes'], 'valor') ?: [1]));
        $totalEstados = max(1, array_sum(array_column($c['por_estado'], 'cantidad')));
    @endphp

    {{-- Cuatro cifras. --}}
    <div class="fi-est-tarjetas">
        <div class="fi-est-tarjeta">
            <p class="fi-est-etiqueta">Cotizaciones</p>
            <p class="fi-est-cifra">{{ number_format($conteos['total'], 0, ',', '.') }}</p>
            <p class="fi-est-nota">
                {{ $conteos['mes'] }} este mes
                @if ($conteos['variacion_mes'] !== null)
                    <span @class([
                        'fi-est-sube' => $conteos['variacion_mes'] > 0,
                        'fi-est-baja' => $conteos['variacion_mes'] < 0,
                    ])>
                        ({{ $conteos['variacion_mes'] > 0 ? '+' : '' }}{{ number_format($conteos['variacion_mes'], 0, ',', '.') }} % frente a {{ $conteos['mes_anterior'] }} del mes anterior)
                    </span>
                @endif
            </p>
        </div>

        <div class="fi-est-tarjeta">
            <p class="fi-est-etiqueta">Valor cotizado</p>
            <p class="fi-est-cifra" title="{{ $this->pesos($valores['total']) }}">{{ $this->pesosCortos($valores['total']) }}</p>
            <p class="fi-est-nota">Promedio por cotización: {{ $this->pesosCortos($valores['promedio']) }}</p>
        </div>

        <div class="fi-est-tarjeta">
            <p class="fi-est-etiqueta">Este año</p>
            <p class="fi-est-cifra" title="{{ $this->pesos($valores['anio']) }}">{{ $this->pesosCortos($valores['anio']) }}</p>
            <p class="fi-est-nota">{{ $conteos['anio'] }} cotizaciones · este mes {{ $this->pesosCortos($valores['mes']) }}</p>
        </div>

        <div class="fi-est-tarjeta">
            <p class="fi-est-etiqueta">Tasa de aprobación</p>
            <p class="fi-est-cifra">
                {{ $aprobacion['tasa'] === null ? '—' : number_format($aprobacion['tasa'], 0, ',', '.') . ' %' }}
            </p>
            <p class="fi-est-nota">
                {{ $aprobacion['aprobadas'] }} aprobadas · {{ $aprobacion['rechazadas'] }} devueltas · {{ $aprobacion['en_tramite'] }} en trámite
            </p>
        </div>
    </div>

    {{-- El año, mes a mes. --}}
    <x-filament::section
        icon="heroicon-o-calendar-days"
        :heading="'Valor cotizado en ' . now()->year"
        description="Cada barra es el valor de las cotizaciones creadas ese mes; encima, cuántas fueron."
    >
        <div class="fi-est-meses" role="list">
            @foreach ($c['por_mes'] as $i => $mes)
                <div class="fi-est-mes" role="listitem" title="{{ $mes['mes'] }}: {{ $mes['cantidad'] }} cotizaciones, {{ $this->pesos($mes['valor']) }}">
                    <span class="fi-est-mes-n">{{ $mes['cantidad'] ?: '' }}</span>
                    <span class="fi-est-mes-barra-ctn">
                        <span
                            class="fi-est-mes-barra"
                            style="height: {{ round($mes['valor'] / $maxMes * 100, 1) }}%; animation-delay: {{ $i * 40 }}ms"
                        ></span>
                    </span>
                    <span class="fi-est-mes-nombre">{{ $mes['mes'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <div class="fi-est-columnas">
        {{-- Por estado. --}}
        <x-filament::section
            icon="heroicon-o-check-badge"
            heading="Por estado de aprobación"
            description="Según el flujo de aprobación, no la columna de estado del sistema anterior."
        >
            <div class="fi-est-filas">
                @foreach ($c['por_estado'] as $estado)
                    <div class="fi-est-fila">
                        <div class="fi-est-fila-cab">
                            <x-filament::badge :color="$estado['color']">{{ $estado['etiqueta'] }}</x-filament::badge>
                            <span class="fi-est-fila-valor">{{ $estado['cantidad'] }} · {{ $this->pesosCortos($estado['valor']) }}</span>
                        </div>
                        <span class="fi-est-barra-ctn">
                            <span class="fi-est-barra fi-est-barra-{{ $estado['color'] }}" style="width: {{ round($estado['cantidad'] / $totalEstados * 100, 1) }}%"></span>
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- Por cliente. --}}
        <x-filament::section
            icon="heroicon-o-building-office-2"
            heading="Clientes con más valor cotizado"
            description="Los cinco primeros, por valor: cinco ofertas pequeñas pesan menos que una grande."
        >
            @if ($c['clientes'] === [])
                <p class="fi-est-vacio">Todavía no hay cotizaciones.</p>
            @else
                <div class="fi-est-filas">
                    @foreach ($c['clientes'] as $cliente)
                        <div class="fi-est-fila">
                            <div class="fi-est-fila-cab">
                                <span class="fi-est-fila-nombre">{{ $cliente['cliente'] }}</span>
                                <span class="fi-est-fila-valor">{{ $this->pesosCortos($cliente['valor']) }} · {{ $cliente['cantidad'] }} {{ $cliente['cantidad'] === 1 ? 'cotización' : 'cotizaciones' }}</span>
                            </div>
                            <span class="fi-est-barra-ctn">
                                <span class="fi-est-barra" style="width: {{ round($cliente['valor'] / $maxCliente * 100, 1) }}%"></span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Lo último que entró. --}}
    <x-filament::section icon="heroicon-o-clock" heading="Últimas cotizaciones">
        <table class="fi-mant-tabla fi-est-tabla">
            <tbody>
                @foreach ($c['recientes'] as $cot)
                    <tr>
                        <td>
                            <a href="{{ $this->urlDe($cot['id']) }}" class="fi-est-enlace" wire:navigate>{{ $cot['codigo'] }}</a>
                            <span class="fi-est-sub">{{ \Illuminate\Support\Str::limit($cot['nombre'], 70) }}</span>
                        </td>
                        <td>
                            <span class="fi-est-fila-nombre">{{ $cot['cliente'] }}</span>
                            <span class="fi-est-sub">{{ $cot['fecha'] }}</span>
                        </td>
                        <td><x-filament::badge :color="$cot['color']">{{ $cot['estado'] }}</x-filament::badge></td>
                        <td class="fi-est-dinero">{{ $this->pesos($cot['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    {{-- Lo que el cálculo no pudo cobrar entero. --}}
    @if ($c['con_avisos'] !== [])
        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            heading="Cotizaciones con recursos que no se pudieron calcular"
            description="Su precio puede estar por debajo del real: tienen unidades no reconocidas o componentes con cantidad negativa. El detalle está en «Precio resultante», en la pestaña de AIU."
            collapsible
        >
            <div class="fi-est-avisos">
                @foreach ($c['con_avisos'] as $aviso)
                    <a href="{{ $this->urlDe($aviso['id']) }}" class="fi-est-aviso" wire:navigate>
                        {{ $aviso['codigo'] }}
                        <x-filament::badge color="warning" size="sm">{{ $aviso['avisos'] }}</x-filament::badge>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <p class="fi-est-pie">Calculado el {{ $c['calculado'] }}.</p>
</x-filament-panels::page>
