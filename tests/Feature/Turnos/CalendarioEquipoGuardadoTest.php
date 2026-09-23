<?php

namespace Tests\Feature\Turnos;

use App\Livewire\Turnos\CalendarioEquipoIndex;
use App\Models\Turno;
use App\Models\UnidadOrganica;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión: "Calendario del equipo" ya no es solo lectura para los
 * trabajadores 728 — el Jefe pinta M/T/N/D directo en la grilla y
 * guarda sin pasar por otra pantalla (ver CalendarioEquipoIndex::guardar,
 * que reutiliza ProgramacionTurnoService::guardarEquipo).
 */
class CalendarioEquipoGuardadoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    public function test_el_jefe_inmediato_guarda_turnos_728_de_su_trabajador_desde_el_calendario(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $jefe->update(['unidad_organica_id' => $unidad->id]);

        $trabajador = $this->usuarioDePrueba([
            'unidad_organica_id' => $unidad->id,
            'regimen' => '728',
            'activo' => true,
        ], ['trabajador']);

        Livewire::actingAs($jefe)
            ->test(CalendarioEquipoIndex::class)
            ->call('guardar', [
                (string) $trabajador->id => [
                    '2026-10-01' => 'MANANA',
                    '2026-10-02' => 'DESCANSO',
                ],
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turnos', [
            'user_id' => $trabajador->id,
            'fecha' => '2026-10-01',
            'turno' => 'MANANA',
            'es_descanso' => false,
        ]);

        $this->assertDatabaseHas('turnos', [
            'user_id' => $trabajador->id,
            'fecha' => '2026-10-02',
            'es_descanso' => true,
        ]);
    }

    public function test_no_deja_guardar_turnos_de_un_trabajador_fuera_del_equipo_del_jefe(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $jefe->update(['unidad_organica_id' => $unidad->id]);

        $ajeno = $this->usuarioDePrueba(['regimen' => '728', 'activo' => true], ['trabajador']);

        Livewire::actingAs($jefe)
            ->test(CalendarioEquipoIndex::class)
            ->call('guardar', [
                (string) $ajeno->id => ['2026-10-01' => 'MANANA'],
            ])
            ->assertHasErrors('dias');

        $this->assertDatabaseMissing('turnos', ['user_id' => $ajeno->id]);
    }
}
