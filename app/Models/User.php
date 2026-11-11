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
     * Quien puede entrar al panel.
     *
     * Hoy basta con tener algun rol asignado. Afinar esta regla aqui, no en
     * middleware suelto, para que local y produccion se comporten igual.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
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
