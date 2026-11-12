<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Equipos de la cotizacion.
 *
 * Toda la pantalla vive en ActivoRelationManager: aqui solo se declara que
 * relacion gestiona y como llamarla.
 */
class EquiposRelationManager extends ActivoRelationManager
{
    protected static string $relationship = 'equipos';

    protected static ?string $title = 'Equipos';

    protected static function etiquetaSingular(): string
    {
        return 'equipos';
    }
}
