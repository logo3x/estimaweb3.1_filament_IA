<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
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
        $this->registrarCorreoPorGraph();
    }

    /**
     * Registra el transporte de correo por Microsoft 365.
     *
     * Laravel no trae uno: se añade aqui para que `MAIL_MAILER=graph`
     * funcione como cualquier otro.
     */
    private function registrarCorreoPorGraph(): void
    {
        Mail::extend('graph', fn (array $configuracion): \App\Correo\TransporteMicrosoftGraph => new \App\Correo\TransporteMicrosoftGraph(
            tenantId: (string) ($configuracion['tenant_id'] ?? ''),
            clientId: (string) ($configuracion['client_id'] ?? ''),
            clientSecret: (string) ($configuracion['client_secret'] ?? ''),
            remitente: (string) ($configuracion['sender'] ?? ''),
        ));
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
