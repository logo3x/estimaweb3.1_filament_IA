<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Las cabeceras de seguridad llegan en las respuestas.
 *
 * Son defensas en profundidad: no arreglan un fallo del codigo, pero limitan
 * el dano si existe. La auditoria encontro que no habia ninguna.
 */
class CabecerasDeSeguridadTest extends TestCase
{
    /** Las cabeceras basicas acompanan a cualquier respuesta web. */
    public function test_las_respuestas_traen_las_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get('/admin/login');

        foreach ([
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ] as $cabecera => $valor) {
            $respuesta->assertHeader($cabecera, $valor);
        }

        $respuesta->assertHeader('Permissions-Policy');
        $respuesta->assertHeader('Content-Security-Policy');
    }

    /**
     * La politica de contenido cierra lo esencial.
     *
     * Filament y Livewire necesitan estilos y scripts en linea, de modo que
     * la politica no puede ser estricta del todo; lo que si debe impedir es
     * que un script venga de un dominio ajeno o que la pagina se incruste.
     */
    public function test_la_politica_de_contenido_restringe_el_origen(): void
    {
        $politica = $this->get('/admin/login')->headers->get('Content-Security-Policy');

        $this->assertNotNull($politica);

        foreach ([
            "default-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ] as $directiva) {
            $this->assertStringContainsString(
                $directiva,
                $politica,
                "Falta la directiva: {$directiva}"
            );
        }

        /** Ningun script puede cargarse desde un dominio externo. */
        $this->assertStringContainsString("script-src 'self'", $politica);
    }

    /**
     * HSTS solo viaja sobre HTTPS.
     *
     * Enviarla por http no tiene efecto, y en desarrollo local dejaria el
     * dominio bloqueado en el navegador durante un ano.
     */
    public function test_hsts_no_se_envia_sobre_http(): void
    {
        $this->get('/admin/login')->assertHeaderMissing('Strict-Transport-Security');
    }
}
