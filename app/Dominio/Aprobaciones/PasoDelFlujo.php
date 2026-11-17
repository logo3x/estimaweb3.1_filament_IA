<?php

namespace App\Dominio\Aprobaciones;

/**
 * Un paso del ciclo de aprobacion, ya resuelto para mostrarlo.
 *
 * Es un dato, no una vista: la plantilla solo lo pinta. Asi el flujo se
 * puede probar sin abrir un navegador.
 */
final readonly class PasoDelFlujo
{
    public function __construct(
        public string $titulo,
        /** `hecho`, `actual`, `rechazado` o `pendiente`. */
        public string $estado,
        /** Quien decidio, o a quien le toca. */
        public string $quien,
        /** Cuando se decidio, en palabras. Vacio si aun no paso. */
        public string $cuando,
        /** Lo que escribio quien decidio. Casi siempre vacio. */
        public string $comentario,
        /** Que tiene que pasar para que avance. Solo en el paso actual. */
        public string $queFalta,
    ) {}

    public function estaHecho(): bool
    {
        return $this->estado === 'hecho';
    }

    public function esElActual(): bool
    {
        return $this->estado === 'actual';
    }

    public function fueRechazado(): bool
    {
        return $this->estado === 'rechazado';
    }

    /**
     * La marca del circulo: un visto, una cruz o el numero del paso.
     *
     * El numero solo aparece mientras no ha pasado nada: una vez decidido,
     * importa el resultado y no el orden.
     */
    public function marca(int $numero): string
    {
        return match ($this->estado) {
            'hecho' => '✓',
            'rechazado' => '✕',
            default => (string) $numero,
        };
    }
}
