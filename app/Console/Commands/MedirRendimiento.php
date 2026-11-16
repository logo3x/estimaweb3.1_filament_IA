<?php

namespace App\Console\Commands;

use App\Dominio\CalcularCotizacion;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mide cuanto tarda y cuantas consultas hace cada pantalla.
 *
 * El sistema actual tarda 35 segundos en frio y 2-4 en caliente al abrir
 * una cotizacion. Este comando da el numero equivalente aqui, y sobre todo
 * **cuantas consultas** lanza cada pantalla: una que crece con el numero de
 * filas es el problema que no se ve hasta que hay datos de verdad.
 */
class MedirRendimiento extends Command
{
    protected $signature = 'rendimiento:medir
        {--cotizacion=55 : Cotización sobre la que medir}
        {--repeticiones=3 : Veces que se repite cada medición}';

    protected $description = 'Mide el tiempo y las consultas de las pantallas principales';

    /** Consultas que se consideran demasiadas para una sola pantalla. */
    private const LIMITE_CONSULTAS = 60;

    public function handle(): int
    {
        $id = (int) $this->option('cotizacion');
        $cotizacion = Cotizacion::find($id);

        if (! $cotizacion) {
            $this->error("No existe la cotización {$id}.");

            return self::FAILURE;
        }

        $this->avisarSiHayPocosDatos($cotizacion);

        $usuario = $this->usuarioParaMedir($cotizacion);

        if (! $usuario) {
            $this->error('No hay ningún usuario con acceso al panel.');

            return self::FAILURE;
        }

        auth()->login($usuario);

        $this->components->info("Midiendo sobre la cotización {$id} como {$usuario->name}.");
        $this->newLine();

        $filas = [];
        $hayProblemas = false;

        foreach ($this->mediciones($cotizacion) as $titulo => $accion) {
            [$ms, $consultas] = $this->medir($accion);

            $excesivas = $consultas > self::LIMITE_CONSULTAS;
            $hayProblemas = $hayProblemas || $excesivas;

            $filas[] = [
                $titulo,
                number_format($ms, 0) . ' ms',
                $excesivas
                    ? "<fg=red>{$consultas}</>"
                    : "<fg=green>{$consultas}</>",
            ];
        }

        $this->table(['Operación', 'Tiempo', 'Consultas'], $filas);

        $this->line('El tiempo es la mediana de ' . $this->option('repeticiones') . ' repeticiones.');
        $this->line('Más de ' . self::LIMITE_CONSULTAS . ' consultas en una pantalla suele ser un N+1.');

        return $hayProblemas ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Las operaciones que se miden.
     *
     * @return array<string, callable>
     */
    private function mediciones(Cotizacion $cotizacion): array
    {
        return [
            'Listado de cotizaciones' => fn (): mixed => \App\Filament\Resources\Cotizacions\CotizacionResource::getEloquentQuery()
                ->limit(10)
                ->get()
                ->each(fn (Cotizacion $c) => $c->estadoAprobacion()),

            'Cálculo del precio' => fn (): mixed => CalcularCotizacion::crear()->para($cotizacion->fresh()),

            'Recursos de la cotización' => fn (): mixed => $cotizacion->fresh()->load([
                'items', 'personals', 'herramientas.items', 'equipos.items',
                'consumibles.items', 'vehiculos', 'oficinas', 'aseos',
                'viaticos', 'servicios', 'comunicaciones', 'otros',
            ]),

            'Catálogo de herramientas (782)' => fn (): mixed => \App\Models\DatosHerramienta::query()
                ->orderBy('nombre')
                ->limit(25)
                ->get(),

            'Opciones del catálogo completo' => fn (): mixed => \App\Models\DatosHerramienta::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'precio']),
        ];
    }

    /**
     * Cuanto tarda y cuantas consultas hace.
     *
     * Se toma la mediana y no la media: una sola repeticion lenta -por el
     * disco, por otro proceso- desplazaria la media y haria creer que hay
     * un problema donde no lo hay.
     *
     * @return array{float, int}
     */
    private function medir(callable $accion): array
    {
        $tiempos = [];
        $consultas = 0;

        for ($i = 0; $i < (int) $this->option('repeticiones'); $i++) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $inicio = microtime(true);
            $accion();
            $tiempos[] = (microtime(true) - $inicio) * 1000;

            $consultas = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        sort($tiempos);

        return [$tiempos[intdiv(count($tiempos), 2)], $consultas];
    }

    /**
     * Medir sobre una cotizacion vacia no dice nada.
     *
     * El problema aparece con datos: una pantalla puede ir sobrada con tres
     * filas y caerse con trescientas.
     */
    private function avisarSiHayPocosDatos(Cotizacion $cotizacion): void
    {
        $recursos = $cotizacion->items()->count()
            + $cotizacion->personals()->count()
            + $cotizacion->herramientas()->count();

        if ($recursos < 5) {
            $this->components->warn(
                "La cotización {$cotizacion->id} tiene pocos recursos ({$recursos}): "
                . 'los tiempos no serán representativos. Pruebe con --cotizacion=55.'
            );
        }
    }

    /** Un administrador: ve todas las cotizaciones, que es el caso mas pesado. */
    private function usuarioParaMedir(Cotizacion $cotizacion): ?User
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Administrador'))
            ->first()
            ?? User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', User::ROLES_CON_ACCESO_AL_PANEL))
                ->first();
    }
}
