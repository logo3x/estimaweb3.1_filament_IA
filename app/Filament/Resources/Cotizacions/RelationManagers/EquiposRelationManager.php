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

    protected static function claseDeCaja(): string
    {
        return \App\Models\PrecargadoEquipo::class;
    }

    protected static function claseDeCatalogo(): string
    {
        return \App\Models\DatosEquipo::class;
    }

    protected static function claseDeElemento(): string
    {
        return \App\Models\ItemEquipo::class;
    }

    protected static function columnaDelGrupo(): string
    {
        return 'id_equipo';
    }

    protected static function columnaDelCatalogo(): string
    {
        return 'id_datos_equipo';
    }
}
