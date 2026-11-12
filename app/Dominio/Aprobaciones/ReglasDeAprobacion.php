<?php

namespace App\Dominio\Aprobaciones;

use App\Models\Cotizacion;
use App\Models\User;

/**
 * Quien puede editar, enviar, aprobar o rechazar una cotizacion.
 *
 * Una sola clase decide, y la pantalla solo pregunta. En el sistema actual
 * estas reglas viven repartidas entre CotizacionAuth, el modelo y los
 * metodos visible() de cada boton, lo que ya produjo un fallo real: un
 * usuario que era a la vez creador y Administrador no podia editar su
 * propio borrador, porque se comprobaba el rol antes que la autoria.
 *
 * EDITAR y APROBAR son permisos distintos. Un Gerente que ademas es
 * Administrador puede aprobar sus propias cotizaciones en los dos niveles:
 * no hay segregacion de funciones. Es como funciona hoy y se mantiene; si
 * alguna vez se quiere exigir que aprueben personas distintas, el sitio
 * donde ponerlo es aqui.
 */
final class ReglasDeAprobacion
{
    public const ROL_TECNICO = 'Gerente';

    public const ROL_FINANCIERO = 'Administrador';

    public const ROL_TOTAL = 'super_admin';

    public function estado(Cotizacion $cotizacion): EstadoAprobacion
    {
        return EstadoAprobacion::desdeColumnas(
            $cotizacion->aprobacion1,
            $cotizacion->aprobacion2,
        );
    }

    /**
     * Si el usuario puede modificar el contenido.
     *
     * El orden de las comprobaciones importa: la autoria se mira ANTES que
     * los roles, porque quien escribio un borrador puede seguir
     * trabajandolo aunque ademas tenga un rol de aprobador.
     */
    public function puedeEditar(Cotizacion $cotizacion, ?User $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        if ($usuario->hasRole(self::ROL_TOTAL)) {
            return true;
        }

        $estado = $this->estado($cotizacion);

        /** Lo aprobado es lo que se envio al cliente: no se toca. */
        if ($estado->estaCerrada()) {
            return false;
        }

        return match ($estado) {
            /** En borrador o devuelta: el autor, su gerencia o la administracion. */
            EstadoAprobacion::Borrador,
            EstadoAprobacion::RechazadaTecnica => $this->esAutor($cotizacion, $usuario)
                || $this->esDeLaMismaGerencia($cotizacion, $usuario)
                || $usuario->hasRole(self::ROL_FINANCIERO),

            /** En manos del gerente: solo el gerente de esa gerencia. */
            EstadoAprobacion::EsperandoTecnica => $this->esGerenteDeLaGerencia($cotizacion, $usuario),

            /** En manos de la administracion: solo la administracion. */
            EstadoAprobacion::EsperandoFinanciera => $usuario->hasRole(self::ROL_FINANCIERO),

            /** Devuelta por la administracion: el gerente la corrige, o ella misma. */
            EstadoAprobacion::RechazadaFinanciera => $this->esGerenteDeLaGerencia($cotizacion, $usuario)
                || $usuario->hasRole(self::ROL_FINANCIERO),

            EstadoAprobacion::Aprobada => false,
        };
    }

    /** Por que no puede editarla, en palabras que el usuario entienda. */
    public function motivoDelBloqueo(Cotizacion $cotizacion, ?User $usuario): string
    {
        if (! $usuario) {
            return 'Debe iniciar sesión.';
        }

        return match ($this->estado($cotizacion)) {
            EstadoAprobacion::Aprobada => 'La cotización ya fue aprobada y no admite cambios.',
            EstadoAprobacion::EsperandoTecnica => 'Está en aprobación técnica: solo el gerente de la gerencia puede modificarla.',
            EstadoAprobacion::EsperandoFinanciera => 'Está en aprobación financiera: solo la administración puede modificarla.',
            EstadoAprobacion::RechazadaFinanciera => 'Fue devuelta por la administración: la corrige el gerente o la propia administración.',
            default => 'No tiene permisos sobre esta cotización.',
        };
    }

    /**
     * Si puede enviarla a aprobacion tecnica.
     *
     * Solo desde borrador o tras un rechazo tecnico, y solo quien podria
     * editarla: no tiene sentido enviar algo que no se puede tocar.
     */
    public function puedeEnviarAAprobacion(Cotizacion $cotizacion, ?User $usuario): bool
    {
        $estado = $this->estado($cotizacion);

        if ($estado !== EstadoAprobacion::Borrador && $estado !== EstadoAprobacion::RechazadaTecnica) {
            return false;
        }

        return $this->puedeEditar($cotizacion, $usuario);
    }

    /** Si puede resolver la aprobacion tecnica (aprobar o rechazar). */
    public function puedeDecidirTecnica(Cotizacion $cotizacion, ?User $usuario): bool
    {
        if (! $usuario || $this->estado($cotizacion) !== EstadoAprobacion::EsperandoTecnica) {
            return false;
        }

        return $usuario->hasRole(self::ROL_TOTAL)
            || $this->esGerenteDeLaGerencia($cotizacion, $usuario);
    }

    /** Si puede resolver la aprobacion financiera. */
    public function puedeDecidirFinanciera(Cotizacion $cotizacion, ?User $usuario): bool
    {
        if (! $usuario || $this->estado($cotizacion) !== EstadoAprobacion::EsperandoFinanciera) {
            return false;
        }

        return $usuario->hasRole(self::ROL_TOTAL)
            || $usuario->hasRole(self::ROL_FINANCIERO);
    }

    /**
     * Si puede reabrir una cotizacion ya aprobada.
     *
     * Es deshacer algo que ya se envio al cliente, asi que queda reservado
     * a la administracion y queda registrado como cualquier otra decision.
     */
    public function puedeReabrir(Cotizacion $cotizacion, ?User $usuario): bool
    {
        if (! $usuario || ! $this->estado($cotizacion)->estaCerrada()) {
            return false;
        }

        return $usuario->hasRole(self::ROL_TOTAL)
            || $usuario->hasRole(self::ROL_FINANCIERO);
    }

    private function esAutor(Cotizacion $cotizacion, User $usuario): bool
    {
        return $cotizacion->id_user !== null
            && (int) $cotizacion->id_user === (int) $usuario->id;
    }

    /**
     * Si pertenece a la gerencia de la cotizacion.
     *
     * Se compara flojo a proposito: 'gerencia' guarda numeros en unas filas
     * y texto en otras, un desajuste conocido y pendiente de normalizar.
     */
    private function esDeLaMismaGerencia(Cotizacion $cotizacion, User $usuario): bool
    {
        return $cotizacion->gerencia !== null
            && $usuario->id_gerencia !== null
            && (string) $cotizacion->gerencia === (string) $usuario->id_gerencia;
    }

    private function esGerenteDeLaGerencia(Cotizacion $cotizacion, User $usuario): bool
    {
        return $usuario->hasRole(self::ROL_TECNICO)
            && $this->esDeLaMismaGerencia($cotizacion, $usuario);
    }
}
