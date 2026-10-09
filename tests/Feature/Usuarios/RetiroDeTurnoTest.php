<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Models\CargaTurnoMensual;
use App\Models\ConfiguracionTurno;
use App\Models\Turno;
use App\Models\User;
use App\Services\GeneradorTurnoMensualService;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un turno solo vale mientras el trabajador esté activo y en el régimen
 * para el que se programó: al desactivarlo o cambiarlo de régimen
 * (276 <-> 728) su turno anterior se retira. El historial pasado queda.
 */
class RetiroDeTurnoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        Carbon::setTestNow('2026-10-15 09:00:00');

        $this->admin = $this->usuarioDePrueba([], ['admin']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function trabajadorConTurno(string $regimen, string $turno): User
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => $regimen]);

        app(GeneradorTurnoMensualService::class)->cargarConfiguracion(
            trabajador: $trabajador,
            turno: $turno,
            fechaAncla: Carbon::parse('2026-10-01'),
            actor: $this->admin,
        );

        return $trabajador->fresh();
    }

    public function test_desactivar_desde_el_formulario_retira_el_turno_y_conserva_el_historial(): void
    {
        $trabajador = $this->trabajadorConTurno('728', 'TARDE');

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $trabajador])
            ->set('activo', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse($trabajador->fresh()->activo);
        $this->assertFalse(ConfiguracionTurno::where('user_id', $trabajador->id)->exists());
        $this->assertFalse(Turno::where('user_id', $trabajador->id)->where('fecha', '>=', '2026-10-15')->exists());
        $this->assertTrue(Turno::where('user_id', $trabajador->id)->where('fecha', '<', '2026-10-15')->exists());
        $this->assertFalse(CargaTurnoMensual::where('user_id', $trabajador->id)->exists());
    }

    public function test_cambiar_de_728_a_276_retira_el_turno_rotativo(): void
    {
        $trabajador = $this->trabajadorConTurno('728', 'NOCHE');

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $trabajador])
            ->set('regimen', '276')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('276', $trabajador->fresh()->regimen);
        $this->assertFalse(ConfiguracionTurno::where('user_id', $trabajador->id)->exists());
        $this->assertFalse(Turno::where('user_id', $trabajador->id)->where('fecha', '>=', '2026-10-15')->exists());
    }

    public function test_cambiar_de_276_a_728_deja_programar_el_turno_nuevo(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        // Turno heredado del régimen anterior.
        ConfiguracionTurno::create([
            'user_id' => $trabajador->id,
            'turno' => ConfiguracionTurno::TURNO_276,
            'fecha_ancla' => '2026-10-01',
            'dias_trabajo' => 5,
            'dias_descanso' => 2,
        ]);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $trabajador->fresh()])
            ->set('regimen', '728')
            ->assertSet('turno', '')
            ->set('turno', 'MANANA')
            ->set('fechaAncla', '2026-10-15')
            ->call('guardar')
            ->assertHasNoErrors();

        $config = ConfiguracionTurno::where('user_id', $trabajador->id)->firstOrFail();

        $this->assertSame('MANANA', $config->turno);
        $this->assertSame('728', $trabajador->fresh()->regimen);
    }

    public function test_cambiar_de_728_a_276_sin_programar_turno_nuevo_no_deja_el_antiguo(): void
    {
        $trabajador = $this->trabajadorConTurno('728', 'MANANA');

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $trabajador])
            ->set('regimen', '276')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, ConfiguracionTurno::where('user_id', $trabajador->id)->count());
    }
}
