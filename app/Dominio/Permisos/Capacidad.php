<?php

namespace App\Dominio\Permisos;

/**
 * Lo que un usuario puede hacer en el panel, por area.
 *
 * Hasta aqui el sistema solo tenia una puerta: quien entraba al panel
 * entraba a todo. Los diecisiete usuarios con rol Creador podian borrar
 * usuarios y cambiar los factores legales, que mueven el precio de TODAS
 * las cotizaciones.
 *
 * Las reglas viven en este enum y no repartidas por los recursos para que
 * se puedan leer de corrido y probar juntas.
 */
enum Capacidad: string
{
    /** Ver un catalogo de precios: lo necesita cualquiera que cotice. */
    case VerCatalogos = 'ver_catalogos';

    /** Cambiar un precio base: afecta a lo que cotizan los demas. */
    case EditarCatalogos = 'editar_catalogos';

    /** Usuarios y roles. */
    case AdministrarUsuarios = 'administrar_usuarios';

    /**
     * Factores legales.
     *
     * Es una sola fila que entra en el costo de cada persona de cada
     * cotizacion: tocarla cambia todos los precios a la vez.
     */
    case AdministrarFactoresLegales = 'administrar_factores';

    /** Ver cotizaciones. */
    case VerCotizaciones = 'ver_cotizaciones';

    /** Crear una cotizacion nueva. */
    case CrearCotizaciones = 'crear_cotizaciones';

    /** Borrar una cotizacion con todos sus recursos. */
    case BorrarCotizaciones = 'borrar_cotizaciones';

    /**
     * Quien puede ejercer cada capacidad.
     *
     * super_admin no aparece: puede todo, y se resuelve aparte para que no
     * haya que acordarse de añadirlo en cada linea.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return match ($this) {
            /** Para cotizar hay que poder consultar los precios. */
            self::VerCatalogos,
            self::VerCotizaciones,
            self::CrearCotizaciones => ['Administrador', 'Gerente', 'Creador', 'Director'],

            /**
             * Cambiar un precio base afecta a las cotizaciones de otros, de
             * modo que queda en manos de quien responde por ellas.
             */
            self::EditarCatalogos => ['Administrador', 'Gerente'],

            /** Borrar arrastra items, recursos y asignaciones. */
            self::BorrarCotizaciones => ['Administrador', 'Gerente'],

            self::AdministrarUsuarios,
            self::AdministrarFactoresLegales => ['Administrador'],
        };
    }

    /** Lo que se le dice al usuario cuando no puede. */
    public function motivoDelBloqueo(): string
    {
        return match ($this) {
            self::VerCatalogos, self::VerCotizaciones => 'No tiene permiso para consultar esta sección.',
            self::EditarCatalogos => 'Solo la administración o un gerente pueden modificar los precios base.',
            self::CrearCotizaciones => 'No tiene permiso para crear cotizaciones.',
            self::BorrarCotizaciones => 'Solo la administración o un gerente pueden borrar una cotización.',
            self::AdministrarUsuarios => 'Solo la administración puede gestionar usuarios.',
            self::AdministrarFactoresLegales => 'Solo la administración puede modificar los factores legales.',
        };
    }
}
