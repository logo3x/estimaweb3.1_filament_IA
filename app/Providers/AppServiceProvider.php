<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpWord\Settings as AjustesWord;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurarGeneracionDeWord();
    }

    /**
     * Escapa el texto que PhpWord escribe dentro del .docx.
     *
     * Un .docx es un ZIP con XML dentro. Sin esto, un ampersand en el nombre
     * de un cliente -- "Mantenimiento & Operaciones" -- sale crudo al XML,
     * el documento queda mal formado y Word se niega a abrirlo: el usuario
     * solo ve un archivo corrupto, sin ninguna pista de la causa.
     *
     * Paso en produccion y se corrigio alli el 2026-11-09. Aqui lo detecto
     * la prueba de caracteres especiales antes de que llegara a nadie.
     *
     * Tambien cubre los signos de menor y mayor que, y las comillas.
     */
    private function configurarGeneracionDeWord(): void
    {
        AjustesWord::setOutputEscapingEnabled(true);
    }
}
