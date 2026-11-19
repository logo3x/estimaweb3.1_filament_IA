{{--
    Respaldos de la base.

    Lo primero es el aviso de antigüedad: tener respaldos viejos y creer que
    están al día es la forma habitual de perder datos. Después la lista, con
    el botón de descargar bien visible, porque un respaldo que se queda en el
    servidor no sirve si se pierde el servidor.
--}}
<x-filament-panels::page>
    @php
        $aviso = $this->avisoDeAntiguedad();
        $respaldos = $this->respaldos();
    @endphp

    @if ($aviso)
        <x-filament::section
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
            heading="Atención"
        >
            <p class="fi-resp-aviso">{{ $aviso }}</p>
        </x-filament::section>
    @endif

    <x-filament::section
        icon="heroicon-o-circle-stack"
        heading="Copias disponibles"
        :description="$respaldos === []
            ? 'Todavía no hay ninguna.'
            : 'Se conservan las ' . \App\Dominio\Respaldos\Respaldos::CUANTOS_SE_GUARDAN . ' más recientes; las anteriores se borran solas.'"
    >
        @if ($respaldos === [])
            <p class="fi-resp-vacio">
                Pulse «Crear respaldo ahora» para generar la primera.
            </p>
        @else
            <table class="fi-mant-tabla fi-resp-tabla">
                <tbody>
                    @foreach ($respaldos as $respaldo)
                        <tr wire:key="respaldo-{{ $respaldo['nombre'] }}">
                            <td class="fi-resp-cuando">
                                {{ $respaldo['cuando'] }}
                                <span class="fi-resp-hace">{{ $respaldo['hace'] }}</span>
                            </td>
                            <td class="fi-resp-peso">{{ $respaldo['peso'] }}</td>
                            <td class="fi-resp-acciones">
                                <x-filament::button
                                    size="xs"
                                    icon="heroicon-m-arrow-down-tray"
                                    wire:click="descargar('{{ $respaldo['nombre'] }}')"
                                >
                                    Descargar
                                </x-filament::button>

                                <x-filament::button
                                    size="xs"
                                    color="gray"
                                    icon="heroicon-m-trash"
                                    wire:click="borrar('{{ $respaldo['nombre'] }}')"
                                    wire:confirm="¿Borrar este respaldo? No se puede deshacer."
                                >
                                    Borrar
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    {{--
        Cómo se restaura.

        Va escrito aquí porque el día que haga falta nadie va a tener tiempo
        de averiguarlo, y en cPanel no hay consola para improvisar.
    --}}
    <x-filament::section
        icon="heroicon-o-arrow-uturn-left"
        heading="Cómo restaurar una copia"
        collapsible
        collapsed
    >
        <div class="fi-mant-lista">
            <p>
                <strong>1.</strong> Descargue el archivo <code>.sql.gz</code> y
                descomprímalo: queda un <code>.sql</code>.
            </p>
            <p>
                <strong>2.</strong> Entre a <strong>phpMyAdmin</strong> desde cPanel
                y elija la base de datos de destino.
            </p>
            <p>
                <strong>3.</strong> Pestaña <strong>Importar</strong>, seleccione el
                archivo y confirme.
            </p>
            <p>
                El archivo borra y vuelve a crear cada tabla, así que la base queda
                <strong>exactamente</strong> como estaba al hacer la copia: lo
                grabado después se pierde. Si tiene dudas, impórtelo primero en una
                base nueva y compare.
            </p>
        </div>
    </x-filament::section>

    {{-- El respaldo automático, y cómo comprobar que sigue corriendo. --}}
    <x-filament::section
        icon="heroicon-o-clock"
        heading="Respaldo automático"
        collapsible
        collapsed
    >
        <div class="fi-mant-lista">
            <p>
                El respaldo diario lo lanza el <strong>cron de cPanel</strong>, que es
                la única forma de ejecutar algo periódico sin consola. La orden es:
            </p>
            <p><code>php /home/USUARIO/estimaweb/artisan respaldo:crear</code></p>
            <p>
                Si la lista de arriba no crece cada día, el cron no está corriendo:
                revíselo en cPanel, en <strong>Trabajos cron</strong>.
            </p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
