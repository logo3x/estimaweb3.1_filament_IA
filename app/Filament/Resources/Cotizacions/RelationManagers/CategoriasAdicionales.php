<?php

namespace App\Filament\Resources\Cotizacions\RelationManagers;

use Filament\Resources\RelationManagers\RelationGroup;

/**
 * Las siete categorias que no son personal ni activos.
 *
 * Vehiculos, oficinas, aseo, viaticos, servicios, comunicaciones y otros
 * costos van juntas, como en el sistema actual: alli es la pestaña
 * "Categorias Adicionales".
 *
 * Sueltas ocupaban siete de las catorce pestañas de la cotizacion, y son
 * justo las que menos se tocan: la barra quedaba dominada por lo accesorio.
 *
 * Dentro, cada una va en una **seccion plegada**. Abiertas son siete tablas
 * apiladas y la pagina se vuelve interminable; plegadas se ven los siete
 * titulos de un vistazo y se abre solo el que hace falta.
 */
final class CategoriasAdicionales
{
    /**
     * Como se titula cada categoria en su seccion.
     *
     * @var array<class-string, array{string, string}>
     */
    private const TITULOS = [
        VehiculosRelationManager::class => ['Vehículos', 'heroicon-o-truck'],
        OficinasRelationManager::class => ['Oficinas, baños y contenedores', 'heroicon-o-building-office'],
        AseosRelationManager::class => ['Aseo, papelería y cafetería', 'heroicon-o-sparkles'],
        ViaticosRelationManager::class => ['Viáticos', 'heroicon-o-map'],
        ServiciosRelationManager::class => ['Servicios especializados', 'heroicon-o-briefcase'],
        ComunicacionesRelationManager::class => ['Comunicaciones', 'heroicon-o-signal'],
        OtrosRelationManager::class => ['Otros costos', 'heroicon-o-ellipsis-horizontal-circle'],
    ];

    /**
     * El grupo, listo para registrarlo entre las relaciones.
     *
     * El orden es el del sistema actual, que es el que conoce quien cotiza.
     */
    /** El grupo, listo para registrarlo entre las relaciones. */
    public static function grupo(): RelationGroup
    {
        return RelationGroup::make('Categorías adicionales', array_keys(self::TITULOS))
            ->icon('heroicon-o-squares-2x2');
    }

    /**
     * El titulo y el icono de una categoria por su posicion.
     *
     * @return array{string, string}
     */
    public static function etiqueta(int $posicion): array
    {
        return array_values(self::TITULOS)[$posicion]
            ?? ['Categoría', 'heroicon-o-squares-2x2'];
    }
}
