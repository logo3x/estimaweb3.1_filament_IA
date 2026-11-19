<?php

namespace App\Filament\Resources\Auditorias\Pages;

use App\Dominio\Permisos\Capacidad;
use App\Filament\Resources\Auditorias\AuditoriaResource;
use Filament\Resources\Pages\ListRecords;

/**
 * La auditoria, solo para consultarla.
 *
 * Sin boton de crear ni de borrar: una bitacora que se puede alterar no
 * sirve como evidencia.
 */
class ListAuditoria extends ListRecords
{
    protected static string $resource = AuditoriaResource::class;

    public function getTitle(): string
    {
        return 'Auditoría';
    }

    public function getSubheading(): ?string
    {
        return 'Quién cambió qué y cuándo. No se puede editar ni borrar: es el respaldo de lo ocurrido.';
    }

    /** Se comprueba otra vez: una peticion de Livewire no pasa por el menu. */
    public function mount(): void
    {
        abort_unless(
            AuditoriaResource::canAccess(),
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
