<?php

namespace Tests\Feature\Turnos;

use App\Models\UnidadOrganica;
use App\Services\EquipoDelJefeService;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión: el alcance de "mi equipo" en las pantallas de turnos
 * (calendario/programación) debe ser más angosto que el de
 * papeletas/reportes (User::equipoDe):
 *
 * - El Jefe Inmediato ve a sus trabajadores directos y a sí mismo.
 * - El Jefe de Área ve a sus Jefes Inmediatos (los jefes de las
 *   sub-unidades de su área, en cualquier nivel) y a sí mismo, pero
 *   NO a los trabajadores de esas sub-unidades — salvo los que tenga
 *   asignados a él de forma directa.
 */
class EquipoDelJefeServiceTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    public function test_jefe_inmediato_ve_solo_a_sus_trabajadores_directos_y_a_si_mismo(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $jefe->update(['unidad_organica_id' => $unidad->id]);

        $trabajador1 = $this->usuarioDePrueba(['unidad_organica_id' => $unidad->id], ['trabajador']);
        $trabajador2 = $this->usuarioDePrueba(['unidad_organica_id' => $unidad->id], ['trabajador']);
        $ajeno = $this->usuarioDePrueba([], ['trabajador']);

        [$trabajadores, $esJefeDeArea] = app(EquipoDelJefeService::class)->para($jefe->fresh());

        $ids = $trabajadores->pluck('id')->all();

        $this->assertFalse($esJefeDeArea);
        $this->assertContains($jefe->id, $ids);
        $this->assertContains($trabajador1->id, $ids);
        $this->assertContains($trabajador2->id, $ids);
        $this->assertNotContains($ajeno->id, $ids);
    }

    public function test_jefe_de_area_ve_a_los_jefes_de_sus_subunidades_pero_no_a_los_trabajadores_de_estas(): void
    {
        $jefeDeArea = $this->usuarioDePrueba([], ['trabajador']);
        $unidadArea = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefeDeArea->id]);
        $jefeDeArea->update(['unidad_organica_id' => $unidadArea->id]);

        $jefeSub = $this->usuarioDePrueba([], ['trabajador']);
        $unidadSub = UnidadOrganica::create([
            'nombre' => 'Oficina',
            'jefe_id' => $jefeSub->id,
            'parent_id' => $unidadArea->id,
        ]);
        $jefeSub->update(['unidad_organica_id' => $unidadSub->id]);

        $trabajadorDeLaSub = $this->usuarioDePrueba(['unidad_organica_id' => $unidadSub->id], ['trabajador']);

        [$trabajadores, $esJefeDeArea] = app(EquipoDelJefeService::class)->para($jefeDeArea->fresh());

        $ids = $trabajadores->pluck('id')->all();

        $this->assertTrue($esJefeDeArea);
        $this->assertContains($jefeDeArea->id, $ids);
        $this->assertContains($jefeSub->id, $ids, 'debe ver al jefe de la sub-unidad');
        $this->assertNotContains($trabajadorDeLaSub->id, $ids, 'NO debe ver a los trabajadores de esa sub-unidad');
    }

    public function test_jefe_de_area_si_ve_a_los_trabajadores_que_tiene_asignados_directamente_como_jefe_inmediato(): void
    {
        $jefeDeArea = $this->usuarioDePrueba([], ['trabajador']);
        $unidadArea = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefeDeArea->id]);
        $jefeDeArea->update(['unidad_organica_id' => $unidadArea->id]);

        // Trabajador de su propia oficina: jefe_inmediato_id lo apunta a
        // él directamente (miembro de la misma unidad que encabeza).
        $trabajadorDirecto = $this->usuarioDePrueba(['unidad_organica_id' => $unidadArea->id], ['trabajador']);

        [$trabajadores] = app(EquipoDelJefeService::class)->para($jefeDeArea->fresh());

        $this->assertContains($trabajadorDirecto->id, $trabajadores->pluck('id')->all());
    }
}
