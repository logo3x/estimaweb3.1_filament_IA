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
class User extends Authenticatable implements FilamentUser, \Filament\Models\Contracts\HasName
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
     * El nombre que se ve en el menu de arriba, con el rol al lado.
     *
     * Quien entra tiene que saber con que permisos esta trabajando: seis
     * personas acumulan varios roles -una llega a cuatro- y lo que pueden
     * hacer cambia segun cual pese mas.
     */
    public function getFilamentName(): string
    {
        $rol = $this->rolPrincipal();

        return $rol ? "{$this->name} · {$rol}" : $this->name;
    }

    /**
     * El rol de mas autoridad de los que tiene.
     *
     * Los roles SUMAN: quien es Creador y Administrador puede lo de ambos.
     * Mostrarlos todos haria el menu ilegible -hay quien tiene cuatro-, y
     * mostrar el primero que devuelva la base seria arbitrario. Se toma el
     * de mayor peso, que es el que explica lo que esa persona alcanza.
     *
     * El orden es el de ROLES_POR_AUTORIDAD, que no es el mismo de
     * ROLES_CON_ACCESO_AL_PANEL: aquella lista dice quien entra, no quien
     * manda. Alli Creador va antes que Gerente, y un gerente salia como
     * "Creador" pese a ser quien aprueba tecnicamente.
     */
    public function rolPrincipal(): ?string
    {
        $suyos = $this->getRoleNames();

        foreach (self::ROLES_POR_AUTORIDAD as $rol) {
            if ($suyos->contains($rol)) {
                return self::ETIQUETAS_DE_ROL[$rol] ?? $rol;
            }
        }

        return $suyos->first();
    }

    /** Todos sus roles, para cuando hace falta el detalle. */
    public function todosSusRoles(): string
    {
        return $this->getRoleNames()
            ->map(fn (string $rol): string => self::ETIQUETAS_DE_ROL[$rol] ?? $rol)
            ->implode(' · ');
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
     * Los roles ordenados de mas a menos autoridad.
     *
     * No coincide con ROLES_CON_ACCESO_AL_PANEL, que solo dice quien
     * entra. Aqui manda lo que cada uno decide: la administracion aprueba
     * el segundo nivel, el gerente el primero, y creador y director
     * cotizan.
     *
     * @var list<string>
     */
    public const ROLES_POR_AUTORIDAD = [
        'super_admin',
        'Administrador',
        'Gerente',
        'Director',
        'Creador',
    ];

    /**
     * Como se nombra cada rol en pantalla.
     *
     * `super_admin` viene asi de la base -es el nombre tecnico del paquete
     * de permisos- y no se puede renombrar sin tocar los datos, pero
     * tampoco hace falta mostrarlo tal cual.
     *
     * @var array<string, string>
     */
    public const ETIQUETAS_DE_ROL = [
        'super_admin' => 'Administrador total',
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
