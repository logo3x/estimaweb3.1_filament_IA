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

    protected static function claseDeCaja(): string
    {
        return \App\Models\PrecargadoHerramienta::class;
    }

    protected static function claseDeCatalogo(): string
    {
        return \App\Models\DatosHerramienta::class;
    }

    protected static function claseDeElemento(): string
    {
        return \App\Models\ItemHerramienta::class;
    }

    protected static function columnaDelGrupo(): string
    {
        return 'id_herramienta';
    }

    protected static function columnaDelCatalogo(): string
    {
        return 'id_datos_herramientas';
    }
}
