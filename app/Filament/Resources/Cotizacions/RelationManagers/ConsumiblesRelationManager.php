<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

/**
 * Consumibles de la cotizacion.
 *
 * Toda la pantalla vive en ActivoRelationManager: aqui solo se declara que
 * relacion gestiona y como llamarla.
 */
class ConsumiblesRelationManager extends ActivoRelationManager
{
    protected static string $relationship = 'consumibles';

    protected static ?string $title = 'Consumibles';

    protected static function etiquetaSingular(): string
    {
        return 'consumibles';
    }

    /**
     * En consumibles reposicion y calibracion son opcionales: en produccion
     * esas columnas vienen vacias.
     */
    protected static function exigeValores(): bool
    {
        return false;
    }

    protected static function claseDeCaja(): string
    {
        return \App\Models\PrecargadoConsumible::class;
    }

    protected static function claseDeCatalogo(): string
    {
        return \App\Models\DatosConsumible::class;
    }

    protected static function claseDeElemento(): string
    {
        return \App\Models\ItemConsumible::class;
    }

    protected static function columnaDelGrupo(): string
    {
        return 'id_consumibles';
    }

    protected static function columnaDelCatalogo(): string
    {
        return 'id_datos_consumibles';
    }
}
