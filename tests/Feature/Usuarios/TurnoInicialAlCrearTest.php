<?php

namespace Tests\Feature\Usuarios;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Models\ConfiguracionTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un 728 SIEMPRE necesita turno vigente para crear una papeleta (ver
 * CrearPapeletaAction, ya no hay MODO_ESTRICTO_728). El generador
 * automático mensual solo continúa una ConfiguracionTurno que ya
 * existe, nunca crea la primera: por eso ambos flujos de alta
 * (Admin y Jefe) exigen el turno inicial en el mismo paso, para que
 * ningún 728 quede sin horario desde el día uno.
 */
class TurnoInicialAlCrearTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        $this->admin = $this->usuarioDePrueba([], ['admin']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_admin_no_puede_crear_un_728_sin_turno(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Rosa')
            ->set('apellido', 'Quispe')
            ->set('dni', '70112233')
            ->set('email', 'rosa@example.com')
            ->set('regimen', '728')
            ->set('sedeId', $this->sedeDePrueba()->id)
            ->call('guardar')
            ->assertHasErrors(['turno', 'fechaAncla']);

        $this->assertNull(User::where('dni', '70112233')->first(), 'no debe crear al usuario si falta el turno');
    }

    public function test_admin_crea_un_728_con_turno_y_puede_crear_papeleta_el_mismo_dia(): void
    {
        // Congelado dentro del horario de MANANA (06:00-14:00) para que el
        // turno recién creado esté vigente al llamar a CrearPapeletaAction.
        $this->travelTo(Carbon::parse(now()->toDateString().' 10:00:00'));

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Rosa')
            ->set('apellido', 'Quispe')
            ->set('dni', '70112233')
            ->set('email', 'rosa@example.com')
            ->set('regimen', '728')
            ->set('sedeId', $this->sedeDePrueba()->id)
            ->set('turno', 'MANANA')
            ->set('fechaAncla', now()->toDateString())
            ->call('guardar')
            ->assertHasNoErrors();

        $nuevo = User::where('dni', '70112233')->firstOrFail();

        $this->assertTrue(ConfiguracionTurno::where('user_id', $nuevo->id)->exists());

        // Cierra el círculo: el mismo trabajador recién creado ya puede
        // crear una papeleta hoy, sin que nadie tenga que cargarle nada más.
        $papeleta = app(CrearPapeletaAction::class)->ejecutar($nuevo, $this->motivoDe('PARTICULAR'), []);
        $this->assertNotNull($papeleta->id);
    }

    public function test_admin_puede_crear_un_276_sin_turno(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Rosa')
            ->set('apellido', 'Quispe')
            ->set('dni', '70112233')
            ->set('email', 'rosa@example.com')
            ->set('regimen', '276')
            ->set('sedeId', $this->sedeDePrueba()->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $nuevo = User::where('dni', '70112233')->firstOrFail();
        $this->assertFalse(ConfiguracionTurno::where('user_id', $nuevo->id)->exists());
    }

    public function test_admin_editando_un_728_que_ya_tiene_turno_no_lo_vuelve_a_exigir(): void
    {
        $trabajador = $this->usuarioDePrueba(['dni' => '70112233']);

        ConfiguracionTurno::create([
            'user_id' => $trabajador->id,
            'turno' => 'TARDE',
            'fecha_ancla' => Carbon::parse('2026-09-01'),
            'dias_trabajo' => 6,
            'dias_descanso' => 1,
        ]);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $trabajador])
            ->set('name', 'Rosa Editada')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Rosa Editada', $trabajador->fresh()->name);
    }

    public function test_jefe_de_area_no_puede_crear_un_728_sin_turno_por_http(): void
    {
        $jefeDeArea = $this->usuarioDePrueba([], ['admin']); // dueño de la unidad para simplificar autorización
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeDeArea->id]);

        $this->actingAs($jefeDeArea)
            ->post(route('usuarios.store'), [
                'name' => 'Luis',
                'apellido' => 'Mamani',
                'dni' => '70998877',
                'email' => 'luis@example.com',
                'regimen' => '728',
                'unidad_organica_id' => $unidad->id,
                'tipo' => 'trabajador',
            ])
            ->assertSessionHasErrors(['turno', 'fecha_ancla']);

        $this->assertNull(User::where('dni', '70998877')->first());
    }
}
