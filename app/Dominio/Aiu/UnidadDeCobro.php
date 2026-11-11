<?php

namespace App\Dominio\Aiu;

/**
 * Unidad en que se cobra un recurso dentro de una cotizacion.
 *
 * Existe como enum, y no como texto suelto, por un fallo concreto del sistema
 * actual: alli la unidad se compara con cadenas en tres `if` encadenados sin
 * `else`, de modo que una unidad no prevista -- "Persona", "N/A" -- no entra
 * en ninguna rama y el recurso aporta CERO sin aviso. La cotizacion 7 tiene
 * 15 recursos bien cargados (salarios de 3,2 y 5,5 millones) y da un total de
 * cero; la 6 esta parcialmente afectada.
 *
 * Es mas dificil de detectar que un error visible: nadie ve un mensaje, solo
 * un precio mas bajo de lo que deberia.
 *
 * Con un enum, una unidad desconocida falla al construirse en vez de
 * desaparecer en silencio.
 */
enum UnidadDeCobro: string
{
    case DiaHabil = 'Dia Habil';
    case DiaCalendario = 'Dia Calendario';
    case Mes = 'Mes';

    /**
     * Unidades que aparecen en los datos pero que el calculo nunca reconocio.
     *
     * Se listan para poder detectarlas y avisar, no para darlas por validas.
     *
     * @var list<string>
     */
    public const NO_RECONOCIDAS = ['Persona', 'N/A', 'Unidad', 'Global'];

    /**
     * Construye la unidad desde el texto guardado en la base.
     *
     * Devuelve null si el texto no corresponde a ninguna unidad de cobro
     * conocida; quien llama decide si eso es un aviso o un error, pero ya no
     * puede ignorarlo por accidente.
     */
    public static function desdeTexto(?string $texto): ?self
    {
        if ($texto === null) {
            return null;
        }

        return self::tryFrom(trim($texto));
    }

    /** Si el texto corresponde a una unidad con la que se puede calcular. */
    public static function esCalculable(?string $texto): bool
    {
        return self::desdeTexto($texto) !== null;
    }

    /** Nombre para mostrar en pantalla. */
    public function etiqueta(): string
    {
        return match ($this) {
            self::DiaHabil => 'Día hábil',
            self::DiaCalendario => 'Día calendario',
            self::Mes => 'Mes',
        };
    }

    /**
     * Opciones para los desplegables del formulario.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $unidad) {
            $opciones[$unidad->value] = $unidad->etiqueta();
        }

        return $opciones;
    }
}
