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
     * Cambiar el margen: administracion, imprevistos y utilidad.
     *
     * Es lo que convierte el costo en precio. Tocarlo cambia lo que se le
     * cobra al cliente, asi que no lo hace quien arma la cotizacion sino
     * quien responde por el precio.
     */
    case ConfigurarElMargen = 'configurar_el_margen';

    /**
     * Generar el Word y el PDF que se le mandan al cliente.
     *
     * El documento sale con membrete y firma del Presidente: es la oferta
     * formal de la empresa, no un borrador para revisar.
     */
    case GenerarDocumentos = 'generar_documentos';

    /**
     * Mantener el servidor desde el panel.
     *
     * Rehacer caches, aplicar migraciones, ver el diagnostico. Hace falta
     * porque el hosting no tiene consola, pero afecta al sitio entero: se
     * reserva al rol mas restringido.
     */
    case MantenerElSistema = 'mantener_el_sistema';

    /**
     * Crear y descargar respaldos de la base.
     *
     * Se da tambien al Administrador, y no solo a la administracion total,
     * a proposito: en el sistema anterior el respaldo estaba atado a un
     * correo concreto escrito en el codigo, de modo que si esa persona no
     * estaba, nadie podia respaldar sin un despliegue.
     *
     * El archivo lleva todos los precios, margenes y datos de clientes, asi
     * que tampoco se abre mas alla de ahi.
     */
    case GestionarRespaldos = 'gestionar_respaldos';

    /**
     * Consultar quien hizo que: cambios, accesos y envios.
     *
     * Solo consultar. La auditoria no se edita ni se borra desde el panel
     * -en el sistema anterior si se podia, y eso anula su valor como
     * evidencia-, de modo que esta capacidad es de lectura.
     */
    case ConsultarLaAuditoria = 'consultar_la_auditoria';

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

            /**
             * Igual que en el sistema actual.
             *
             * Alli lo comprueba `AiuStep::handle()`: «Solo Administradores
             * y Gerentes pueden configurar el AIU». Sin esta linea, un
             * Creador cambiaba el margen y con el, el precio de la oferta.
             */
            self::ConfigurarElMargen => ['Administrador', 'Gerente'],

            /**
             * Tambien como en el sistema actual, que lo reserva a
             * `isAdmin()` -Administrador y super_admin-. El Gerente aprueba
             * la oferta; quien la emite al cliente es la administracion.
             */
            self::GenerarDocumentos => ['Administrador'],

            self::AdministrarUsuarios,
            self::AdministrarFactoresLegales,
            /**
             * El respaldo y la auditoria son tarea de la administracion.
             *
             * Deliberadamente NO se reservan a super_admin: un respaldo que
             * solo puede hacer una persona concreta es el que no se hace.
             */
            self::GestionarRespaldos,
            self::ConsultarLaAuditoria => ['Administrador'],
            /**
             * Lista vacia a proposito: solo super_admin, que Permisos
             * resuelve antes de mirar esta tabla. Estas acciones afectan al
             * servidor entero, no a una cotizacion.
             */
            self::MantenerElSistema => [],
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
            self::ConfigurarElMargen => 'Solo la administración o un gerente pueden cambiar el margen: afecta al precio de la oferta.',
            self::GenerarDocumentos => 'Solo la administración genera la oferta para el cliente.',
            self::AdministrarUsuarios => 'Solo la administración puede gestionar usuarios.',
            self::AdministrarFactoresLegales => 'Solo la administración puede modificar los factores legales.',
            self::GestionarRespaldos => 'Solo la administración puede crear y descargar respaldos: contienen todos los precios y datos de clientes.',
            self::ConsultarLaAuditoria => 'Solo la administración puede consultar el registro de actividad.',
            self::MantenerElSistema => 'El mantenimiento del servidor está reservado a la administración total.',
        };
    }
}
