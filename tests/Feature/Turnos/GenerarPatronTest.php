<?php

namespace Tests\Feature\Turnos;

use App\Livewire\Turnos\ProgramacionMensual;
use App\Models\CargaTurnoMensual;
use App\Models\Turno;
use App\Services\ProgramacionTurnoService;
use App\Support\PatronTurnos;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Generar el calendario de un trabajador 728 repitiendo un patrón:
 * un mes en el navegador, varios meses desde el servidor.
 */
class GenerarPatronTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    private function servicio(): ProgramacionTurnoService
    {
        return app(ProgramacionTurnoService::class);
    }

    public function test_el_plan_repite_el_patron_en_varios_meses_y_conserva_lo_previo_del_mes(): void
    {
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        $plan = $this->servicio()->planPatron(
            $trabajador,
            PatronTurnos::parsear('M D'),
            '2026-10-10',
            2,
            false,
            ['2026-10-05' => 'NOCHE', '2026-10-20' => 'TARDE'],
        );

        $this->assertSame(['2026-10', '2026-11'], array_keys($plan));
        $this->assertSame('NOCHE', $plan['2026-10']['2026-10-05']); // anterior a "desde": se conserva
        $this->assertSame('MANANA', $plan['2026-10']['2026-10-10']);
        $this->assertSame('DESCANSO', $plan['2026-10']['2026-10-11']);
        $this->assertSame('MANANA', $plan['2026-10']['2026-10-20']); // el patrón pisa lo posterior a "desde"
        $this->assertCount(30, $plan['2026-11']);
    }

    public function test_el_plan_nunca_pasa_del_maximo_de_meses(): void
    {
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        $plan = $this->servicio()->planPatron($trabajador, PatronTurnos::parsear('M D'), '2026-10-01', 99, false);

        $this->assertCount(PatronTurnos::MAX_MESES, $plan);
    }

    public function test_continuar_sigue_la_rotacion_guardada_del_mes_anterior(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        // Octubre terminó en M M T T.
        $this->servicio()->guardarMes($trabajador, 2026, 10, [
            '2026-10-28' => 'MANANA',
            '2026-10-29' => 'MANANA',
            '2026-10-30' => 'TARDE',
            '2026-10-31' => 'TARDE',
        ], $admin);

        $plan = $this->servicio()->planPatron($trabajador, PatronTurnos::parsear('M M T T N D'), '2026-11-01', 1, true);

        $this->assertSame('NOCHE', $plan['2026-11']['2026-11-01']);
        $this->assertSame('DESCANSO', $plan['2026-11']['2026-11-02']);
        $this->assertSame('MANANA', $plan['2026-11']['2026-11-03']);
    }

    public function test_guardar_el_plan_escribe_todos_los_meses_y_los_marca_manuales(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        $plan = $this->servicio()->planPatron($trabajador, PatronTurnos::parsear('M M M M M M D'), '2026-10-01', 2, false);
        $this->servicio()->guardarPlan($trabajador, $plan, $admin);

        $this->assertSame(61, Turno::where('user_id', $trabajador->id)->count());
        $this->assertSame(2, CargaTurnoMensual::where('user_id', $trabajador->id)->where('origen', 'manual')->count());
    }

    public function test_un_plan_con_un_mes_invalido_no_guarda_nada(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        try {
            $this->servicio()->guardarPlan($trabajador, [
                '2026-10' => ['2026-10-01' => 'MANANA'],
                '2026-11' => ['2026-12-01' => 'MANANA'], // fecha fuera de su mes
            ], $admin);
            $this->fail('Debió lanzar ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(0, Turno::where('user_id', $trabajador->id)->count());
        }
    }

    public function test_el_admin_genera_varios_meses_desde_la_pantalla(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramacionMensual::class, ['trabajador' => $trabajador])
            ->set('anio', 2026)
            ->set('mes', 10)
            ->call('generarMeses', 'M M M M M M D', '2026-10-01', 2, false, [])
            ->assertHasNoErrors();

        $this->assertSame(61, Turno::where('user_id', $trabajador->id)->count());
    }

    public function test_con_advertencias_espera_confirmacion_antes_de_guardar(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        // Tarde → Mañana del día siguiente deja solo 8 h de descanso.
        $componente = Livewire::actingAs($admin)
            ->test(ProgramacionMensual::class, ['trabajador' => $trabajador])
            ->set('anio', 2026)
            ->set('mes', 10)
            ->call('generarMeses', 'T M', '2026-10-01', 2, false, []);

        $this->assertSame(0, Turno::where('user_id', $trabajador->id)->count());

        $componente->call('guardarPlanIgualmente');

        $this->assertSame(61, Turno::where('user_id', $trabajador->id)->count());
    }

    public function test_un_patron_invalido_muestra_error_y_no_guarda(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramacionMensual::class, ['trabajador' => $trabajador])
            ->set('anio', 2026)
            ->set('mes', 10)
            ->call('generarMeses', 'M X D', '2026-10-01', 2, false, [])
            ->assertHasErrors('dias');

        $this->assertSame(0, Turno::where('user_id', $trabajador->id)->count());
    }

    public function test_el_desde_debe_estar_en_el_mes_que_se_ve(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        Livewire::actingAs($admin)
            ->test(ProgramacionMensual::class, ['trabajador' => $trabajador])
            ->set('anio', 2026)
            ->set('mes', 10)
            ->call('generarMeses', 'M D', '2026-12-01', 1, false, [])
            ->assertHasErrors('dias');

        $this->assertSame(0, Turno::where('user_id', $trabajador->id)->count());
    }

    public function test_quien_no_puede_programar_al_trabajador_no_abre_la_pantalla(): void
    {
        $otro = $this->usuarioDePrueba([], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['activo' => true]);

        Livewire::actingAs($otro)
            ->test(ProgramacionMensual::class, ['trabajador' => $trabajador])
            ->assertForbidden();
    }
}
