<?php

namespace Tests\Feature\Organigrama;

use App\Livewire\Organigrama\OrganigramaArbol;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Cerrada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Organigrama: filtro por sede, panel lateral (ficha) y modo edición.
 * Escenario: Gerencia (raíz, jefe Gina) → Oficina A (jefe Omar, con un
 * trabajador en la sede central y otro sin sede) y Oficina B (otra rama).
 */
class OrganigramaArbolTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private Sede $central;

    private Sede $norte;

    private UnidadOrganica $gerencia;

    private UnidadOrganica $oficinaA;

    private UnidadOrganica $oficinaB;

    private User $gina;

    private User $trabajadorCentral;

    private User $trabajadorSinSede;

    private User $trabajadorNorte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->central = $this->sedeDePrueba();
        $this->norte = Sede::create(['nombre' => 'Sede norte', 'latitud' => -13.4, 'longitud' => -71.9]);

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->gina = $this->usuarioDePrueba(['name' => 'Gina'], ['trabajador']);
        $omar = $this->usuarioDePrueba(['name' => 'Omar'], ['trabajador']);
        $beto = $this->usuarioDePrueba(['name' => 'Beto'], ['trabajador']);

        $this->gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->gina->id]);
        $this->oficinaA = UnidadOrganica::create(['nombre' => 'Oficina A', 'parent_id' => $this->gerencia->id, 'jefe_id' => $omar->id]);
        $this->oficinaB = UnidadOrganica::create(['nombre' => 'Oficina B', 'parent_id' => $this->gerencia->id, 'jefe_id' => $beto->id]);

        $this->trabajadorCentral = $this->usuarioDePrueba(['name' => 'Carla', 'unidad_organica_id' => $this->oficinaA->id]);
        $this->trabajadorSinSede = $this->usuarioDePrueba(['name' => 'Sergio', 'sede_id' => null, 'unidad_organica_id' => $this->oficinaA->id]);
        $this->trabajadorNorte = $this->usuarioDePrueba(['name' => 'Nora', 'sede_id' => $this->norte->id, 'unidad_organica_id' => $this->oficinaB->id]);
    }

    public function test_el_filtro_por_sede_deja_solo_a_la_gente_de_esa_sede(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->set('sede', (string) $this->norte->id)
            ->assertSee('Nora')
            ->assertDontSee('Carla')
            ->assertDontSee('Sergio');
    }

    public function test_el_filtro_sin_sede_muestra_a_quienes_no_tienen(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->set('sede', 'sin')
            ->assertSee('Sergio')
            ->assertDontSee('Carla')
            ->assertDontSee('Nora');
    }

    public function test_el_selector_muestra_cuantos_hay_por_sede_y_cuantos_sin_sede(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->assertSee('Sede norte (1)')
            ->assertSee('Sin sede (1)');
    }

    public function test_un_valor_de_sede_invalido_se_descarta(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->set('sede', 'x; drop')
            ->assertSet('sede', '');
    }

    public function test_la_ficha_muestra_jefe_sede_regimen_y_papeletas_pendientes(): void
    {
        $this->papeletaDePrueba($this->trabajadorCentral, PendienteJefe::class);
        $this->papeletaDePrueba($this->trabajadorCentral, Cerrada::class);

        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('verPersona', $this->trabajadorCentral->id)
            ->assertViewHas('ficha', fn (?array $ficha) => $ficha !== null
                && $ficha['persona']->is($this->trabajadorCentral)
                && $ficha['persona']->sede->nombre === 'Sede central'
                && $ficha['persona']->regimen === '728'
                && $ficha['pendientes_total'] === 1
                && $ficha['pendientes']->firstWhere('etiqueta', 'Pendiente del jefe')['total'] === 1)
            ->assertSee('Pendiente del jefe: 1')
            ->call('cerrarPersona')
            ->assertSet('personaId', null);
    }

    public function test_el_jefe_de_area_no_puede_abrir_la_ficha_de_alguien_fuera_de_su_rama(): void
    {
        $jefeAreaAjeno = $this->usuarioDePrueba(['name' => 'Ajena'], ['trabajador']);
        $otraRaiz = UnidadOrganica::create(['nombre' => 'Otra gerencia', 'jefe_id' => $jefeAreaAjeno->id]);
        UnidadOrganica::create(['nombre' => 'Sub otra', 'parent_id' => $otraRaiz->id, 'jefe_id' => $this->usuarioDePrueba()->id]);

        Livewire::actingAs($jefeAreaAjeno)
            ->test(OrganigramaArbol::class)
            ->call('verPersona', $this->trabajadorCentral->id)
            ->assertViewHas('ficha', null)
            ->assertDontSee('Carla');
    }

    public function test_el_modo_edicion_se_activa_y_desactiva_con_el_boton(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->assertSet('modoEdicion', false)
            ->call('alternarEdicion')
            ->assertSet('modoEdicion', true)
            ->assertSee('Salir del modo edición')
            ->call('alternarEdicion')
            ->assertSet('modoEdicion', false)
            ->assertDontSee('Salir del modo edición');
    }

    // ---- «Mover a otra unidad» desde la ficha (alternativa al arrastre) ----

    public function test_desde_la_ficha_se_propone_el_movimiento_sin_arrastrar(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('verPersona', $this->trabajadorCentral->id)
            ->set('destinoMover', $this->oficinaB->id)
            ->call('moverDesdeFicha')
            // La ficha se cierra para que la confirmación no quede apilada sobre ella.
            ->assertSet('personaId', null)
            ->assertSet('destinoMover', null)
            ->assertSet('propuesta.trabajador', $this->trabajadorCentral->id)
            ->assertSet('propuesta.origen', $this->oficinaA->id)
            ->assertSet('propuesta.destino', $this->oficinaB->id)
            ->assertSee('Confirmar movimiento');

        // Solo es una propuesta: nadie se mueve hasta confirmar.
        $this->assertSame($this->oficinaA->id, $this->trabajadorCentral->fresh()->unidad_organica_id);
    }

    public function test_desde_la_ficha_sin_elegir_destino_avisa_y_no_cierra_la_ficha(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('verPersona', $this->trabajadorCentral->id)
            ->call('moverDesdeFicha')
            ->assertHasErrors(['destinoMover'])
            ->assertSet('personaId', $this->trabajadorCentral->id)
            ->assertSet('propuesta', null);
    }

    public function test_desde_la_ficha_no_mueve_fuera_del_modo_edicion(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('verPersona', $this->trabajadorCentral->id)
            ->set('destinoMover', $this->oficinaB->id)
            ->call('moverDesdeFicha')
            ->assertSet('propuesta', null)
            ->assertSet('personaId', $this->trabajadorCentral->id);
    }

    public function test_los_destinos_de_la_ficha_solo_salen_en_modo_edicion_y_excluyen_la_unidad_actual(): void
    {
        $componente = Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('verPersona', $this->trabajadorCentral->id)
            ->assertViewHas('destinosMover', []);

        $componente
            ->call('alternarEdicion')
            ->assertViewHas('destinosMover', function (array $destinos) {
                $ids = array_column($destinos, 'id');

                return in_array($this->oficinaB->id, $ids, true)
                    && ! in_array($this->oficinaA->id, $ids, true);
            });
    }

    public function test_un_trabajador_comun_recibe_403(): void
    {
        Livewire::actingAs($this->trabajadorCentral)
            ->test(OrganigramaArbol::class)
            ->assertForbidden();
    }
}
