<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema.
 *
 * Implementa FilamentUser a proposito: sin esa interfaz y su canAccessPanel(),
 * el panel deja entrar en local pero responde 403 despues del login en
 * produccion, que fue un fallo real del sistema anterior.
 *
 * La gerencia viaja duplicada (id_gerencia y gerencia en texto) porque asi
 * esta en produccion; usar la relacion y no la cadena.
 */
#[Fillable(['name', 'email', 'password', 'id_gerencia', 'gerencia', 'avatar_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Roles que pueden entrar al panel.
     *
     * Es una lista explicita a proposito: antes bastaba con tener cualquier
     * rol, de modo que un rol creado para otra cosa daba acceso completo sin
     * que nadie lo decidiera. Anadir un rol aqui es una decision consciente.
     *
     * @var list<string>
     */
    public const ROLES_CON_ACCESO_AL_PANEL = [
        'super_admin',
        'Administrador',
        'Creador',
        'Gerente',
        'Director',
    ];

    /**
     * Quien puede entrar al panel.
     *
     * Esta regla vive aqui, y no en un middleware suelto, para que local y
     * produccion se comporten igual: sin ella, el panel deja entrar en local
     * y responde 403 despues del login en produccion.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(self::ROLES_CON_ACCESO_AL_PANEL);
    }

    /** @return BelongsTo<Gerencia, $this> */
    public function gerenciaRelacion(): BelongsTo
    {
        return $this->belongsTo(Gerencia::class, 'id_gerencia');
    }

    /** Cotizaciones creadas por este usuario. */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class, 'id_user');
    }
}
