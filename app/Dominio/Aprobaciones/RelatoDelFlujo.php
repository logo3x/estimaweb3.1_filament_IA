<?php

namespace App\Dominio\Aprobaciones;

use App\Models\Cotizacion;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * El recorrido de una cotizacion, contado para que se entienda.
 *
 * Quien abre una cotizacion ajena se pregunta dos cosas: **por que no puedo
 * editarla** y **a quien le toca ahora**. Esta clase responde a ambas con
 * datos que ya estaban en la base pero no se veian: la fecha de cada
 * decision -34 cotizaciones la tienen- y el comentario de quien decidio
 * -5 lo tienen, y es trabajo que alguien escribio y nadie leia-.
 *
 * Solo LEE. Quien ejecuta las transiciones es {@see FlujoDeAprobacion}.
 */
final readonly class RelatoDelFlujo
{
    public function __construct(private Cotizacion $cotizacion) {}

    public static function de(Cotizacion $cotizacion): self
    {
        return new self($cotizacion);
    }

    public function estado(): EstadoAprobacion
    {
        return $this->cotizacion->estadoAprobacion();
    }

    /**
     * Los tres pasos, en orden.
     *
     * @return list<PasoDelFlujo>
     */
    public function pasos(): array
    {
        return [
            $this->elaboracion(),
            $this->nivel(
                numero: 1,
                titulo: 'Revisión técnica',
                decision: $this->cotizacion->aprobacion1,
                usuarioId: $this->cotizacion->aprobacion1_user_id,
                fecha: $this->cotizacion->aprobacion1_fecha,
                comentario: $this->cotizacion->aprobacion1_comentarios,
                aQuienToca: 'El gerente de la gerencia',
            ),
            $this->nivel(
                numero: 2,
                titulo: 'Aprobación financiera',
                decision: $this->cotizacion->aprobacion2,
                usuarioId: $this->cotizacion->aprobacion2_user_id,
                fecha: $this->cotizacion->aprobacion2_fecha,
                comentario: $this->cotizacion->aprobacion2_comentarios,
                aQuienToca: 'La administración',
            ),
        ];
    }

    /**
     * Una frase con lo que hay que saber ahora mismo.
     *
     * Es lo primero que se lee, antes que los pasos.
     */
    public function resumen(): string
    {
        return match ($this->estado()) {
            EstadoAprobacion::Borrador => 'Todavía no se ha enviado a aprobación.',
            EstadoAprobacion::EsperandoTecnica => 'Esperando al gerente de la gerencia' . $this->desdeCuando(1) . '.',
            EstadoAprobacion::EsperandoFinanciera => 'Esperando a la administración' . $this->desdeCuando(2) . '.',
            EstadoAprobacion::RechazadaTecnica => 'El gerente la devolvió para corregirla.',
            EstadoAprobacion::RechazadaFinanciera => 'La administración la devolvió al gerente.',
            EstadoAprobacion::Aprobada => 'Aprobada en los dos niveles' . $this->desdeCuando(2) . '. Ya no admite cambios.',
        };
    }

    /**
     * Quien tiene la pelota.
     *
     * Vacio si ya no espera a nadie.
     */
    public function aQuienLeToca(): string
    {
        return match ($this->estado()) {
            EstadoAprobacion::Borrador => 'Al autor, que aún no la ha enviado',
            EstadoAprobacion::EsperandoTecnica => 'Al gerente de la gerencia',
            EstadoAprobacion::EsperandoFinanciera => 'A la administración',
            EstadoAprobacion::RechazadaTecnica => 'Al autor, para corregirla',
            EstadoAprobacion::RechazadaFinanciera => 'Al gerente, para corregirla',
            EstadoAprobacion::Aprobada => '',
        };
    }

    /**
     * Si a quien esta mirando le toca decidir ahora.
     *
     * Cambia el tono de la pantalla: no es lo mismo enterarse de que algo
     * esta parado que enterarse de que esta parado **esperandote**.
     */
    public function leTocaA(?User $usuario): bool
    {
        if (! $usuario) {
            return false;
        }

        $reglas = new ReglasDeAprobacion();

        return $reglas->puedeDecidirTecnica($this->cotizacion, $usuario)
            || $reglas->puedeDecidirFinanciera($this->cotizacion, $usuario);
    }

    private function elaboracion(): PasoDelFlujo
    {
        $autor = $this->cotizacion->autor?->name;

        return new PasoDelFlujo(
            titulo: 'Elaboración',
            /** Siempre hecho: si la cotizacion existe, alguien la escribio. */
            estado: 'hecho',
            quien: $autor ? 'La escribió ' . $autor : 'Sin autor registrado',
            cuando: $this->enPalabras($this->cotizacion->created_at),
            comentario: '',
            queFalta: '',
        );
    }

    private function nivel(
        int $numero,
        string $titulo,
        ?string $decision,
        ?int $usuarioId,
        mixed $fecha,
        ?string $comentario,
        string $aQuienToca,
    ): PasoDelFlujo {
        $estado = $this->estadoDelNivel($numero);
        $decision = mb_strtoupper(trim((string) $decision));

        return new PasoDelFlujo(
            titulo: $titulo,
            estado: $estado,
            quien: $this->quien($decision, $usuarioId, $aQuienToca),
            /** La fecha solo dice algo cuando ya se decidio. */
            cuando: in_array($estado, ['hecho', 'rechazado'], true)
                ? $this->enPalabras($fecha)
                : '',
            comentario: trim((string) $comentario),
            queFalta: $estado === 'actual' ? $this->queFalta($numero) : '',
        );
    }

    /**
     * Quien decidio, o a quien le toca.
     *
     * Ojo con `PENDIENTE`: es un valor real que escribe
     * {@see FlujoDeAprobacion::enviarAAprobacion()}, y antes se pintaba
     * crudo y en mayusculas en mitad de la pantalla.
     */
    private function quien(string $decision, ?int $usuarioId, string $aQuienToca): string
    {
        if ($decision === '' || $decision === 'PENDIENTE') {
            return $aQuienToca;
        }

        $nombre = $usuarioId
            ? User::query()->whereKey($usuarioId)->value('name')
            : null;

        /**
         * Las decisiones viejas no guardaron quien las tomo.
         *
         * 22 cotizaciones aprobadas vienen del sistema anterior sin
         * `aprobacion_user_id`. Decir «Aprobada» a secas es honesto;
         * inventar un nombre, no.
         */
        if (blank($nombre)) {
            return match ($decision) {
                'SI', 'SÍ' => 'Aprobada · no se registró quién',
                'NO' => 'Devuelta · no se registró quién',
                default => $aQuienToca,
            };
        }

        return match ($decision) {
            'SI', 'SÍ' => 'Aprobada por ' . $nombre,
            'NO' => 'Devuelta por ' . $nombre,
            default => $aQuienToca,
        };
    }

    /** Que tiene que pasar para que el flujo avance. */
    private function queFalta(int $numero): string
    {
        return $numero === 1
            ? 'Debe aprobarla o devolverla'
            : 'Debe dar el visto bueno final';
    }

    private function estadoDelNivel(int $numero): string
    {
        $estado = $this->estado();

        if ($numero === 1) {
            return match ($estado) {
                EstadoAprobacion::EsperandoTecnica => 'actual',
                EstadoAprobacion::RechazadaTecnica => 'rechazado',
                EstadoAprobacion::EsperandoFinanciera,
                EstadoAprobacion::RechazadaFinanciera,
                EstadoAprobacion::Aprobada => 'hecho',
                default => 'pendiente',
            };
        }

        return match ($estado) {
            EstadoAprobacion::EsperandoFinanciera => 'actual',
            EstadoAprobacion::RechazadaFinanciera => 'rechazado',
            EstadoAprobacion::Aprobada => 'hecho',
            default => 'pendiente',
        };
    }

    /**
     * Desde cuando espera, si se sabe.
     *
     * «Esperando al gerente desde hace 3 días» dice bastante mas que
     * «esperando al gerente»: es lo que vuelve accionable la pantalla.
     */
    private function desdeCuando(int $nivel): string
    {
        $texto = $this->enPalabras(
            $nivel === 1
                ? $this->cotizacion->aprobacion1_fecha
                : $this->cotizacion->aprobacion2_fecha
        );

        return $texto === '' ? '' : ' desde ' . $texto;
    }

    /** Una fecha como la diria una persona. */
    private function enPalabras(mixed $fecha): string
    {
        if (blank($fecha)) {
            return '';
        }

        $momento = $fecha instanceof CarbonInterface ? $fecha : Carbon::parse((string) $fecha);

        return $momento->diffForHumans();
    }
}
