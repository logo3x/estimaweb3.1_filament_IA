<?php

namespace App\Dominio\Aprobaciones;

use App\Models\Cotizacion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta los cambios de estado del flujo de aprobacion.
 *
 * Toda transicion pasa por aqui, y ninguna ocurre sin comprobar antes que
 * quien la pide tiene permiso. En el sistema actual los botones escriben
 * directamente en las columnas, de modo que el permiso solo lo garantiza el
 * visible() del boton: quien alcance la accion por otra via no encuentra
 * ninguna comprobacion detras.
 *
 * Cada metodo devuelve la cotizacion recargada para que la pantalla muestre
 * el estado nuevo sin volver a consultarla.
 */
final class FlujoDeAprobacion
{
    public function __construct(
        private readonly ReglasDeAprobacion $reglas,
    ) {}

    public static function crear(): self
    {
        return new self(new ReglasDeAprobacion());
    }

    /**
     * El autor la envia al gerente.
     *
     * @throws DomainException si el usuario no puede enviarla
     */
    public function enviarAAprobacion(Cotizacion $cotizacion, User $usuario, ?string $comentarios = null): Cotizacion
    {
        $this->exigir(
            $this->reglas->puedeEnviarAAprobacion($cotizacion, $usuario),
            'No puede enviar esta cotización a aprobación.'
        );

        return $this->escribir($cotizacion, [
            'aprobacion1' => 'PENDIENTE',
            'aprobacion1_comentarios' => $comentarios,
            'aprobacion1_fecha' => now(),
            /** Se limpia al reenviar: la decision anterior ya no vale. */
            'aprobacion1_user_id' => null,
        ]);
    }

    /**
     * El gerente aprueba; pasa a la administracion.
     *
     * @throws DomainException si el usuario no puede decidir
     */
    public function aprobarTecnica(Cotizacion $cotizacion, User $usuario, ?string $comentarios = null): Cotizacion
    {
        $this->exigir(
            $this->reglas->puedeDecidirTecnica($cotizacion, $usuario),
            'No puede aprobar técnicamente esta cotización.'
        );

        return $this->escribir($cotizacion, [
            'aprobacion1' => 'SI',
            'aprobacion1_comentarios' => $comentarios,
            'aprobacion1_user_id' => $usuario->id,
            'aprobacion1_fecha' => now(),
            /** Queda esperando a la administracion. */
            'aprobacion2' => 'PENDIENTE',
            'aprobacion2_user_id' => null,
            'aprobacion2_fecha' => null,
        ]);
    }

    /**
     * El gerente la devuelve al autor.
     *
     * @throws DomainException si el usuario no puede decidir
     */
    public function rechazarTecnica(Cotizacion $cotizacion, User $usuario, string $comentarios): Cotizacion
    {
        $this->exigir(
            $this->reglas->puedeDecidirTecnica($cotizacion, $usuario),
            'No puede rechazar técnicamente esta cotización.'
        );

        $this->exigirMotivo($comentarios);

        return $this->escribir($cotizacion, [
            'aprobacion1' => 'NO',
            'aprobacion1_comentarios' => $comentarios,
            'aprobacion1_user_id' => $usuario->id,
            'aprobacion1_fecha' => now(),
            /**
             * Se limpia el segundo nivel.
             *
             * En produccion hay una cotizacion rechazada tecnicamente cuya
             * aprobacion2 quedo en 'PENDIENTE': un estado que no significa
             * nada, porque nunca llego a ese nivel.
             */
            'aprobacion2' => null,
            'aprobacion2_comentarios' => null,
            'aprobacion2_user_id' => null,
            'aprobacion2_fecha' => null,
        ]);
    }

    /**
     * La administracion aprueba; la cotizacion queda cerrada.
     *
     * @throws DomainException si el usuario no puede decidir
     */
    public function aprobarFinanciera(Cotizacion $cotizacion, User $usuario, ?string $comentarios = null): Cotizacion
    {
        $this->exigir(
            $this->reglas->puedeDecidirFinanciera($cotizacion, $usuario),
            'No puede aprobar financieramente esta cotización.'
        );

        return $this->escribir($cotizacion, [
            'aprobacion2' => 'SI',
            'aprobacion2_comentarios' => $comentarios,
            'aprobacion2_user_id' => $usuario->id,
            'aprobacion2_fecha' => now(),
        ]);
    }

    /**
     * La administracion la devuelve al gerente.
     *
     * @throws DomainException si el usuario no puede decidir
     */
    public function rechazarFinanciera(Cotizacion $cotizacion, User $usuario, string $comentarios): Cotizacion
    {
        $this->exigir(
            $this->reglas->puedeDecidirFinanciera($cotizacion, $usuario),
            'No puede rechazar financieramente esta cotización.'
        );

        $this->exigirMotivo($comentarios);

        return $this->escribir($cotizacion, [
            'aprobacion2' => 'NO',
            'aprobacion2_comentarios' => $comentarios,
            'aprobacion2_user_id' => $usuario->id,
            'aprobacion2_fecha' => now(),
        ]);
    }

    /**
     * Devuelve una cotizacion aprobada a borrador.
     *
     * Se borra el rastro de ambos niveles porque las decisiones anteriores
     * se tomaron sobre un contenido que va a cambiar.
     *
     * @throws DomainException si el usuario no puede reabrirla
     */
    public function reabrir(Cotizacion $cotizacion, User $usuario, string $comentarios): Cotizacion
    {
        $this->exigir(
            $this->reglas->puedeReabrir($cotizacion, $usuario),
            'No puede reabrir esta cotización.'
        );

        $this->exigirMotivo($comentarios);

        return $this->escribir($cotizacion, [
            'aprobacion1' => null,
            'aprobacion1_comentarios' => $comentarios,
            'aprobacion1_user_id' => null,
            'aprobacion1_fecha' => null,
            'aprobacion2' => null,
            'aprobacion2_comentarios' => null,
            'aprobacion2_user_id' => null,
            'aprobacion2_fecha' => null,
        ]);
    }

    /**
     * Escribe y devuelve la cotizacion recargada.
     *
     * En transaccion porque cada transicion toca varias columnas que solo
     * tienen sentido juntas: un estado a medias no es ningun estado.
     *
     * @param array<string, mixed> $cambios
     */
    private function escribir(Cotizacion $cotizacion, array $cambios): Cotizacion
    {
        DB::transaction(function () use ($cotizacion, $cambios): void {
            $cotizacion->forceFill($cambios)->save();
        });

        return $cotizacion->refresh();
    }

    /** @throws DomainException */
    private function exigir(bool $permitido, string $mensaje): void
    {
        if (! $permitido) {
            throw new DomainException($mensaje);
        }
    }

    /**
     * Un rechazo sin motivo deja al siguiente sin saber que corregir.
     *
     * @throws DomainException
     */
    private function exigirMotivo(string $comentarios): void
    {
        if (trim($comentarios) === '') {
            throw new DomainException('Debe indicar el motivo.');
        }
    }
}
