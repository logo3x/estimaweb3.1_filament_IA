<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Viáticos de la cotizacion.
 *
 * Toda la pantalla vive en RecursoSimpleRelationManager: aqui solo se declara
 * que relacion gestiona y como llamarla.
 */
class ViaticosRelationManager extends RecursoSimpleRelationManager
{
    protected static string $relationship = 'viaticos';

    protected static ?string $title = 'Viáticos';

    protected static function etiquetaSingular(): string
    {
        return 'viáticos';
    }
}
