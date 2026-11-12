<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Herramientas de la cotizacion.
 *
 * Toda la pantalla vive en ActivoRelationManager: aqui solo se declara que
 * relacion gestiona y como llamarla.
 */
class HerramientasRelationManager extends ActivoRelationManager
{
    protected static string $relationship = 'herramientas';

    protected static ?string $title = 'Herramientas';

    protected static function etiquetaSingular(): string
    {
        return 'herramientas';
    }
}
