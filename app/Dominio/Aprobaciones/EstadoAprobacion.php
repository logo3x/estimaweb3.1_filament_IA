<?php

namespace App\Dominio\Aprobaciones;

/**
 * El estado de una cotizacion dentro del flujo de aprobacion.
 *
 * El sistema actual lo deduce comparando cadenas ('SI', 'NO', 'PENDIENTE')
 * repartidas por mas de cuarenta sitios. Eso tiene dos consecuencias: hay
 * combinaciones que nadie contemplo, y basta un dato con otro formato para
 * que una comparacion falle en silencio.
 *
 * Aqui el estado se deriva UNA vez desde las dos columnas, y el resto del
 * sistema pregunta por el estado, no por las columnas.
 */
enum EstadoAprobacion: string
{
    /** Nadie la ha enviado a aprobar todavia; el autor puede trabajarla. */
    case Borrador = 'borrador';

    /** Enviada; espera al gerente de la gerencia. */
    case EsperandoTecnica = 'esperando_tecnica';

    /** El gerente la rechazo; vuelve al autor. */
    case RechazadaTecnica = 'rechazada_tecnica';

    /** Aprobada por el gerente; espera a la administracion. */
    case EsperandoFinanciera = 'esperando_financiera';

    /** La administracion la rechazo; vuelve al gerente. */
    case RechazadaFinanciera = 'rechazada_financiera';

    /** Aprobada por ambos: cerrada, no admite cambios. */
    case Aprobada = 'aprobada';

    /**
     * Deriva el estado desde las dos columnas de la base.
     *
     * Acepta los valores tal como estan guardados hoy ('SI', 'NO',
     * 'PENDIENTE' y nulo) para no tener que migrar datos. La comparacion
     * ignora mayusculas y espacios: en produccion conviven 'SI' y 'Si' en
     * otras columnas, y ese tipo de descuido ya ha costado cuatro fallos
     * reales en este sistema.
     */
    public static function desdeColumnas(?string $tecnica, ?string $financiera): self
    {
        $tecnica = self::normalizar($tecnica);
        $financiera = self::normalizar($financiera);

        if ($tecnica === 'SI' && $financiera === 'SI') {
            return self::Aprobada;
        }

        if ($financiera === 'NO') {
            return self::RechazadaFinanciera;
        }

        if ($tecnica === 'NO') {
            return self::RechazadaTecnica;
        }

        if ($tecnica === 'SI') {
            return self::EsperandoFinanciera;
        }

        if ($tecnica === 'PENDIENTE') {
            return self::EsperandoTecnica;
        }

        return self::Borrador;
    }

    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = mb_strtoupper(trim($valor));

        return $valor === '' ? null : $valor;
    }

    /** Lo que ve el usuario. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EsperandoTecnica => 'Esperando aprobación técnica',
            self::RechazadaTecnica => 'Rechazada (técnica)',
            self::EsperandoFinanciera => 'Esperando aprobación financiera',
            self::RechazadaFinanciera => 'Rechazada (financiera)',
            self::Aprobada => 'Aprobada',
        };
    }

    /**
     * Version corta, para distintivos y columnas estrechas.
     *
     * 'Esperando aprobación financiera' no cabe en un badge y se corta a
     * media palabra.
     */
    public function etiquetaCorta(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EsperandoTecnica => 'En técnica',
            self::RechazadaTecnica => 'Rechazada (téc.)',
            self::EsperandoFinanciera => 'En financiera',
            self::RechazadaFinanciera => 'Rechazada (fin.)',
            self::Aprobada => 'Aprobada',
        };
    }

    /** Que significa el estado, para el texto de ayuda. */
    public function explicacion(): string
    {
        return match ($this) {
            self::Borrador => 'Todavía no se ha enviado a aprobación.',
            self::EsperandoTecnica => 'Esperando la decisión del gerente de la gerencia.',
            self::RechazadaTecnica => 'El gerente la devolvió al autor para corregirla.',
            self::EsperandoFinanciera => 'Aprobada por el gerente; esperando a la administración.',
            self::RechazadaFinanciera => 'La administración la devolvió al gerente.',
            self::Aprobada => 'Aprobada en los dos niveles. No admite cambios.',
        };
    }

    /** Color del distintivo en el panel. */
    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::EsperandoTecnica, self::EsperandoFinanciera => 'warning',
            self::RechazadaTecnica, self::RechazadaFinanciera => 'danger',
            self::Aprobada => 'success',
        };
    }

    /**
     * Una cotizacion aprobada esta cerrada.
     *
     * Es la unica regla que no admite excepcion por rol: lo que se aprobo
     * es lo que se envio al cliente.
     */
    public function estaCerrada(): bool
    {
        return $this === self::Aprobada;
    }

    /** Esta esperando la decision de alguien. */
    public function estaEnTramite(): bool
    {
        return $this === self::EsperandoTecnica || $this === self::EsperandoFinanciera;
    }

    /**
     * Traduce el estado a una condicion SQL, para filtrar listados.
     *
     * Se hace aqui y no en la tabla para que la definicion del estado viva
     * en un solo sitio: si manana cambia una regla, cambia con ella el
     * filtro.
     *
     * Las columnas se comparan en mayusculas porque en produccion conviven
     * variantes del mismo valor.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\Cotizacion> $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Cotizacion>
     */
    public function aplicarFiltro(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $tecnica = 'UPPER(TRIM(COALESCE(aprobacion1, "")))';
        $financiera = 'UPPER(TRIM(COALESCE(aprobacion2, "")))';

        return match ($this) {
            self::Aprobada => $query
                ->whereRaw("{$tecnica} = 'SI'")
                ->whereRaw("{$financiera} = 'SI'"),

            self::RechazadaFinanciera => $query->whereRaw("{$financiera} = 'NO'"),

            self::RechazadaTecnica => $query
                ->whereRaw("{$tecnica} = 'NO'")
                ->whereRaw("{$financiera} != 'NO'"),

            self::EsperandoFinanciera => $query
                ->whereRaw("{$tecnica} = 'SI'")
                ->whereRaw("{$financiera} NOT IN ('SI', 'NO')"),

            self::EsperandoTecnica => $query
                ->whereRaw("{$tecnica} = 'PENDIENTE'")
                ->whereRaw("{$financiera} != 'NO'"),

            self::Borrador => $query
                ->whereRaw("{$tecnica} NOT IN ('SI', 'NO', 'PENDIENTE')")
                ->whereRaw("{$financiera} != 'NO'"),
        };
    }
}
