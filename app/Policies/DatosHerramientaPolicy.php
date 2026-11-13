<?php

namespace App\Policies;

/**
 * Permisos del catalogo. La regla esta en PolicyDeCatalogo, que comparten
 * los ocho: se consulta para cotizar, lo modifica administracion o gerente.
 */
class DatosHerramientaPolicy extends PolicyDeCatalogo
{
}
