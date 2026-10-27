<?php

namespace Tests\Feature\Turnos;

use App\Livewire\Turnos\ConfiguracionTurnoForm;
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

        $papeleta = app(\App\Actions\Papeleta\CrearPapeletaAction::class)
            ->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertNotNull($papeleta->id);
    }
}
