<?php

namespace Tests\Feature\Usuarios;

use App\Models\ConfiguracionTurno;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\EquipoDelJefeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un jefe de turno que no es `jefe_id` de la unidad (users.jefe_inmediato_id
 * apunta al titular) ve igual a los trabajadores de su turno.
 */
class EquipoPorTurnoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefeDeArea;

    private User $titular;

    private User $jefeTarde;

    private User $trabajadorTarde;

    private User $trabajadorManana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefeDeArea = $this->usuarioDePrueba();
        $this->titular = $this->usuarioDePrueba();
        $this->jefeTarde = $this->usuarioDePrueba();

        $area = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->jefeDeArea->id]);
        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $area->id, 'jefe_id' => $this->titular->id]);

        JefeTurno::create(['unidad_organica_id' => $oficina->id, 'jefe_id' => $this->titular->id]);
        JefeTurno::create(['unidad_organica_id' => $oficina->id, 'jefe_id' => $this->jefeTarde->id]);

        $this->trabajadorTarde = $this->usuarioDePrueba();
        $this->trabajadorManana = $this->usuarioDePrueba();

        // El jefe de turno cubre a los trabajadores cuyo turno CONFIGURADO
        // coincide con el suyo propio (ver User::scopeDeLosTurnosQueCubre):
        // por eso jefeTarde también necesita su propia configuración TARDE.
        foreach ([[$this->jefeTarde, 'TARDE'], [$this->trabajadorTarde, 'TARDE'], [$this->trabajadorManana, 'MANANA']] as [$usuario, $turno]) {
            ConfiguracionTurno::create([
                'user_id' => $usuario->id,
                'turno' => $turno,
                'fecha_ancla' => Carbon::parse('2026-09-01'),
                'dias_trabajo' => 6,
                'dias_descanso' => 1,
            ]);
        }

        $this->trabajadorTarde->update(['unidad_organica_id' => $oficina->id]);
        $this->trabajadorManana->update(['unidad_organica_id' => $oficina->id]);
    }

    public function test_el_equipo_de_un_jefe_de_turno_son_los_trabajadores_de_su_turno(): void
    {
        $ids = User::equipoDe($this->jefeTarde)->pluck('id')->all();

        $this->assertContains($this->trabajadorTarde->id, $ids);
        $this->assertNotContains($this->trabajadorManana->id, $ids);
    }

    public function test_la_programacion_de_equipo_de_un_jefe_de_turno_incluye_solo_su_turno_y_a_el_mismo(): void
    {
        [$trabajadores] = app(EquipoDelJefeService::class)->para($this->jefeTarde);

        $ids = $trabajadores->pluck('id')->all();

        $this->assertContains($this->trabajadorTarde->id, $ids);
        $this->assertContains($this->jefeTarde->id, $ids);
        $this->assertNotContains($this->trabajadorManana->id, $ids);
    }

    public function test_el_jefe_de_area_ve_a_los_jefes_de_turno_de_sus_subunidades(): void
    {
        [$trabajadores, $esJefeDeArea] = app(EquipoDelJefeService::class)->para($this->jefeDeArea);

        $ids = $trabajadores->pluck('id')->all();

        $this->assertTrue($esJefeDeArea);
        $this->assertContains($this->titular->id, $ids);
        $this->assertContains($this->jefeTarde->id, $ids);
    }

    public function test_quien_no_es_jefe_de_ningun_turno_no_gana_equipo_por_este_camino(): void
    {
        $otro = $this->usuarioDePrueba();

        $this->assertSame([], User::equipoDe($otro)->pluck('id')->all());
    }
}
