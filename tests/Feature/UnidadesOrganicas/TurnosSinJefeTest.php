<?php

namespace Tests\Feature\UnidadesOrganicas;

use App\Livewire\UnidadesOrganicas\UnidadOrganicaIndex;
use App\Models\ConfiguracionTurno;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Avisos de turnos sin jefe inmediato: UnidadOrganica::turnosSinJefe() y
 * los tres sitios donde se muestran (índice de unidades del admin,
 * listado de usuarios del jefe de área y alta de un trabajador 728).
 *
 * El jefe inmediato ya no elige un turno fijo al asignarse a la unidad
 * (jefes_turno solo dice QUIÉN); el turno que cubre para efectos de este
 * aviso sale de su propia configuración de calendario
 * (configuraciones_turno.turno, ver UnidadOrganica::resolverJefeInmediato()).
 */
class TurnosSinJefeTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    /** Asigna a $jefe como jefe inmediato adicional de $unidad, cubriendo $turno según su propio calendario. */
    private function asignarJefeConTurno(UnidadOrganica $unidad, User $jefe, string $turno): void
    {
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefe->id]);

        ConfiguracionTurno::create([
            'user_id' => $jefe->id,
            'turno' => $turno,
            'fecha_ancla' => Carbon::parse('2026-09-01'),
            'dias_trabajo' => 6,
            'dias_descanso' => 1,
        ]);
    }

    public function test_una_unidad_728_lista_los_turnos_que_no_tienen_jefe(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $this->asignarJefeConTurno($unidad, $jefe, 'MANANA');

        $this->assertSame(['TARDE', 'NOCHE'], $unidad->fresh()->turnosSinJefe());
    }

    public function test_una_unidad_728_completa_no_tiene_turnos_sin_jefe(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);

        // Un jefe cubre un solo turno propio (su calendario es uno solo):
        // una unidad completa necesita un jefe distinto por cada turno.
        foreach (['MANANA', 'TARDE', 'NOCHE'] as $turno) {
            $this->asignarJefeConTurno($unidad, $this->usuarioDePrueba(['regimen' => '728']), $turno);
        }

        $this->assertSame([], $unidad->fresh()->turnosSinJefe());
    }

    public function test_una_unidad_276_o_sin_jefe_no_tiene_turnos_que_avisar(): void
    {
        $jefe276 = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad276 = UnidadOrganica::create(['nombre' => 'Oficina 276', 'jefe_id' => $jefe276->id]);
        $sinJefe = UnidadOrganica::create(['nombre' => 'Sin jefe']);

        $this->assertSame([], $unidad276->fresh()->turnosSinJefe());
        $this->assertSame([], $sinJefe->fresh()->turnosSinJefe());
    }

    public function test_un_jefe_de_turno_desactivado_cuenta_como_turno_sin_jefe(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $jefeTarde = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $this->asignarJefeConTurno($unidad, $jefe, 'MANANA');
        $this->asignarJefeConTurno($unidad, $jefeTarde, 'TARDE');

        $jefeTarde->update(['activo' => false]);

        $this->assertSame(['TARDE', 'NOCHE'], $unidad->fresh()->turnosSinJefe());
    }

    public function test_el_admin_ve_en_el_indice_que_turnos_le_faltan_a_cada_unidad(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $jefeManana = $this->usuarioDePrueba(['regimen' => '728']);
        $jefeTarde = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina Sin Cobertura', 'jefe_id' => $jefeManana->id]);
        $this->asignarJefeConTurno($unidad, $jefeManana, 'MANANA');
        $this->asignarJefeConTurno($unidad, $jefeTarde, 'TARDE');

        Livewire::actingAs($admin)
            ->test(UnidadOrganicaIndex::class)
            ->assertSee('Turnos sin jefe')
            ->assertSee('Noche');
    }

    public function test_el_jefe_de_area_ve_el_aviso_en_su_listado_de_usuarios(): void
    {
        $jefeDeArea = $this->usuarioDePrueba([], ['admin']);
        UnidadOrganica::create(['nombre' => 'Oficina Incompleta', 'jefe_id' => $jefeDeArea->id]);

        $this->actingAs($jefeDeArea)
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('Oficina Incompleta: falta jefe inmediato para Mañana, Tarde, Noche.');
    }

    public function test_crear_un_trabajador_728_en_un_turno_sin_jefe_avisa(): void
    {
        $jefeDeArea = $this->usuarioDePrueba([], ['admin']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeDeArea->id]);
        $this->asignarJefeConTurno($unidad, $jefeDeArea, 'MANANA');

        $this->actingAs($jefeDeArea)
            ->post(route('usuarios.store'), [
                'name' => 'Luis',
                'apellido' => 'Mamani',
                'dni' => '70998877',
                'email' => 'luis@example.com',
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,
                'tipo' => 'trabajador',
                'turno' => 'NOCHE',
                'fecha_ancla' => now()->toDateString(),
            ])
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn (string $mensaje) => str_contains($mensaje, 'turno Noche'));
    }

    public function test_crear_un_trabajador_728_en_un_turno_con_jefe_no_avisa(): void
    {
        $jefeDeArea = $this->usuarioDePrueba([], ['admin']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeDeArea->id]);
        $this->asignarJefeConTurno($unidad, $jefeDeArea, 'MANANA');

        $this->actingAs($jefeDeArea)
            ->post(route('usuarios.store'), [
                'name' => 'Luis',
                'apellido' => 'Mamani',
                'dni' => '70998877',
                'email' => 'luis@example.com',
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,
                'tipo' => 'trabajador',
                'turno' => 'MANANA',
                'fecha_ancla' => now()->toDateString(),
            ])
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('success')
            ->assertSessionMissing('warning');
    }
}
