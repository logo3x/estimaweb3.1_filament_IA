<?php

namespace App\Dominio\Auditoria;

/**
 * Que se hizo.
 *
 * No son solo los tres verbos de la base: aprobar una cotizacion o
 * suplantar a un usuario son hechos que importan en una auditoria aunque
 * por dentro sean un UPDATE como cualquier otro.
 */
enum AccionAuditada: string
{
    case Creado = 'creado';
    case Modificado = 'modificado';
    case Borrado = 'borrado';

    /** Decisiones del flujo: lo que mas se consulta despues. */
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case EnviadoAAprobacion = 'enviado_a_aprobacion';

    /** El margen aparte: es lo que convierte el costo en precio. */
    case MargenCambiado = 'margen_cambiado';

    /**
     * Alguien entro al panel como otra persona.
     *
     * Se audita porque durante ese rato actua con los permisos del otro:
     * sin este registro, lo que haga parecera hecho por el suplantado.
     */
    case Suplantacion = 'suplantacion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Creado => 'Creado',
            self::Modificado => 'Modificado',
            self::Borrado => 'Borrado',
            self::Aprobado => 'Aprobado',
            self::Rechazado => 'Devuelto',
            self::EnviadoAAprobacion => 'Enviado a aprobación',
            self::MargenCambiado => 'Margen cambiado',
            self::Suplantacion => 'Entró como otro usuario',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Creado => 'success',
            self::Modificado => 'info',
            self::Borrado, self::Rechazado => 'danger',
            self::Aprobado => 'success',
            self::EnviadoAAprobacion => 'warning',
            self::MargenCambiado, self::Suplantacion => 'warning',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Creado => 'heroicon-m-plus-circle',
            self::Modificado => 'heroicon-m-pencil',
            self::Borrado => 'heroicon-m-trash',
            self::Aprobado => 'heroicon-m-check-circle',
            self::Rechazado => 'heroicon-m-x-circle',
            self::EnviadoAAprobacion => 'heroicon-m-paper-airplane',
            self::MargenCambiado => 'heroicon-m-calculator',
            self::Suplantacion => 'heroicon-m-user-circle',
        };
    }

    /**
     * Si merece destacarse en la pantalla.
     *
     * Lo que toca el dinero o los permisos se mira distinto que un cambio
     * de nombre.
     */
    public function esDelicada(): bool
    {
        return in_array($this, [self::MargenCambiado, self::Suplantacion, self::Borrado], true);
    }
}
