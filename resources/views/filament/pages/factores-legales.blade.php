<x-filament-panels::page>
    <form wire:submit="guardar">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Guardar cambios
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
