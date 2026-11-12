<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listado de usuarios.
 *
 * Nunca se muestra la contrasena, ni siquiera su hash: un listado acaba en
 * capturas de pantalla y exportaciones.
 */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(','),

                TextColumn::make('gerenciaRelacion.nombre')
                    ->label('Gerencia')
                    ->badge()
                    ->placeholder('Sin gerencia')
                    ->sortable(),

                /**
                 * Si el usuario puede entrar al panel.
                 *
                 * Tener un rol no basta: debe ser uno de los previstos. Verlo
                 * aqui evita el desconcierto de una cuenta que existe pero no
                 * deja entrar.
                 */
                IconColumn::make('acceso')
                    ->label('Accede')
                    ->boolean()
                    ->alignCenter()
                    ->state(fn (User $record): bool => $record->hasAnyRole(User::ROLES_CON_ACCESO_AL_PANEL))
                    ->tooltip('Tiene un rol que da acceso al panel'),

                TextColumn::make('cotizaciones_count')
                    ->label('Cotizaciones')
                    ->counts('cotizaciones')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Alta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('id_gerencia')
                    ->label('Gerencia')
                    ->relationship('gerenciaRelacion', 'nombre'),
            ])
            ->recordActions([
                EditAction::make(),
                /**
                 * Borrar un usuario esta restringido en la base: sus
                 * cotizaciones y registros de envio dejarian de tener autor.
                 */
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->cotizaciones()->doesntExist())
                    ->requiresConfirmation()
                    ->modalDescription('Solo se pueden borrar usuarios sin cotizaciones a su nombre.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ])
            ->defaultSort('name')
            ->striped()
            /** Evita una consulta de roles por fila. */
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['roles', 'gerenciaRelacion']));
    }
}
