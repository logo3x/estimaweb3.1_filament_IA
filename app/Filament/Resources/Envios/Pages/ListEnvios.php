<?php

namespace App\Filament\Resources\Envios\Pages;

use App\Dominio\Permisos\Capacidad;
use App\Filament\Resources\Envios\EnvioResource;
use Filament\Resources\Pages\ListRecords;

/**
 * La bitacora de envios, solo para consultarla.
 *
 * Sin boton de crear: los envios se registran solos al generar un documento
 * o al salir un correo.
 */
class ListEnvios extends ListRecords
{
    protected static string $resource = EnvioResource::class;

    public function getTitle(): string
    {
        return 'Envíos y documentos';
    }

    public function getSubheading(): ?string
    {
        return 'Cada oferta generada o enviada, con su fecha, quién la hizo y a quién fue.';
    }

    /** Se comprueba otra vez: una peticion de Livewire no pasa por el menu. */
    public function mount(): void
    {
        abort_unless(
            EnvioResource::canAccess(),
            403,
            Capacidad::ConsultarLaAuditoria->motivoDelBloqueo(),
        );

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
