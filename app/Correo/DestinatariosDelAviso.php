<?php

namespace App\Correo;

use App\Dominio\Aprobaciones\EstadoAprobacion;
use App\Dominio\Aprobaciones\ReglasDeAprobacion;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Quien recibe el aviso de cada transicion.
 *
 * Separado del envio para poder comprobarlo sin mandar correos: en el
 * sistema actual la lista de destinatarios esta entremezclada con las
 * llamadas a Mail::to dentro de un metodo de 160 lineas, y no hay forma de
 * verificar a quien se avisa sin enviar de verdad.
 *
 * La regla es la misma del flujo: avisa a quien le toca actuar, y ademas al
 * autor cuando la decision le afecta.
 */
final class DestinatariosDelAviso
{
    /**
     * A quien hay que avisar de que la cotizacion llego a este estado.
     *
     * Devuelve pares de destinatario y si el aviso es "para el autor", que
     * cambia el texto: a quien debe actuar se le pide algo, al autor se le
     * informa.
     *
     * @return Collection<int, array{usuario: User, paraElAutor: bool}>
     */
    public function para(Cotizacion $cotizacion, EstadoAprobacion $estado): Collection
    {
        $avisos = match ($estado) {
            /** Le toca al gerente de la gerencia. */
            EstadoAprobacion::EsperandoTecnica => $this->marcar($this->gerentesDeLaGerencia($cotizacion), paraElAutor: false),

            /** Le toca a la administracion; el autor se entera de que avanzo. */
            EstadoAprobacion::EsperandoFinanciera => $this->marcar($this->administradores(), paraElAutor: false)
                ->concat($this->marcar($this->autor($cotizacion), paraElAutor: true)),

            /** Vuelve al autor. */
            EstadoAprobacion::RechazadaTecnica => $this->marcar($this->autor($cotizacion), paraElAutor: true),

            /** Vuelve al gerente; el autor se entera. */
            EstadoAprobacion::RechazadaFinanciera => $this->marcar($this->gerentesDeLaGerencia($cotizacion), paraElAutor: false)
                ->concat($this->marcar($this->autor($cotizacion), paraElAutor: true)),

            /** Cerrada: se avisa al autor y al gerente que la aprobo. */
            EstadoAprobacion::Aprobada => $this->marcar($this->autor($cotizacion), paraElAutor: true)
                ->concat($this->marcar($this->gerentesDeLaGerencia($cotizacion), paraElAutor: false)),

            /** Volver a borrador no se avisa: no hay nada que hacer todavia. */
            EstadoAprobacion::Borrador => collect(),
        };

        return $this->sinRepetidosNiVacios($avisos);
    }

    /** @return Collection<int, User> */
    private function gerentesDeLaGerencia(Cotizacion $cotizacion): Collection
    {
        if (blank($cotizacion->gerencia)) {
            return collect();
        }

        /**
         * Se compara en texto a proposito: 'gerencia' guarda numeros en unas
         * filas y cadenas en otras, un desajuste conocido y pendiente de
         * normalizar. Comparar flojo evita que el aviso se pierda por eso.
         */
        return User::query()
            ->role(ReglasDeAprobacion::ROL_TECNICO)
            ->get()
            ->filter(fn (User $u): bool => (string) $u->id_gerencia === (string) $cotizacion->gerencia)
            ->values();
    }

    /** @return Collection<int, User> */
    private function administradores(): Collection
    {
        return User::query()->role(ReglasDeAprobacion::ROL_FINANCIERO)->get();
    }

    /** @return Collection<int, User> */
    private function autor(Cotizacion $cotizacion): Collection
    {
        $autor = $cotizacion->autor;

        return $autor ? collect([$autor]) : collect();
    }

    /**
     * @param Collection<int, User> $usuarios
     * @return Collection<int, array{usuario: User, paraElAutor: bool}>
     */
    private function marcar(Collection $usuarios, bool $paraElAutor): Collection
    {
        return $usuarios->map(fn (User $u): array => ['usuario' => $u, 'paraElAutor' => $paraElAutor]);
    }

    /**
     * Un correo por persona, y solo a quien tiene direccion.
     *
     * Quien es a la vez autor y aprobador recibiria dos avisos del mismo
     * hecho; se queda con el primero, que es el que le pide actuar.
     *
     * @param Collection<int, array{usuario: User, paraElAutor: bool}> $avisos
     * @return Collection<int, array{usuario: User, paraElAutor: bool}>
     */
    private function sinRepetidosNiVacios(Collection $avisos): Collection
    {
        return $avisos
            ->filter(fn (array $a): bool => filled($a['usuario']->email))
            ->unique(fn (array $a): string => mb_strtolower((string) $a['usuario']->email))
            ->values();
    }
}
