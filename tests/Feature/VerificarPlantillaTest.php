<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El comando que revisa una plantilla antes de usarla.
 *
 * La plantilla real lleva el membrete de la empresa y vive solo en el
 * servidor, así que no se puede probar hasta tenerla delante. Mejor
 * descubrir con este comando que un marcador se llama distinto, que
 * enviarle al cliente una oferta sin la tabla de precios.
 */
class VerificarPlantillaTest extends TestCase
{
    /** La plantilla instalada encaja con lo que el sistema rellena. */
    public function test_la_plantilla_instalada_encaja(): void
    {
        $this->artisan('plantilla:verificar')
            ->assertSuccessful();
    }

    /** Un archivo que no existe se avisa, no revienta. */
    public function test_avisa_si_el_archivo_no_existe(): void
    {
        $this->artisan('plantilla:verificar', ['archivo' => 'no-existe.docx'])
            ->assertFailed();
    }

    /**
     * Un archivo que no es un .docx tampoco pasa.
     *
     * Alguien puede renombrar un PDF por error.
     */
    public function test_rechaza_lo_que_no_es_un_docx(): void
    {
        $falso = storage_path('app/plantillas/falso.docx');
        file_put_contents($falso, 'esto no es un documento de Word');

        try {
            $this->artisan('plantilla:verificar', ['archivo' => $falso])
                ->assertFailed();
        } finally {
            @unlink($falso);
        }
    }
}
