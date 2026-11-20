<?php

/**
 * Congela, linea por linea, lo que calcula el sistema ACTUAL (estimaweb3).
 *
 * Los golden masters de cotizacion-*.json comparan totales e items. Este
 * archivo baja un nivel: cada revision de cada cotizacion (lo que aporta cada
 * persona, herramienta, equipo, consumible...) y el costo de cada persona. Lo
 * consume LineasDelCalculoTest.
 *
 * Se ejecuta el motor del sistema actual, no se leen columnas de su base: las
 * columnas guardan totales de versiones anteriores del codigo.
 *
 * El motor actual GUARDA los totales al terminar de calcular
 * (guardarResultados), asi que cada cotizacion se calcula dentro de una
 * transaccion que se revierte. El script comprueba al final que la base quedo
 * igual y falla si no.
 *
 * No lleva nombres: cada linea se identifica por su revision. Los nombres de
 * recursos y servicios identifican a los clientes.
 *
 * Uso, desde la raiz de este proyecto:
 *   php tests/golden/generar-lineas.php C:/wamp64/www/estimaweb3_filament
 *
 * Regenerar SOLO si un cambio de numeros es deliberado y se reviso uno por uno.
 */
[, $raizViejo] = $argv + [null, null];

if (! $raizViejo || ! is_file($raizViejo.'/app/Services/AiuCalculationService.php')) {
    fwrite(STDERR, "Indique la carpeta del sistema actual (la que tiene app/Services/AiuCalculationService.php).\n");
    exit(1);
}

use App\Models\Cotizacione;
use App\Models\Revision;
use App\Services\AiuCalculationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** Los `use` van antes: en un script suelto solo valen para lo que viene despues. */
require $raizViejo.'/vendor/autoload.php';
$app = require $raizViejo.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Cotizacione::disableAuditing();

/**
 * Diferencias DELIBERADAS de EstimaWeb 3.1, con el valor esperado calculado
 * aqui con la formula a mano (no con el motor nuevo, que es lo que se prueba).
 */
const INTERES = [
    '1' => 0.0525865366152938, '1.5' => -0.123611063, '2' => -0.164056738, '2.5' => -0.17560827,
    '3' => -0.175717616, '3.5' => -0.170121406, '4' => -0.161283177, '4.5' => -0.150440088,
    '5' => -0.138282094, '5.5' => -0.125224202, '6' => -0.111530726, '6.5' => -0.097377691,
    '7' => -0.082886587, '7.5' => -0.068143731, '8' => -0.053211934, '8.5' => -0.038137811,
    '9' => -0.022956528, '9.5' => -0.007694981, '10' => 0.007626037, '10.5' => 0.022990296,
    '11' => 0.03838505, '11.5' => 0.053800229, '12' => 0.069227858, '12.5' => 0.084661607,
    '13' => 0.100096463, '13.5' => 0.115528462, '14' => 0.130954492, '14.5' => 0.146372136,
    '15' => 0.161779541, '15.5' => 0.177175321, '16' => 0.192558475, '16.5' => 0.207928323,
    '17' => 0.223284448, '17.5' => 0.238626656, '18' => 0.253954937, '18.5' => 0.269269436,
    '19' => 0.284570427, '19.5' => 0.29985829, '20' => 0.315133497, '20.5' => 0.330396594,
    '21' => 0.345648187, '21.5' => 0.360888935, '22' => 0.376119536, '22.5' => 0.391340724,
    '23' => 0.406553257, '23.5' => 0.421757915, '24' => 0.436955493, '24.5' => 0.452146796,
    '25' => 0.467332639,
];

/** Costo de un activo por unidad, con la formula del sistema actual y el interes dado. */
function costoDeActivo(object $activo, iterable $componentes, string $unidad, float $interes): float
{
    $compra = 0.0;
    foreach ($componentes as $c) {
        if ((int) $c['cantidad'] > 0) {
            $compra += (int) $c['cantidad'] * (float) $c['precio'];
        }
    }

    $periodo = (float) $activo->periodo_evaluacion;
    if ($compra <= 0 || $periodo <= 0) {
        return 0.0;
    }

    $mes = $compra / (12 * $periodo) * (1 + $interes) * (1.1 ** (1 / 12))
        + $compra * ((float) $activo->vrepo_anual / 100) / 12
        + $compra * ((float) $activo->vcalibracion_anual / 100) / 12;

    return match ($unidad) {
        'Mes' => $mes,
        'Dia Habil', 'Dia Calendario' => $mes / ((365 - 52 - 52 - 18) / 12),
        default => 0.0,
    };
}

$fotoDeLaBase = fn () => DB::table('cotizaciones')->orderBy('id')
    ->get(['id', 'valor_total', 'valor_total_con_aiu', 'aiu_valortotal_con_iva', 'updated_at'])
    ->map(fn ($f) => (array) $f)->all();

$antes = $fotoDeLaBase();
$salida = ['cotizaciones' => [], 'diferencias_deliberadas' => []];

foreach (Cotizacione::query()->orderBy('id')->get() as $cotizacion) {
    DB::beginTransaction();

    try {
        $servicio = new AiuCalculationService($cotizacion);
        $resultado = $servicio->calcularPrevisualacion();

        /**
         * El motor actual emite una linea por revision, recorriendo los items
         * y, dentro de cada item, las revisiones en el orden de la consulta.
         * Se reproduce ese recorrido para saber de que revision sale cada
         * linea, y se comprueba que la categoria y la cantidad coinciden.
         */
        $revisionesPorItem = Revision::where('id_cotizacion', $cotizacion->id)->get()->groupBy('id_item_cotizacion');
        $orden = [];
        foreach ($resultado['pre_item_cotizaciones'] as $item) {
            foreach ($revisionesPorItem->get($item->id, collect()) as $revision) {
                $orden[] = $revision;
            }
        }

        $lineas = $resultado['vista_resumen'];
        if (count($lineas) !== count($orden)) {
            throw new RuntimeException("Cotizacion {$cotizacion->id}: ".count($lineas).' lineas para '.count($orden).' revisiones.');
        }

        $congeladas = [];
        foreach ($lineas as $i => $linea) {
            $revision = $orden[$i];

            if ((int) $linea['id_item_cotizacion'] !== (int) $revision->id_item_cotizacion
                || abs((float) $linea['cantidad'] - (float) $revision->cantidad) > 1e-9) {
                throw new RuntimeException("Cotizacion {$cotizacion->id}: la linea {$i} no corresponde a la revision {$revision->id}.");
            }

            $congeladas[] = [
                'revision' => (int) $revision->id,
                'item' => (int) $revision->id_item_cotizacion,
                'categoria' => $linea['categoria'],
                'cantidad' => (float) $linea['cantidad'],
                'unidad' => (string) $linea['unidad'],
                'valor_unitario' => round((float) $linea['valor_unitario'], 6),
                'aporte' => round((float) $linea['valor_parcial'], 6),
            ];

            /** Herramientas y equipos: el 3.1 aplica el interes que aqui siempre era cero. */
            $activo = $revision->id_herramienta
                ? DB::table('herramientas')->find($revision->id_herramienta)
                : ($revision->id_equipo ? DB::table('equipos')->find($revision->id_equipo) : null);

            if ($activo) {
                $componentes = $revision->id_herramienta
                    ? DB::table('item_herramientas as i')->join('datos_herramientas as d', 'd.id', '=', 'i.id_datos_herramientas')
                        ->where('i.id_herramienta', $activo->id)->get(['i.cantidad', 'd.precio'])
                    : DB::table('item_equipos as i')->join('datos_equipos as d', 'd.id', '=', 'i.id_datos_equipo')
                        ->where('i.id_equipo', $activo->id)->get(['i.cantidad', 'd.precio']);
                $componentes = $componentes->map(fn ($c) => (array) $c)->all();

                $clave = rtrim(rtrim(number_format((float) $activo->periodo_evaluacion, 2, '.', ''), '0'), '.');
                $unitario = costoDeActivo($activo, $componentes, (string) $revision->unidad, INTERES[$clave] ?? 0.0);
                $aporte = $unitario * (float) $revision->cantidad * (int) $resultado['pre_item_cotizaciones']->firstWhere('id', $revision->id_item_cotizacion)->cantidad;

                $negativo = collect($componentes)->contains(fn ($c) => (int) $c['cantidad'] < 0);

                if (abs($unitario - (float) $linea['valor_unitario']) > 0.005) {
                    $salida['diferencias_deliberadas'][(string) $revision->id] = [
                        'cotizacion' => (int) $cotizacion->id,
                        'motivo' => $negativo
                            ? 'Componente con cantidad negativa: el sistema actual lo RESTABA del precio; el 3.1 no lo cobra y avisa.'
                            : 'Interes constante: el sistema actual nunca lo encontraba en su tabla (periodo guardado como "'.$activo->periodo_evaluacion.'") y aplicaba cero.',
                        'valor_unitario' => round($unitario, 6),
                        'aporte' => round($aporte, 6),
                    ];
                }
            }
        }

        $personas = [];
        foreach ($servicio->_personales ?? [] as $personal) {
            $metodo = new ReflectionMethod($servicio, $personal->rol == 'Rol Mensual' ? 'calcularCostoPersonalMensual' : 'calcularCostoPersonalDiario');
            $costo = $metodo->invoke($servicio, $personal);
            $personas[(string) $personal->id] = [
                'por_mes' => round((float) $costo['valor_costo_personal_mes'], 6),
                'por_dia_calendario' => round((float) $costo['valor_costo_personal_dia'], 6),
                'por_dia_habil' => round((float) $costo['valor_costo_personal_dia_habil'], 6),
            ];
        }

        $salida['cotizaciones'][(string) $cotizacion->id] = ['lineas' => $congeladas, 'personas' => $personas];
    } finally {
        DB::rollBack();
    }
}

if ($fotoDeLaBase() !== $antes) {
    fwrite(STDERR, "ALERTA: la base del sistema actual cambio durante la generacion.\n");
    exit(2);
}

$destino = __DIR__.'/lineas.json';
file_put_contents($destino, json_encode($salida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)."\n");

$totalLineas = array_sum(array_map(fn ($c) => count($c['lineas']), $salida['cotizaciones']));
echo count($salida['cotizaciones'])." cotizaciones, {$totalLineas} lineas, "
    .count($salida['diferencias_deliberadas'])." diferencias deliberadas. Base intacta.\n";
