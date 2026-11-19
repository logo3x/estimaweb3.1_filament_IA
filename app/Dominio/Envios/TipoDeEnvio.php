<?php

namespace App\Dominio\Envios;

/**
 * Que se genero o se mando, y por que medio.
 *
 * El sistema anterior llamaba "impresion" a una sola cosa -generar el
 * Word- y no distinguia nada mas: un PDF descargado y un correo enviado al
 * cliente no dejaban rastro propio. Aqui cada salida dice lo que fue,
 * porque ante una discusion con un cliente no es lo mismo "se genero un
 * borrador" que "se le envio".
 */
enum TipoDeEnvio: string
{
    /** Se genero el Word de la oferta. */
    case Word = 'word';

    /** Se genero el PDF de la oferta. */
    case Pdf = 'pdf';

    /** Salio un correo con la oferta hacia fuera. */
    case CorreoAlCliente = 'correo_cliente';

    /** Aviso interno del flujo de aprobacion. */
    case AvisoInterno = 'aviso_interno';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Word => 'Word generado',
            self::Pdf => 'PDF generado',
            self::CorreoAlCliente => 'Enviada por correo',
            self::AvisoInterno => 'Aviso de aprobación',
        };
    }

    /**
     * Si salio de la empresa.
     *
     * Es la distincion que importa ante un cliente que discute una oferta:
     * generar un documento no es lo mismo que habersela mandado.
     */
    public function salioAlCliente(): bool
    {
        return $this === self::CorreoAlCliente;
    }

    public function color(): string
    {
        return match ($this) {
            self::Word => 'info',
            self::Pdf => 'warning',
            self::CorreoAlCliente => 'success',
            self::AvisoInterno => 'gray',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Word => 'heroicon-m-document-text',
            self::Pdf => 'heroicon-m-document',
            self::CorreoAlCliente => 'heroicon-m-paper-airplane',
            self::AvisoInterno => 'heroicon-m-bell',
        };
    }
}
