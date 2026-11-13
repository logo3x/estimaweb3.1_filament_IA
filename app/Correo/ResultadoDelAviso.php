<?php

namespace App\Correo;

/**
 * Que paso con los avisos de una transicion.
 *
 * Existe para que la pantalla pueda decir la verdad: "aprobada, 2 avisos
 * enviados" o "aprobada, pero no se pudo avisar a fulano". El sistema
 * actual no distingue, de modo que un correo que nunca sale parece haber
 * salido.
 */
final class ResultadoDelAviso
{
    /**
     * @param list<string> $fallidos direcciones a las que no se pudo enviar
     */
    public function __construct(
        public readonly int $enviados,
        public readonly array $fallidos = [],
    ) {}

    public function huboFallos(): bool
    {
        return $this->fallidos !== [];
    }

    public function noHabiaAQuienAvisar(): bool
    {
        return $this->enviados === 0 && $this->fallidos === [];
    }

    /** Lo que se le muestra al usuario tras la transicion. */
    public function resumen(): string
    {
        if ($this->noHabiaAQuienAvisar()) {
            return 'No había a quién avisar.';
        }

        if (! $this->huboFallos()) {
            return $this->enviados === 1
                ? 'Se envió 1 aviso por correo.'
                : "Se enviaron {$this->enviados} avisos por correo.";
        }

        return sprintf(
            'Se enviaron %d aviso(s), pero no se pudo avisar a: %s.',
            $this->enviados,
            implode(', ', $this->fallidos),
        );
    }
}
