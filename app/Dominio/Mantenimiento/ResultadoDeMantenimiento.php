<?php

namespace App\Dominio\Mantenimiento;

/**
 * Como fue una tarea de mantenimiento.
 *
 * Se devuelve en lugar de lanzar una excepcion: esta pagina es justamente
 * la que se usa cuando algo va mal, asi que un fallo aqui no puede dejar al
 * usuario ante una pantalla de error.
 */
final readonly class ResultadoDeMantenimiento
{
    private function __construct(
        public TareaDeMantenimiento $tarea,
        public bool $salioBien,
        public string $detalle,
        public float $segundos,
    ) {}

    public static function exito(TareaDeMantenimiento $tarea, string $detalle, float $segundos): self
    {
        return new self($tarea, true, $detalle, $segundos);
    }

    public static function fallo(TareaDeMantenimiento $tarea, string $detalle, float $segundos): self
    {
        return new self($tarea, false, $detalle, $segundos);
    }

    /** Lo que se le dice a quien pulso el boton. */
    public function titulo(): string
    {
        return $this->salioBien
            ? $this->tarea->etiqueta() . ': hecho'
            : 'No se pudo: ' . mb_strtolower($this->tarea->etiqueta());
    }

    /**
     * El detalle, recortado para caber en un aviso.
     *
     * La salida completa queda en el registro; aqui solo lo justo para
     * saber si hay que mirarlo.
     */
    public function resumen(): string
    {
        $texto = trim($this->detalle);

        if ($texto === '') {
            return "Terminó en {$this->segundos} s.";
        }

        return mb_strlen($texto) > 300
            ? mb_substr($texto, 0, 300) . '…'
            : $texto;
    }
}
