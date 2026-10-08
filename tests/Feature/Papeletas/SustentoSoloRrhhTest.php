<?php

namespace Tests\Feature\Papeletas;

use App\Models\Sustento;
use App\States\Papeleta\EnJustificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El visto bueno de la justificación (Salud) lo da solo RRHH: ni el jefe
 * inmediato la revisa ni abre el archivo médico del trabajador.
 */
class SustentoSoloRrhhTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_solo_rrhh_revisa_el_sustento_y_el_jefe_no(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $jefe = $this->conJefeDePrueba($trabajador);
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $papeleta = $this->papeletaDePrueba($trabajador->fresh(), EnJustificacion::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
        ]);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
            'archivo_path' => 'papeletas/sustentos/certificado.pdf',
            'presentado_at' => now(),
        ]);

        $this->assertTrue($papeleta->tieneComoJefeInmediatoA($jefe), 'el jefe sí es el jefe inmediato');
        $this->assertFalse($jefe->can('revisarSustento', $sustento));
        $this->assertFalse($jefe->can('verSustento', $sustento), 'el jefe tampoco abre el archivo');
        $this->assertTrue($rrhh->can('revisarSustento', $sustento));
        $this->assertTrue($trabajador->can('verSustento', $sustento), 'el dueño ve su propio archivo');
    }

    public function test_el_jefe_ya_no_tiene_bandeja_de_sustentos_por_revisar(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $jefe = $this->conJefeDePrueba($trabajador);

        $papeleta = $this->papeletaDePrueba($trabajador->fresh(), EnJustificacion::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
        ]);
        Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
            'archivo_path' => 'papeletas/sustentos/certificado.pdf',
            'presentado_at' => now(),
        ]);

        \Livewire\Livewire::actingAs($jefe)
            ->test(\App\Livewire\Papeletas\JefeIndex::class)
            ->assertDontSee('Sustentos por revisar');
    }
}
