<?php

namespace App\Filament\Resources\Cotizacions;

use App\Dominio\DuplicarCotizacion;
use App\Dominio\Permisos\AlcanceDeCotizaciones;
use App\Dominio\Permisos\Capacidad;
use App\Dominio\Permisos\Permisos;
use App\Models\Cotizacion;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

/**
 * "Duplicar": empezar una oferta nueva a partir de otra.
 *
 * La misma accion en el listado, en la consulta y en la edicion. Sirve
 * tambien con las aprobadas, que es justo cuando hace falta: son las que
 * no se pueden editar y las que se tienen como modelo.
 */
final class AccionDuplicar
{
    public static function make(): Action
    {
        return Action::make('duplicar')
            ->label('Duplicar')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->visible(fn (Cotizacion $record): bool => self::puede($record))
            ->modalHeading('Duplicar cotización')
            ->modalDescription('Se crea una cotización nueva en borrador, con los mismos ítems, recursos y AIU. La original no cambia.')
            ->modalSubmitActionLabel('Crear la copia')
            ->modalWidth('lg')
            ->fillForm(fn (Cotizacion $record): array => [
                'codigo' => DuplicarCotizacion::codigoSugerido(),
                'nombre' => $record->nombre,
            ])
            ->schema([
                TextInput::make('codigo')
                    ->label('Código de la copia')
                    ->required()
                    ->maxLength(255)
                    ->rule(Rule::unique('cotizaciones', 'codigo'))
                    ->validationMessages(['unique' => 'Ya hay una cotización con ese código.']),
                TextInput::make('nombre')
                    ->label('Nombre del servicio')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Cotizacion $record, array $data): void {
                /** Esconder el boton no es proteccion. */
                abort_unless(self::puede($record), 403);

                $copia = DuplicarCotizacion::crear()->duplicar($record, auth()->user(), $data['codigo'], $data['nombre']);

                Notification::make()
                    ->success()
                    ->title('Copia creada')
                    ->body("Está trabajando en {$copia->codigo}. La original ({$record->codigo}) no cambió.")
                    ->send();

                redirect(CotizacionResource::getUrl('edit', ['record' => $copia]));
            });
    }

    /** Quien puede cotizar y ve la original. */
    private static function puede(Cotizacion $cotizacion): bool
    {
        $usuario = auth()->user();

        return (new Permisos)->puede($usuario, Capacidad::CrearCotizaciones)
            && (new AlcanceDeCotizaciones)->puedeVer($cotizacion, $usuario);
    }
}
