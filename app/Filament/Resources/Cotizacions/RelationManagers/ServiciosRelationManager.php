<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Servicios de la cotizacion.
 *
 * Toda la pantalla vive en RecursoSimpleRelationManager: aqui solo se declara
 * que relacion gestiona y como llamarla.
 */
class ServiciosRelationManager extends RecursoSimpleRelationManager
{
    protected static string $relationship = 'servicios';

    protected static ?string $title = 'Servicios';

    protected static function etiquetaSingular(): string
    {
        return 'servicios';
    }
}
