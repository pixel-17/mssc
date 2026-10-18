<?php

namespace Tests\Feature\Organigrama;

use App\Livewire\Organigrama\PersonaDrawer;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Drawer único de alta/edición de personas desde el organigrama (reemplaza al
 * Unifica alta y edición para admin y jefe de área en un solo flujo.
 * Escenario: Gerencia (raíz, jefe Gina) → Oficina A (jefe Omar, con Carla) y
 * Oficina B (otra rama, fuera del área de Gina).
 */
class PersonaDrawerTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private Sede $central;

    private UnidadOrganica $gerencia;

    private UnidadOrganica $oficinaA;

    private UnidadOrganica $oficinaB;

    private UnidadOrganica $oficinaC;

    private User $admin;

    private User $gina;

    private User $omar;

    private User $carla;

    private User $vero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->central = $this->sedeDePrueba();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->gina = $this->usuarioDePrueba(['name' => 'Gina'], ['trabajador']);
        $this->omar = $this->usuarioDePrueba(['name' => 'Omar'], ['trabajador']);
        $this->vero = $this->usuarioDePrueba(['name' => 'Vero'], ['trabajador']);

        $this->gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->gina->id]);
        $this->oficinaA = UnidadOrganica::create(['nombre' => 'Oficina A', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->omar->id]);
        // Un destino sin jefe inmediato activo no admite movimientos (MoverTrabajadorAction::exigirJefeInmediato).
        $this->oficinaB = UnidadOrganica::create(['nombre' => 'Oficina B', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->vero->id]);
        $this->oficinaC = UnidadOrganica::create(['nombre' => 'Oficina C', 'parent_id' => $this->gerencia->id]);

        $this->carla = $this->usuarioDePrueba(['name' => 'Carla', 'unidad_organica_id' => $this->oficinaA->id]);
    }

    // ---- Alta ----

    public function test_el_jefe_de_area_crea_un_trabajador_en_su_propia_area(): void
    {
        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', null, $this->oficinaA->id)
            ->set('name', 'Luz')
            ->set('apellido', 'Paredes')
            ->set('dni', '87654321')
            ->set('email', 'luz@example.com')
            ->set('regimen', $this->omar->regimen)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('org-actualizado');

        $nueva = User::where('dni', '87654321')->firstOrFail();
        $this->assertSame((int) $this->oficinaA->id, (int) $nueva->unidad_organica_id);
        $this->assertTrue($nueva->hasRole('trabajador'));
    }

    public function test_el_jefe_de_area_no_puede_crear_en_una_unidad_fuera_de_su_area(): void
    {
        $otraGerencia = UnidadOrganica::create(['nombre' => 'Otra gerencia']);

        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', null, $otraGerencia->id)
            ->set('name', 'Luz')
            ->set('apellido', 'Paredes')
            ->set('dni', '87654321')
            ->set('email', 'luz@example.com')
            ->set('regimen', '728')
            ->set('unidadId', $otraGerencia->id)
            ->call('guardar')
            ->assertHasErrors(['unidadId']);

        $this->assertDatabaseMissing('users', ['dni' => '87654321']);
    }

    public function test_el_regimen_del_trabajador_debe_coincidir_con_el_de_su_jefe_inmediato(): void
    {
        $this->omar->update(['regimen' => '276']);

        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', null, $this->oficinaA->id)
            ->set('name', 'Luz')
            ->set('apellido', 'Paredes')
            ->set('dni', '87654321')
            ->set('email', 'luz@example.com')
            ->set('regimen', '728')
            ->call('guardar')
            ->assertHasErrors(['regimen']);

        $this->assertDatabaseMissing('users', ['dni' => '87654321']);
    }

    public function test_el_admin_crea_un_jefe_inmediato_para_una_unidad_sin_jefe(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', null, $this->oficinaC->id, 'jefe_inmediato')
            ->set('name', 'Rita')
            ->set('apellido', 'Soto')
            ->set('dni', '11223344')
            ->set('email', 'rita@example.com')
            ->set('regimen', '276')
            ->set('sedeId', $this->central->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->oficinaC->refresh();
        $nueva = User::where('dni', '11223344')->firstOrFail();
        $this->assertSame((int) $nueva->id, (int) $this->oficinaC->jefe_id);
    }

    public function test_no_se_puede_crear_un_segundo_jefe_para_una_unidad_que_ya_tiene(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', null, $this->oficinaA->id, 'jefe_inmediato')
            ->set('name', 'Rita')
            ->set('apellido', 'Soto')
            ->set('dni', '11223344')
            ->set('email', 'rita@example.com')
            ->set('regimen', '276')
            ->set('sedeId', $this->central->id)
            ->call('guardar');

        $this->assertDatabaseMissing('users', ['dni' => '11223344']);
    }

    // ---- Edición ----

    public function test_el_jefe_de_area_edita_a_un_trabajador_de_su_area(): void
    {
        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $this->carla->id)
            ->set('name', 'Carla Actualizada')
            ->set('sedeId', $this->central->id)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('org-actualizado');

        $this->assertSame('Carla Actualizada', $this->carla->fresh()->name);
    }

    public function test_el_jefe_de_area_no_puede_editar_a_alguien_fuera_de_su_area(): void
    {
        $fuera = $this->usuarioDePrueba(['name' => 'Fuera']);

        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $fuera->id)
            ->assertForbidden();
    }

    public function test_el_jefe_de_area_no_puede_quedarse_sin_elegir_sede_al_editar(): void
    {
        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $this->carla->id)
            ->set('sedeId', null)
            ->call('guardar')
            ->assertHasErrors(['sedeId']);
    }

    public function test_el_admin_puede_dejar_a_alguien_sin_sede_al_editar(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $this->carla->id)
            ->set('sedeId', null)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull($this->carla->fresh()->sede_id);
    }

    public function test_el_admin_reubica_a_un_trabajador_en_otra_unidad_y_se_recalcula_su_jefe(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $this->carla->id)
            ->set('unidadId', $this->oficinaB->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->carla->refresh();
        $this->assertSame((int) $this->oficinaB->id, (int) $this->carla->unidad_organica_id);
    }

    public function test_solo_el_admin_ve_el_interruptor_de_activo(): void
    {
        $this->actingAs($this->gina);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $this->carla->id)
            ->assertDontSee('Persona activa');

        $this->actingAs($this->admin);

        Livewire::test(PersonaDrawer::class)
            ->call('abrir', $this->carla->id)
            ->assertSee('Persona activa');
    }
}
