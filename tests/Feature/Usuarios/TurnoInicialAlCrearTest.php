<?php

namespace Tests\Feature\Usuarios;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Livewire\Usuarios\UsuarioForm;
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
 * existe, nunca crea la primera: por eso el alta exige el turno
 * inicial en el mismo paso, para que ningún 728 quede sin horario
 * desde el día uno.
 *
 * El admin NO da de alta trabajadores (UserPolicy::puedeCrearAlgo() lo
 * excluye a propósito: sus altas van por Cuentas de usuario —solo admin
 * o RR. HH. puro, sin régimen/sede/turno— o por el organigrama). Así
 * que el único flujo de ALTA que de verdad ejercita el turno inicial es
 * el de Jefe de Área / Jefe Inmediato (UsuarioForm, vía organigrama);
 * el admin solo puede cargar o cambiar el turno al EDITAR a alguien que
 * ya existe (UsuarioAdminForm), cubierto en UsuarioAdminCrudTest.
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

    // "Admin puede crear un 728 sin turno y se le carga después" queda
    // cubierto por test_jefe_de_area_puede_crear_un_728_sin_turno_por_http()
    // más abajo: el admin no da de alta trabajadores (ver nota de clase),
    // así que ya no hay una variante propia de admin que probar aquí.

    public function test_jefe_de_area_crea_un_728_con_turno_y_el_trabajador_puede_crear_papeleta_el_mismo_dia(): void
    {
        // Congelado dentro del horario de MANANA (06:00-14:00) para que el
        // turno recién creado esté vigente al llamar a CrearPapeletaAction.
        $this->travelTo(Carbon::parse(now()->toDateString().' 10:00:00'));

        // El jefe de área también encabeza la unidad donde cae el nuevo
        // trabajador: así dobla como su jefe inmediato para la papeleta,
        // igual que el resto de este archivo (p. ej. el test de abajo).
        // Necesita su propio turno vigente para poder aprobarla.
        $jefeDeArea = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeDeArea->id]);
        $this->conUnidadHija($unidad);
        $jefeDeArea->update(['unidad_organica_id' => $unidad->id]);

        ConfiguracionTurno::create([
            'user_id' => $jefeDeArea->id,
            'turno' => 'MANANA',
            'fecha_ancla' => now()->toDateString(),
            'dias_trabajo' => 6,
            'dias_descanso' => 1,
        ]);
        $this->turnoDePrueba($jefeDeArea, ['turno' => 'MANANA']);

        Livewire::actingAs($jefeDeArea)
            ->test(UsuarioForm::class)
            ->set('name', 'Rosa')
            ->set('apellido', 'Quispe')
            ->set('dni', '70112233')
            ->set('email', 'rosa@example.com')
            ->set('regimen', '728')
            ->set('unidadOrganicaId', $unidad->id)
            ->set('tipo', 'trabajador')
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

    public function test_jefe_de_area_puede_crear_un_276_sin_turno(): void
    {
        $jefeDeArea = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeDeArea->id]);
        $this->conUnidadHija($unidad);

        Livewire::actingAs($jefeDeArea)
            ->test(UsuarioForm::class)
            ->set('name', 'Rosa')
            ->set('apellido', 'Quispe')
            ->set('dni', '70112233')
            ->set('email', 'rosa@example.com')
            ->set('regimen', '276')
            ->set('unidadOrganicaId', $unidad->id)
            ->set('tipo', 'trabajador')
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

    public function test_jefe_de_area_puede_crear_un_728_sin_turno_por_http(): void
    {
        // Jefe de área = trabajador que encabeza la unidad (un admin no puede dar altas por esta vía).
        $jefeDeArea = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeDeArea->id]);
        $this->conUnidadHija($unidad);

        Livewire::actingAs($jefeDeArea)
            ->test(UsuarioForm::class)
            ->set('name', 'Luis')
            ->set('apellido', 'Mamani')
            ->set('dni', '70998877')
            ->set('email', 'luis@example.com')
            ->set('regimen', '728')
            ->set('unidadOrganicaId', $unidad->id)
            ->set('tipo', 'trabajador')
            ->call('guardar')
            ->assertHasNoErrors();

        $nuevo = User::where('dni', '70998877')->firstOrFail();

        $this->assertFalse(ConfiguracionTurno::where('user_id', $nuevo->id)->exists(), 'el turno no se pide al crear');
    }
}