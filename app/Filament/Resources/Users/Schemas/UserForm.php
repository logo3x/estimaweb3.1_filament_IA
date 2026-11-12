<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Gerencia;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Alta y edicion de usuarios.
 *
 * La contrasena nunca se muestra, ni siquiera cifrada: al editar se deja en
 * blanco y solo se escribe si se quiere cambiar.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Correo')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Con este correo inicia sesión.'),

                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        /** Obligatoria al crear; opcional al editar. */
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                        ->helperText(fn (string $operation): string => $operation === 'create'
                            ? 'Mínimo 8 caracteres.'
                            : 'Déjela vacía para conservar la actual.')
                        ->columnSpanFull(),
                ]),

            Section::make('Permisos')
                ->columns(2)
                ->schema([
                    /**
                     * Los roles no son obligatorios: una cuenta puede
                     * crearse antes de decidir que permisos tendra. Pero sin
                     * un rol de la lista no se entra al panel, y eso se
                     * advierte aqui para que no haya que descubrirlo
                     * intentando iniciar sesion.
                     */
                    Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->helperText(
                            'Sin uno de estos roles la cuenta no entra al panel: '
                            . implode(', ', User::ROLES_CON_ACCESO_AL_PANEL)
                        )
                        ->columnSpanFull(),

                    Select::make('id_gerencia')
                        ->label('Gerencia')
                        ->relationship('gerenciaRelacion', 'nombre')
                        ->searchable()
                        ->preload()
                        ->live()
                        /**
                         * La gerencia viaja duplicada en la base, como clave
                         * foranea y como texto. Se mantienen sincronizadas
                         * para no dejar una de las dos obsoleta.
                         */
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $set('gerencia', $state
                                ? (string) Gerencia::query()->whereKey($state)->value('nombre')
                                : '');
                        }),

                    /**
                     * La columna es NOT NULL sin valor por defecto, asi que
                     * nunca puede quedar nula: una cuenta sin gerencia
                     * guarda cadena vacia, no null.
                     */
                    TextInput::make('gerencia')
                        ->label('Gerencia (texto)')
                        ->maxLength(255)
                        ->default('')
                        ->disabled()
                        ->dehydrated()
                        ->dehydrateStateUsing(fn (?string $state): string => (string) $state)
                        ->helperText('Se completa sola a partir de la gerencia elegida.'),
                ]),
        ]);
    }
}
