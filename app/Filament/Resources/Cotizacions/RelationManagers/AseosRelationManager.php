<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Elementos de aseo de la cotizacion.
 *
 * Toda la pantalla vive en RecursoSimpleRelationManager: aqui solo se declara
 * que relacion gestiona y como llamarla.
 */
class AseosRelationManager extends RecursoSimpleRelationManager
{
    protected static string $relationship = 'aseos';

    protected static ?string $title = 'Elementos de aseo';

    protected static function etiquetaSingular(): string
    {
        return 'elementos de aseo';
    }
}
