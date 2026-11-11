<?php

namespace App\Dominio\Aiu;

/**
 * Unidad de tiempo en que se cobra un recurso.
 *
 * Solo aplica a los recursos cuyo precio DEPENDE del tiempo: personal,
 * herramientas y equipos. Los demas (vehiculos, servicios, viaticos, otros,
 * consumibles) cobran por cantidad y su unidad es solo una etiqueta, por eso
 * en los datos reales llevan "N/A" con toda normalidad.
 *
 * Existe como enum, y no como texto suelto, por un fallo del sistema actual:
 * alli la unidad se compara con cadenas en `if` encadenados sin `else`, de
 * modo que una unidad no prevista no entra en ninguna rama y el recurso
 * aporta CERO sin aviso. Nadie ve un mensaje de error: solo un precio mas
 * bajo del que corresponde.
 *
 * Alcance verificado sobre los datos reales: 13 revisiones en las
 * cotizaciones 6 y 7, ambas datos de prueba (contienen nombres en latin y
 * salarios de 90 pesos). Ninguna cotizacion real resulto afectada.
 */
enum UnidadDeCobro: string
{
    case DiaHabil = 'Dia Habil';
    case DiaCalendario = 'Dia Calendario';
    case Mes = 'Mes';

    /**
     * Unidades que aparecen en los datos pero con las que no se puede
     * calcular un precio por tiempo.
     *
     * Se listan para detectarlas y avisar, no para darlas por validas.
     *
     * @var list<string>
     */
    public const NO_CALCULABLES = ['Persona', 'N/A', 'Unidad', 'Global', 'EA'];

    /**
     * Recursos cuyo precio depende de la unidad de tiempo.
     *
     * Para el resto, la unidad no interviene en el calculo.
     *
     * @var list<string>
     */
    public const RECURSOS_POR_TIEMPO = ['personal', 'herramienta', 'equipo'];

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
