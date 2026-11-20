{{--
    El inicio: el saludo y lo pendiente.

    Dos columnas: lo que espera una decisión de quien entra (aprobar) y lo
    suyo que necesita algo (borradores y devueltas). Si no hay nada, se dice
    con una línea: un hueco vacío no distingue "nada pendiente" de "no cargó".
--}}
<x-filament-widgets::widget>
    <div class="fi-inicio">
        <div class="fi-inicio-cabecera">
            <div>
                <p class="fi-inicio-fecha">{{ $fecha }}</p>
                <h2 class="fi-inicio-saludo">{{ $saludo }}, {{ $nombre }}</h2>
                @if (filled($gerencia))
                    <p class="fi-inicio-gerencia">{{ $gerencia }}</p>
                @endif
            </div>

            <div class="fi-inicio-acciones">
                <x-filament::button tag="a" :href="$urlListado" color="gray" icon="heroicon-o-queue-list" wire:navigate>
                    Ver cotizaciones
                </x-filament::button>
                @if ($puedeCrear)
                    <x-filament::button tag="a" :href="$urlNueva" icon="heroicon-o-plus" wire:navigate>
                        Nueva cotización
                    </x-filament::button>
                @endif
            </div>
        </div>

        <div class="fi-inicio-columnas">
            @foreach ([
                ['titulo' => 'Esperan tu decisión', 'icono' => 'heroicon-o-check-badge', 'datos' => $porDecidir, 'vacio' => 'Nada pendiente de aprobar.'],
                ['titulo' => 'Tus borradores y devueltas', 'icono' => 'heroicon-o-pencil-square', 'datos' => $mias, 'vacio' => 'No tienes borradores ni cotizaciones devueltas.'],
            ] as $bloque)
                <section class="fi-inicio-bloque">
                    <h3 class="fi-inicio-bloque-titulo">
                        <x-filament::icon :icon="$bloque['icono']" class="fi-inicio-icono" />
                        {{ $bloque['titulo'] }}
                        @if ($bloque['datos']['total'] > 0)
                            <x-filament::badge :color="$loop->first ? 'warning' : 'gray'" size="sm">{{ $bloque['datos']['total'] }}</x-filament::badge>
                        @endif
                    </h3>

                    @if ($bloque['datos']['lista'] === [])
                        <p class="fi-inicio-vacio">{{ $bloque['vacio'] }}</p>
                    @else
                        <ul class="fi-inicio-lista">
                            @foreach ($bloque['datos']['lista'] as $cot)
                                <li>
                                    <a href="{{ $cot['url'] }}" class="fi-inicio-item" wire:navigate>
                                        <span class="fi-inicio-item-texto">
                                            <span class="fi-inicio-item-codigo">{{ $cot['codigo'] }}</span>
                                            <span class="fi-inicio-item-nombre">{{ \Illuminate\Support\Str::limit($cot['nombre'], 60) }} · {{ $cot['cliente'] }}</span>
                                        </span>
                                        <x-filament::badge :color="$cot['color']" size="sm">{{ $cot['estado'] }}</x-filament::badge>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @if ($bloque['datos']['total'] > count($bloque['datos']['lista']))
                            <p class="fi-inicio-mas">y {{ $bloque['datos']['total'] - count($bloque['datos']['lista']) }} más en el listado.</p>
                        @endif
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
