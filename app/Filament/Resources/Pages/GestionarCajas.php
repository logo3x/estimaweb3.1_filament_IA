<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Listado y edicion de las cajas propias, en una sola pantalla.
 *
 * Son pocas y cortas: abrir una pagina aparte para cambiarle el nombre a una
 * lista de articulos no compensa.
 *
 * Esta clase existe para una sola cosa: los articulos elegidos **no son una
 * columna**, viven en una tabla aparte, de modo que hay que escribirlos a
 * mano despues de guardar la caja.
 */
abstract class GestionarCajas extends ManageRecords
{
    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nueva caja')
                /** El selector de articulos necesita ancho: son nombres largos. */
                ->modalWidth('3xl')
                /**
                 * Titulo escrito a mano.
                 *
                 * Filament lo compone con la etiqueta del modelo y capitaliza
                 * cada palabra: "Crear Caja De EPP". El de la pagina ya se
                 * corrige en getTitle(); este es el del modal.
                 */
                ->modalHeading('Nueva caja')
                ->modalSubmitActionLabel('Crear caja')
                ->using(fn (array $data): Model => $this->crearCaja($data))
                ->successNotificationTitle('Caja creada'),
        ];
    }

    /**
     * Crea la caja y escribe sus articulos.
     *
     * @param  array<string, mixed>  $data
     */
    protected function crearCaja(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            /** La caja es de quien la crea; no se pregunta. */
            $caja = static::getResource()::getModel()::create([
                'nombre' => $data['nombre'],
                'id_user' => auth()->id(),
            ]);

            $this->guardarArticulos($caja, $data['articulos'] ?? []);

            return $caja;
        });
    }

    /**
     * Reemplaza los articulos de la caja.
     *
     * Se borra y se vuelve a escribir: los elementos no tienen identidad
     * propia -son solo una referencia al catalogo-, asi que casar cada fila
     * con la anterior no aportaria nada.
     *
     * @param  array<int, mixed>  $articulos
     */
    protected function guardarArticulos(Model $caja, array $articulos): void
    {
        $columna = $caja::columnaDelCatalogo();

        $caja->elementos()->delete();

        foreach (array_filter($articulos) as $idCatalogo) {
            $caja->elementos()->create([$columna => $idCatalogo]);
        }
    }
}
