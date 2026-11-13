<?php

namespace App\Correo;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * El aviso que recibe quien tiene que actuar sobre una cotizacion.
 *
 * Uno solo para las cinco transiciones del flujo. El sistema actual tiene
 * cuatro clases y cinco plantillas HTML que repiten los mismos estilos y
 * casi el mismo texto; cambiar el pie de los correos obliga a tocar cinco
 * archivos.
 *
 * Lo que varia entre un aviso y otro es el asunto, el titular y la
 * llamada a la accion, y eso se deduce del estado al que acaba de llegar
 * la cotizacion.
 */
final class AvisoDeAprobacion extends Mailable
{
    public function __construct(
        public readonly Cotizacion $cotizacion,
        public readonly EstadoAprobacion $estado,
        public readonly ?User $autorDeLaAccion = null,
        public readonly ?string $comentarios = null,
        public readonly bool $paraElAutor = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'correo.aviso-de-aprobacion',
            with: [
                'titular' => $this->titular(),
                'explicacion' => $this->explicacion(),
                'accionDelAutor' => $this->accionDelAutor(),
                'urlDeLaCotizacion' => route('filament.admin.resources.cotizaciones.view', ['record' => $this->cotizacion->id]),
            ],
        );
    }

    /**
     * El asunto lleva el codigo porque es como se refieren a ellas.
     *
     * Y la accion delante, para que se entienda sin abrir el correo.
     */
    private function asunto(): string
    {
        $codigo = $this->cotizacion->codigo ?: ('cotización ' . $this->cotizacion->id);

        return match (true) {
            $this->paraElAutor && $this->estado === EstadoAprobacion::Aprobada => "Aprobada: {$codigo}",
            $this->paraElAutor => "Novedad en {$codigo}",

            $this->estado === EstadoAprobacion::EsperandoTecnica => "Pendiente de su aprobación técnica: {$codigo}",
            $this->estado === EstadoAprobacion::EsperandoFinanciera => "Pendiente de su aprobación financiera: {$codigo}",
            $this->estado === EstadoAprobacion::RechazadaTecnica => "Devuelta para corregir: {$codigo}",
            $this->estado === EstadoAprobacion::RechazadaFinanciera => "Devuelta por la administración: {$codigo}",
            $this->estado === EstadoAprobacion::Aprobada => "Aprobada: {$codigo}",
            default => "Novedad en {$codigo}",
        };
    }

    private function titular(): string
    {
        return match (true) {
            $this->paraElAutor && $this->estado === EstadoAprobacion::Aprobada => 'Su cotización fue aprobada',
            $this->paraElAutor && $this->estado === EstadoAprobacion::RechazadaTecnica => 'Su cotización fue devuelta',
            $this->paraElAutor => 'Novedad en su cotización',

            $this->estado === EstadoAprobacion::EsperandoTecnica => 'Una cotización espera su aprobación técnica',
            $this->estado === EstadoAprobacion::EsperandoFinanciera => 'Una cotización espera su aprobación financiera',
            $this->estado === EstadoAprobacion::RechazadaFinanciera => 'La administración devolvió una cotización',
            $this->estado === EstadoAprobacion::Aprobada => 'Cotización aprobada',
            default => 'Novedad en una cotización',
        };
    }

    /**
     * Lo que hizo quien tomo la decision, redactado como accion.
     *
     * No vale la etiqueta del estado: daria frases como "Esperando
     * aprobacion tecnica por Fulano".
     */
    private function accionDelAutor(): string
    {
        return match ($this->estado) {
            EstadoAprobacion::EsperandoTecnica => 'Enviada a aprobación',
            EstadoAprobacion::EsperandoFinanciera => 'Aprobada técnicamente',
            EstadoAprobacion::RechazadaTecnica => 'Devuelta',
            EstadoAprobacion::RechazadaFinanciera => 'Devuelta',
            EstadoAprobacion::Aprobada => 'Aprobada',
            EstadoAprobacion::Borrador => 'Reabierta',
        };
    }

    /** Que se espera de quien lo recibe. */
    private function explicacion(): string
    {
        if ($this->paraElAutor) {
            return match ($this->estado) {
                EstadoAprobacion::Aprobada => 'Ya está aprobada en los dos niveles y no admite más cambios.',
                EstadoAprobacion::RechazadaTecnica => 'Revise los comentarios, corrija lo indicado y vuelva a enviarla.',
                EstadoAprobacion::RechazadaFinanciera => 'La administración la devolvió al gerente de su gerencia.',
                default => 'Puede consultar su estado en el sistema.',
            };
        }

        return match ($this->estado) {
            EstadoAprobacion::EsperandoTecnica => 'Revísela y apruébela o devuélvala indicando qué corregir.',
            EstadoAprobacion::EsperandoFinanciera => 'Fue aprobada técnicamente y espera la revisión financiera.',
            EstadoAprobacion::RechazadaFinanciera => 'Revise los comentarios y corrija lo indicado antes de volver a enviarla.',
            EstadoAprobacion::Aprobada => 'Quedó cerrada y no admite más cambios.',
            default => 'Puede consultar su estado en el sistema.',
        };
    }
}
