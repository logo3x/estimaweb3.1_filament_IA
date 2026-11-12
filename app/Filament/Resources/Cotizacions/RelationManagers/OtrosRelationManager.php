<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Otros costos de la cotizacion.
 *
 * Toda la pantalla vive en RecursoSimpleRelationManager: aqui solo se declara
 * que relacion gestiona y como llamarla.
 */
class OtrosRelationManager extends RecursoSimpleRelationManager
{
    protected static string $relationship = 'otros';

    protected static ?string $title = 'Otros costos';

    protected static function etiquetaSingular(): string
    {
        return 'otros costos';
    }
}
