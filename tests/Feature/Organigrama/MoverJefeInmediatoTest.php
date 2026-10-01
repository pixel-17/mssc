<?php

namespace Tests\Feature\Organigrama;

use App\Actions\Organigrama\MoverJefeInmediatoAction;
use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Exceptions\UsuarioException;
use App\Livewire\Organigrama\OrganigramaArbol;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Mover un jefe inmediato a otra área (se mueve su unidad con su gente) y
 * la regla de que no se mueve a nadie a una unidad sin jefe inmediato.
 *
 * Escenario: Gerencia (Gina) → Oficina A (Omar, con Carla) y Oficina B
 * (Beto). Aparte: Otra gerencia (Ajena) con Sub otra (JefeSub), y
 * Gerencia vacía, un área SIN jefe.
 */
class MoverJefeInmediatoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $gina;

    private User $omar;

    private User $beto;

    private User $ajena;

    private User $carla;

    private UnidadOrganica $gerencia;

    private UnidadOrganica $oficinaA;

    private UnidadOrganica $oficinaB;

    private UnidadOrganica $otraGerencia;

    private UnidadOrganica $areaSinJefe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->gina = $this->usuarioDePrueba(['name' => 'Gina']);
        $this->omar = $this->usuarioDePrueba(['name' => 'Omar']);
        $this->beto = $this->usuarioDePrueba(['name' => 'Beto']);
        $this->ajena = $this->usuarioDePrueba(['name' => 'Ajena']);
        $jefeSub = $this->usuarioDePrueba(['name' => 'JefeSub']);

        $this->gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->gina->id]);
        $this->oficinaA = UnidadOrganica::create(['nombre' => 'Oficina A', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->omar->id]);
        $this->oficinaB = UnidadOrganica::create(['nombre' => 'Oficina B', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->beto->id]);

        $this->otraGerencia = UnidadOrganica::create(['nombre' => 'Otra gerencia', 'jefe_id' => $this->ajena->id]);
        UnidadOrganica::create(['nombre' => 'Sub otra', 'parent_id' => $this->otraGerencia->id, 'jefe_id' => $jefeSub->id]);

        $this->areaSinJefe = UnidadOrganica::create(['nombre' => 'Área sin jefe']);

        $this->omar->update(['unidad_organica_id' => $this->oficinaA->id]);
        $this->carla = $this->usuarioDePrueba(['name' => 'Carla', 'unidad_organica_id' => $this->oficinaA->id]);
    }

    private function mover(): MoverJefeInmediatoAction
    {
        return app(MoverJefeInmediatoAction::class);
    }

    // ---- Action ------------------------------------------------------------

    public function test_el_admin_mueve_al_jefe_y_su_gente_cambia_de_jefe_de_area(): void
    {
        $unidad = $this->mover()->ejecutar($this->admin, $this->omar->id, $this->otraGerencia->id, $this->gerencia->id, true);

        $this->assertSame($this->otraGerencia->id, $unidad->parent_id);
        $this->assertSame($this->ajena->id, $this->carla->fresh()->jefe_area_id);
        $this->assertSame($this->omar->id, $this->carla->fresh()->jefe_inmediato_id);
        $this->assertSame($this->ajena->id, $this->omar->fresh()->jefe_inmediato_id);
    }

    public function test_el_jefe_que_figuraba_en_otra_unidad_pasa_a_la_suya(): void
    {
        $this->omar->update(['unidad_organica_id' => $this->oficinaB->id]);

        $this->mover()->ejecutar($this->admin, $this->omar->id, $this->otraGerencia->id, $this->gerencia->id, true);

        $this->assertSame($this->oficinaA->id, $this->omar->fresh()->unidad_organica_id);
        $this->assertSame($this->ajena->id, $this->omar->fresh()->jefe_inmediato_id);
    }

    public function test_el_area_destino_debe_tener_antes_un_jefe_de_area(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('jefe de área');

        $this->mover()->ejecutar($this->admin, $this->omar->id, $this->areaSinJefe->id, $this->gerencia->id, true);
    }

    public function test_el_jefe_de_area_del_destino_debe_estar_activo(): void
    {
        $this->ajena->forceFill(['activo' => false])->save();

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('jefe de área');

        $this->mover()->ejecutar($this->admin, $this->omar->id, $this->otraGerencia->id, $this->gerencia->id, true);
    }

    public function test_solo_el_admin_mueve_jefes_inmediatos(): void
    {
        $this->expectException(UsuarioException::class);

        $this->mover()->ejecutar($this->gina, $this->omar->id, $this->otraGerencia->id, $this->gerencia->id, true);
    }

    public function test_un_jefe_de_area_no_se_mueve_por_aqui(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('jefe de área');

        $this->mover()->ejecutar($this->admin, $this->gina->id, $this->otraGerencia->id, 0, true);
    }

    public function test_un_trabajador_que_no_encabeza_nada_no_se_mueve_como_jefe(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('no encabeza ninguna unidad');

        $this->mover()->ejecutar($this->admin, $this->carla->id, $this->otraGerencia->id, $this->oficinaA->id, true);
    }

    public function test_no_se_mueve_al_area_de_la_que_ya_depende(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('ya depende');

        $this->mover()->ejecutar($this->admin, $this->omar->id, $this->gerencia->id, $this->gerencia->id, true);
    }

    public function test_sin_confirmacion_explicita_no_se_mueve(): void
    {
        $this->expectException(UsuarioException::class);

        $this->mover()->ejecutar($this->admin, $this->omar->id, $this->otraGerencia->id, $this->gerencia->id, false);
    }

    public function test_si_la_unidad_ya_no_esta_donde_la_pantalla_la_vio_se_aborta(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('ya no está donde');

        $this->mover()->ejecutar($this->admin, $this->omar->id, $this->otraGerencia->id, $this->otraGerencia->id, true);
    }

    // ---- Trabajadores: el destino debe tener jefe inmediato -----------------

    public function test_no_se_mueve_a_un_trabajador_a_una_unidad_sin_jefe_inmediato(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('jefe inmediato');

        app(MoverTrabajadorAction::class)->ejecutar($this->admin, $this->carla->id, $this->areaSinJefe->id, $this->oficinaA->id, true);
    }

    public function test_no_se_mueve_a_un_trabajador_a_una_unidad_cuyo_jefe_esta_desactivado(): void
    {
        $this->beto->forceFill(['activo' => false])->save();

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('jefe inmediato');

        app(MoverTrabajadorAction::class)->ejecutar($this->admin, $this->carla->id, $this->oficinaB->id, $this->oficinaA->id, true);
    }

    // ---- Pantalla del organigrama ------------------------------------------

    public function test_arrastrar_un_jefe_abre_la_confirmacion_y_solo_al_confirmar_se_mueve(): void
    {
        $componente = Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->omar->id, $this->otraGerencia->id)
            ->assertSet('propuestaJefe.jefe', $this->omar->id)
            ->assertSet('propuestaJefe.origen', $this->gerencia->id)
            ->assertSet('propuestaJefe.destino', $this->otraGerencia->id)
            ->assertSee('Mover jefe inmediato');

        $this->assertSame($this->gerencia->id, $this->oficinaA->fresh()->parent_id);

        $componente
            ->call('confirmarMovimientoJefe')
            ->assertSet('propuestaJefe', null)
            ->assertSee('ahora depende de Otra gerencia');

        $this->assertSame($this->otraGerencia->id, $this->oficinaA->fresh()->parent_id);
    }

    public function test_arrastrar_un_jefe_a_un_area_sin_jefe_muestra_el_error_y_no_mueve_nada(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->omar->id, $this->areaSinJefe->id)
            ->assertSet('propuestaJefe', null)
            ->assertSee('no tiene jefe de área activo');

        $this->assertSame($this->gerencia->id, $this->oficinaA->fresh()->parent_id);
    }

    public function test_cancelar_no_mueve_al_jefe(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->omar->id, $this->otraGerencia->id)
            ->call('cancelarMovimiento')
            ->assertSet('propuestaJefe', null);

        $this->assertSame($this->gerencia->id, $this->oficinaA->fresh()->parent_id);
    }
}
