<?php

namespace Tests\Feature\Usuarios;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Con un solo jefe por unidad, el equipo de un jefe inmediato es su gente
 * directa (users.jefe_inmediato_id). El jefe de área, además, ve a los
 * jefes inmediatos de sus sub-unidades, pero no a su personal.
 */
class EquipoDeJefeUnicoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefeDeArea;

    private User $jefeInmediato;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefeDeArea = $this->usuarioDePrueba();
        $this->jefeInmediato = $this->usuarioDePrueba();

        $area = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->jefeDeArea->id]);
        $oficina = UnidadOrganica::create([
            'nombre' => 'Oficina',
            'parent_id' => $area->id,
            'jefe_id' => $this->jefeInmediato->id,
        ]);

        $this->trabajador = $this->usuarioDePrueba();
        $this->trabajador->update(['unidad_organica_id' => $oficina->id]);
        $this->trabajador = $this->trabajador->fresh();
    }

    public function test_el_equipo_del_jefe_inmediato_es_su_gente_directa(): void
    {
        $ids = User::equipoDe($this->jefeInmediato)->pluck('id')->all();

        $this->assertContains($this->trabajador->id, $ids);
        $this->assertNotContains($this->jefeDeArea->id, $ids);
    }

    public function test_la_programacion_de_equipo_del_jefe_inmediato_incluye_su_gente_y_a_el_mismo(): void
    {
        [$personas, $esJefeDeArea] = app(EquipoDelJefeService::class)->para($this->jefeInmediato);

        $ids = $personas->pluck('id')->all();

        $this->assertFalse($esJefeDeArea);
        $this->assertContains($this->trabajador->id, $ids);
        $this->assertContains($this->jefeInmediato->id, $ids);
        $this->assertNotContains($this->jefeDeArea->id, $ids);
    }

    public function test_el_jefe_de_area_ve_a_los_jefes_inmediatos_de_sus_subunidades_y_no_a_su_personal(): void
    {
        [$personas, $esJefeDeArea] = app(EquipoDelJefeService::class)->para($this->jefeDeArea);

        $ids = $personas->pluck('id')->all();

        $this->assertTrue($esJefeDeArea);
        $this->assertContains($this->jefeInmediato->id, $ids);
        $this->assertNotContains($this->trabajador->id, $ids);
    }

    public function test_quien_no_es_jefe_de_nadie_no_tiene_equipo(): void
    {
        $otro = $this->usuarioDePrueba();

        $this->assertSame([], User::equipoDe($otro)->pluck('id')->all());
    }
}
