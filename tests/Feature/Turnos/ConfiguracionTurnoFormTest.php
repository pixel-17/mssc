<?php

namespace Tests\Feature\Turnos;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Livewire\Turnos\ConfiguracionTurnoForm;
use App\Models\ConfiguracionTurno;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Aviso de "sin turno vigente hoy" en el formulario de configurar turno:
 * desde que ningún régimen (276 incluido) tiene horario global de
 * respaldo, un trabajador sin turno cargado para hoy no puede crear
 * NINGUNA papeleta (ver CrearPapeletaAction). Quien programa el turno
 * debe verlo de inmediato al entrar a esta pantalla.
 */
class ConfiguracionTurnoFormTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_avisa_cuando_el_trabajador_276_no_tiene_turno_vigente_hoy(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->assertSet('sinTurnoVigenteHoy', true)
            ->assertSee('no tiene un turno vigente cargado para hoy');
    }

    public function test_no_avisa_cuando_el_trabajador_ya_tiene_turno_vigente_hoy(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->assertSet('sinTurnoVigenteHoy', false)
            ->assertDontSee('no tiene un turno vigente cargado para hoy');
    }

    public function test_la_grilla_semanal_marca_dia_y_descanso_segun_el_ciclo_actual(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->set('diasSemanaDescanso', [6, 7]) // sábado y domingo
            ->call('aplicarDiasSemana')
            ->assertSee('Semana (lunes a domingo)')
            ->assertSeeInOrder(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']);
    }

    public function test_guardar_la_configuracion_quita_el_bloqueo_para_crear_papeletas(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            ->set('diasSemanaDescanso', [6, 7])
            ->call('aplicarDiasSemana')
            ->call('guardar')
            ->assertHasNoErrors();

        $papeleta = app(CrearPapeletaAction::class)
            ->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertNotNull($papeleta->id);
    }

    /**
     * Regresión: el Blade oculta el formulario manual para 276, pero
     * diasTrabajo/diasDescanso/fechaAncla NO son #[Locked] en el
     * componente. Antes de este fix, un payload de Livewire manipulado
     * (p. ej. desde las devtools) podía mandar un ciclo manual y
     * guardarConfiguracion() lo aceptaba tal cual para un 276 — algo
     * que la pantalla ni siquiera ofrece como opción. Ahora
     * cargarConfiguracion() descarta esos valores para 276 y siempre
     * impone el ciclo calculado desde HORARIO_ORDINARIO_DIAS_LABORABLES
     * ('1,2,3,4,5' por defecto -> 5 días de trabajo, 2 de descanso).
     */
    public function test_un_payload_manipulado_no_logra_un_ciclo_manual_para_un_276(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);

        Livewire::actingAs($jefe)
            ->test(ConfiguracionTurnoForm::class, ['trabajador' => $trabajador])
            // Simula el payload manipulado: un ciclo diario (1 día de
            // trabajo, 1 de descanso) con una fecha ancla arbitraria,
            // algo que el Blade de 276 nunca expone como input.
            ->set('diasTrabajo', 1)
            ->set('diasDescanso', 1)
            ->set('fechaAncla', '2026-01-01')
            ->call('guardar')
            ->assertHasNoErrors();

        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->first();

        $this->assertNotNull($config);
        $this->assertSame(5, $config->dias_trabajo);
        $this->assertSame(2, $config->dias_descanso);
        $this->assertNotSame('2026-01-01', $config->fecha_ancla->toDateString());
    }
}