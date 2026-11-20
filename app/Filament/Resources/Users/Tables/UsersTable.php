<?php

namespace App\Filament\Resources\Users\Tables;

use App\Dominio\Suplantacion\Suplantacion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
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
                 * Ver el panel como lo ve esta persona.
                 *
                 * Resuelve un "a mi no me aparece" sin pedirle la
                 * contraseña, que es la alternativa y es peor: una clave
                 * compartida deja de identificar a quien actua.
                 *
                 * Reservado a la administracion total y auditado: durante
                 * ese rato se actua con los permisos del otro.
                 */
                Action::make('suplantar')
                    ->label('Entrar como')
                    ->icon('heroicon-m-user-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => 'Entrar como ' . $record->name)
                    ->modalDescription('Verá el panel con los permisos de esta persona. Queda registrado en la auditoría, y podrá volver a su cuenta desde el aviso de arriba.')
                    ->modalSubmitActionLabel('Entrar')
                    ->visible(fn (User $record): bool => app(Suplantacion::class)->puedeEntrarComo($record))
                    ->action(function (User $record) {
                        try {
                            app(Suplantacion::class)->entrarComo($record);
                        } catch (\DomainException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo entrar como esa persona')
                                ->body($e->getMessage())
                                ->send();

                            return null;
                        }

                        /** A la portada, ya con la otra identidad. */
                        return redirect('/admin');
                    }),
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
