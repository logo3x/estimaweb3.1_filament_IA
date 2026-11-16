<?php

namespace App\Policies;

use App\Models\CajaPrecargada;
use App\Models\User;

/**
 * Permisos sobre las cajas precargadas.
 *
 * Son listas de trabajo personales: quien entra al panel puede tener las
 * suyas, y nadie toca las de otro. No dependen del rol -- un creador las
 * necesita tanto como un gerente -- sino de ser el dueño.
 *
 * El filtrado del listado va aparte, en el propio recurso. Esto cubre el
 * caso de alcanzar una caja concreta por su direccion.
 */
abstract class CajaPrecargadaPolicy
{
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function create(User $usuario): bool
    {
        return true;
    }

    public function view(User $usuario, CajaPrecargada $caja): bool
    {
        return $this->esSuya($usuario, $caja);
    }

    public function update(User $usuario, CajaPrecargada $caja): bool
    {
        return $this->esSuya($usuario, $caja);
    }

    public function delete(User $usuario, CajaPrecargada $caja): bool
    {
        return $this->esSuya($usuario, $caja);
    }

    public function replicate(User $usuario, CajaPrecargada $caja): bool
    {
        return $this->esSuya($usuario, $caja);
    }

    public function deleteAny(User $usuario): bool
    {
        return true;
    }

    /**
     * Si la caja es de ese usuario.
     *
     * Las cajas sin dueño -- hay cuatro en los datos, de la copia de
     * produccion -- no son de nadie, asi que nadie las toca.
     */
    private function esSuya(User $usuario, CajaPrecargada $caja): bool
    {
        return filled($caja->id_user)
            && (int) $caja->id_user === (int) $usuario->id;
    }
}
