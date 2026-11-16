<?php

namespace App\Policies;

/**
 * Permisos sobre las cajas de herramientas.
 *
 * Las reglas estan en {@see CajaPrecargadaPolicy}: cada quien toca solo
 * las suyas. Laravel descubre las policies por nombre, de ahi que haga
 * falta una clase por modelo.
 */
class PrecargadoHerramientaPolicy extends CajaPrecargadaPolicy
{
}
