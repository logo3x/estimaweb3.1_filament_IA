<?php

namespace App\Filament\Resources\Cotizacions\Pages;

use App\Filament\Resources\Cotizacions\CotizacionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Alta de una cotizacion.
 *
 * Solo pide los datos de cabecera. Los recursos y los items se cargan despues
 * de guardar, porque todos cuelgan de la cotizacion y necesitan su id.
 *
 * El sistema actual resuelve eso guardando el id en la sesion y ocultando las
 * pestanas hasta que aparece. Aqui se redirige a la pantalla de edicion, que
 * ya tiene el registro cargado: una pestana nunca puede quedar a medias
 * porque la sesion se perdio.
 */
class CreateCotizacion extends CreateRecord
{
    protected static string $resource = CotizacionResource::class;

    public function getTitle(): string
    {
        return 'Nueva cotización';
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Cotización creada. Ahora puede cargar ítems y recursos.';
    }

    /**
     * Tras crear, se continua en la pantalla de edicion.
     *
     * Es donde estan los items y los recursos, que es lo siguiente que hay
     * que hacer.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** Queda registrado quien la creo: decide quien puede editarla. */
        $data['id_user'] = auth()->id();
        $data['estado'] = $data['estado'] ?? 'En elaboración';

        return static::getModel()::create($data);
    }
}
