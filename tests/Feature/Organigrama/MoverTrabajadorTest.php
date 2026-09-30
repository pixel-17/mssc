<?php

namespace Tests\Feature\Organigrama;

use App\Actions\Organigrama\MoverTrabajadorAction;
use App\Exceptions\UsuarioException;
use App\Livewire\Organigrama\OrganigramaArbol;
use App\Models\MovimientoOrganigrama;
use App\Models\Sede;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Organigrama: mover trabajadores (confirmación, reglas en el servidor)
 * e historial con deshacer.
 *
 * Escenario: Gerencia (jefa de área Gina) → Oficina A (Omar), Oficina B
 * (Beto) y Oficina 276 (jefe 276). Carla y Sergio están en A. Aparte, otra
 * área: Otra gerencia (Ajena) → Sub otra.
 */
class MoverTrabajadorTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $gina;

    private User $omar;

    private User $beto;

    private User $ajena;

    private User $carla;

    private User $sergio;

    private UnidadOrganica $gerencia;

    private UnidadOrganica $oficinaA;

    private UnidadOrganica $oficinaB;

    private UnidadOrganica $oficina276;

    private UnidadOrganica $subOtra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->gina = $this->usuarioDePrueba(['name' => 'Gina']);
        $this->omar = $this->usuarioDePrueba(['name' => 'Omar']);
        $this->beto = $this->usuarioDePrueba(['name' => 'Beto']);
        $this->ajena = $this->usuarioDePrueba(['name' => 'Ajena']);
        $jefe276 = $this->usuarioDePrueba(['name' => 'Jefe276', 'regimen' => '276']);
        $jefeSubOtra = $this->usuarioDePrueba(['name' => 'JefeSubOtra']);

        $this->gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->gina->id]);
        $this->oficinaA = UnidadOrganica::create(['nombre' => 'Oficina A', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->omar->id]);
        $this->oficinaB = UnidadOrganica::create(['nombre' => 'Oficina B', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->beto->id]);
        $this->oficina276 = UnidadOrganica::create(['nombre' => 'Oficina 276', 'parent_id' => $this->gerencia->id, 'jefe_id' => $jefe276->id]);

        $otraGerencia = UnidadOrganica::create(['nombre' => 'Otra gerencia', 'jefe_id' => $this->ajena->id]);
        $this->subOtra = UnidadOrganica::create(['nombre' => 'Sub otra', 'parent_id' => $otraGerencia->id, 'jefe_id' => $jefeSubOtra->id]);

        $this->carla = $this->usuarioDePrueba(['name' => 'Carla', 'unidad_organica_id' => $this->oficinaA->id]);
        $this->sergio = $this->usuarioDePrueba(['name' => 'Sergio', 'unidad_organica_id' => $this->oficinaA->id]);
    }

    private function mover(): MoverTrabajadorAction
    {
        return app(MoverTrabajadorAction::class);
    }

    private function moverYa(User $actor, User $trabajador, UnidadOrganica $destino): MovimientoOrganigrama
    {
        return $this->mover()->ejecutar($actor, $trabajador->id, $destino->id, (int) $trabajador->fresh()->unidad_organica_id, true);
    }

    // ---- Action: movimiento y vista previa -------------------------------

    public function test_el_admin_mueve_y_los_jefes_se_recalculan_y_queda_historial(): void
    {
        $movimiento = $this->moverYa($this->admin, $this->carla, $this->oficinaB);

        $carla = $this->carla->fresh();
        $this->assertSame($this->oficinaB->id, $carla->unidad_organica_id);
        $this->assertSame($this->beto->id, $carla->jefe_inmediato_id);
        $this->assertSame($this->gina->id, $carla->jefe_area_id);

        $this->assertSame($this->carla->id, $movimiento->trabajador_id);
        $this->assertSame($this->admin->id, $movimiento->actor_id);
        $this->assertSame($this->oficinaA->id, $movimiento->unidad_anterior_id);
        $this->assertSame($this->oficinaB->id, $movimiento->unidad_nueva_id);
        $this->assertSame($this->omar->id, $movimiento->jefe_inmediato_anterior_id);
        $this->assertSame($this->beto->id, $movimiento->jefe_inmediato_nuevo_id);
        $this->assertSame($this->gina->id, $movimiento->jefe_area_anterior_id);
        $this->assertSame($this->gina->id, $movimiento->jefe_area_nuevo_id);
        $this->assertSame($carla->sede_id, $movimiento->sede_anterior_id);
        $this->assertSame($carla->sede_id, $movimiento->sede_nueva_id);
        $this->assertNotNull($movimiento->created_at);
        $this->assertNull($movimiento->deshecho_at);
    }

    public function test_la_vista_previa_calcula_que_cambia_sin_guardar_nada(): void
    {
        $vista = $this->mover()->previsualizar($this->admin, $this->carla, $this->oficinaB);

        $this->assertTrue($vista['jefe_inmediato']['cambia']);
        $this->assertSame($this->omar->id, $vista['jefe_inmediato']['antes']->id);
        $this->assertSame($this->beto->id, $vista['jefe_inmediato']['despues']->id);
        $this->assertFalse($vista['jefe_area']['cambia']);

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
        $this->assertSame(0, MovimientoOrganigrama::count());
    }

    public function test_no_se_mueve_sin_confirmacion_explicita(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('confirmar');

        $this->mover()->ejecutar($this->admin, $this->carla->id, $this->oficinaB->id, $this->oficinaA->id, false);
    }

    public function test_si_la_persona_ya_no_esta_donde_la_pantalla_la_vio_se_aborta(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('ya no está en la unidad');

        // La pantalla la vio en la Oficina B, pero está en la A.
        $this->mover()->ejecutar($this->admin, $this->carla->id, $this->subOtra->id, $this->oficinaB->id, true);
    }

    // ---- Action: permisos --------------------------------------------------

    public function test_el_jefe_de_area_mueve_dentro_de_su_area(): void
    {
        $this->moverYa($this->gina, $this->carla, $this->oficinaB);

        $this->assertSame($this->oficinaB->id, $this->carla->fresh()->unidad_organica_id);
    }

    public function test_el_jefe_de_area_no_mueve_entre_areas_pero_el_admin_si(): void
    {
        try {
            $this->moverYa($this->gina, $this->carla, $this->subOtra);
            $this->fail('Un jefe de área no debe poder mover entre áreas.');
        } catch (UsuarioException $e) {
            $this->assertStringContainsString('entre áreas', $e->getMessage());
        }

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);

        $this->moverYa($this->admin, $this->carla, $this->subOtra);
        $this->assertSame($this->subOtra->id, $this->carla->fresh()->unidad_organica_id);
    }

    public function test_un_jefe_de_area_no_mueve_a_gente_fuera_de_su_area(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('fuera de tu área');

        $this->moverYa($this->ajena, $this->carla, $this->subOtra);
    }

    public function test_quien_no_es_admin_ni_jefe_de_area_no_mueve_a_nadie(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('No tienes permiso');

        $this->moverYa($this->omar, $this->carla, $this->oficinaB);
    }

    // ---- Action: reglas de negocio ----------------------------------------

    public function test_no_se_mueve_a_una_unidad_de_otro_regimen(): void
    {
        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('régimen');

        $this->moverYa($this->admin, $this->carla, $this->oficina276);
    }

    public function test_quien_encabeza_una_unidad_no_se_mueve_desde_el_organigrama(): void
    {
        $this->omar->update(['unidad_organica_id' => $this->oficinaA->id]);

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('encabeza una unidad');

        $this->moverYa($this->admin, $this->omar, $this->oficinaB);
    }

    public function test_no_se_mueve_a_la_misma_unidad_ni_a_una_desactivada(): void
    {
        try {
            $this->moverYa($this->admin, $this->carla, $this->oficinaA);
            $this->fail('Debía rechazar la misma unidad.');
        } catch (UsuarioException $e) {
            $this->assertStringContainsString('ya está en esa unidad', $e->getMessage());
        }

        $this->oficinaB->update(['activo' => false]);

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('desactivada');

        $this->moverYa($this->admin, $this->carla, $this->oficinaB);
    }

    // ---- Deshacer ----------------------------------------------------------

    public function test_deshacer_devuelve_a_la_persona_y_deja_registro_sin_borrar_el_original(): void
    {
        $original = $this->moverYa($this->admin, $this->carla, $this->oficinaB);

        $reversion = $this->mover()->deshacer($this->admin, $original);

        $carla = $this->carla->fresh();
        $this->assertSame($this->oficinaA->id, $carla->unidad_organica_id);
        $this->assertSame($this->omar->id, $carla->jefe_inmediato_id);

        $this->assertSame($original->id, $reversion->revierte_id);
        $this->assertSame($this->oficinaB->id, $reversion->unidad_anterior_id);
        $this->assertSame($this->oficinaA->id, $reversion->unidad_nueva_id);

        $original->refresh();
        $this->assertNotNull($original->deshecho_at);
        $this->assertSame($this->admin->id, $original->deshecho_por_id);
        $this->assertSame(2, MovimientoOrganigrama::count());
    }

    public function test_un_movimiento_no_se_deshace_dos_veces(): void
    {
        $original = $this->moverYa($this->admin, $this->carla, $this->oficinaB);
        $this->mover()->deshacer($this->admin, $original);

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('ya se deshizo');

        $this->mover()->deshacer($this->admin, $original);
    }

    public function test_no_se_deshace_si_hay_movimientos_mas_recientes_de_esa_persona(): void
    {
        $primero = $this->moverYa($this->admin, $this->carla, $this->oficinaB);
        $this->moverYa($this->admin, $this->carla, $this->gerencia);

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('más recientes');

        $this->mover()->deshacer($this->admin, $primero);
    }

    public function test_deshacer_respeta_los_permisos_de_area(): void
    {
        $original = $this->moverYa($this->admin, $this->carla, $this->subOtra);

        // Gina ve el movimiento terminar fuera de su área: no puede revertirlo.
        $this->assertFalse($this->mover()->puedeDeshacer($this->gina, $original));
        $this->assertTrue($this->mover()->puedeDeshacer($this->admin, $original));

        $this->expectException(UsuarioException::class);

        $this->mover()->deshacer($this->gina, $original);
    }

    // ---- Livewire ----------------------------------------------------------

    public function test_soltar_abre_la_confirmacion_y_solo_al_confirmar_se_mueve(): void
    {
        $componente = Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->carla->id, $this->oficinaB->id)
            ->assertSet('propuesta.trabajador', $this->carla->id)
            ->assertSet('propuesta.origen', $this->oficinaA->id)
            ->assertSet('propuesta.destino', $this->oficinaB->id)
            ->assertSet('propuesta.cambiar_sede', false)
            ->assertSet('propuesta.quitar_adicionales', false)
            ->assertSee('Confirmar movimiento');

        // Solo con la propuesta abierta, todavía no se movió nadie.
        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
        $this->assertSame(0, MovimientoOrganigrama::count());

        $componente
            ->call('confirmarMovimiento')
            ->assertSet('propuesta', null)
            ->assertSee('pasó de Oficina A a Oficina B')
            ->assertSee('Movimientos recientes');

        $this->assertSame($this->oficinaB->id, $this->carla->fresh()->unidad_organica_id);
        $this->assertSame(1, MovimientoOrganigrama::count());
    }

    public function test_cancelar_no_mueve_a_nadie(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->carla->id, $this->oficinaB->id)
            ->call('cancelarMovimiento')
            ->assertSet('propuesta', null);

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
        $this->assertSame(0, MovimientoOrganigrama::count());
    }

    public function test_fuera_del_modo_edicion_soltar_no_hace_nada(): void
    {
        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('proponerMovimiento', $this->carla->id, $this->oficinaB->id)
            ->assertSet('propuesta', null);

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
    }

    public function test_el_jefe_de_area_que_intenta_mover_entre_areas_ve_el_error_y_no_se_mueve_nada(): void
    {
        Livewire::actingAs($this->gina)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->carla->id, $this->subOtra->id)
            ->assertSet('propuesta', null)
            ->assertSee('Solo un administrador puede mover personas entre áreas');

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
    }

    public function test_una_propuesta_manipulada_desde_el_navegador_se_rechaza_al_confirmar(): void
    {
        // Ajena solo manda en su área; manipula la propuesta para mover a Carla.
        Livewire::actingAs($this->ajena)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->set('propuesta', [
                'trabajador' => $this->carla->id,
                'origen' => $this->oficinaA->id,
                'destino' => $this->subOtra->id,
                'cambiar_sede' => false,
                'quitar_adicionales' => false,
            ])
            ->call('confirmarMovimiento')
            ->assertSee('fuera de tu área');

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
        $this->assertSame(0, MovimientoOrganigrama::count());
    }

    public function test_se_puede_deshacer_desde_el_historial(): void
    {
        $original = $this->moverYa($this->admin, $this->carla, $this->oficinaB);

        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('deshacerMovimiento', $original->id)
            ->assertSee('Se deshizo el movimiento');

        $this->assertSame($this->oficinaA->id, $this->carla->fresh()->unidad_organica_id);
        $this->assertNotNull($original->fresh()->deshecho_at);
    }

    public function test_el_historial_del_jefe_de_area_solo_muestra_movimientos_de_su_alcance(): void
    {
        $this->moverYa($this->admin, $this->carla, $this->oficinaB);
        $this->moverYa($this->admin, $this->sergio, $this->subOtra);

        Livewire::actingAs($this->ajena)
            ->test(OrganigramaArbol::class)
            ->assertViewHas('historial', fn ($historial) => $historial->count() === 1
                && $historial->first()['movimiento']->trabajador_id === $this->sergio->id);
    }

    // ---- Sede propuesta y jefes adicionales -------------------------------

    private function ponerBetoEnOtraSede(): Sede
    {
        $norte = Sede::create(['nombre' => 'Sede norte', 'latitud' => -13.4, 'longitud' => -71.9]);
        $this->beto->update(['sede_id' => $norte->id]);

        return $norte;
    }

    public function test_se_propone_la_sede_del_jefe_del_destino_solo_si_es_distinta(): void
    {
        $vista = $this->mover()->previsualizar($this->admin, $this->carla, $this->oficinaB);
        $this->assertFalse($vista['sede']['propone_cambio']);

        $norte = $this->ponerBetoEnOtraSede();

        $vista = $this->mover()->previsualizar($this->admin, $this->carla, $this->oficinaB);
        $this->assertTrue($vista['sede']['propone_cambio']);
        $this->assertSame($norte->id, $vista['sede']['propuesta']->id);
    }

    public function test_aceptar_la_sede_propuesta_la_cambia_y_deshacer_la_devuelve(): void
    {
        $norte = $this->ponerBetoEnOtraSede();
        $sedeOriginal = $this->carla->fresh()->sede_id;

        $movimiento = $this->mover()->ejecutar($this->admin, $this->carla->id, $this->oficinaB->id, $this->oficinaA->id, true, cambiarSede: true);

        $this->assertSame($norte->id, $this->carla->fresh()->sede_id);
        $this->assertSame($sedeOriginal, $movimiento->sede_anterior_id);
        $this->assertSame($norte->id, $movimiento->sede_nueva_id);

        $this->mover()->deshacer($this->admin, $movimiento);

        $this->assertSame($sedeOriginal, $this->carla->fresh()->sede_id);
    }

    public function test_rechazar_la_sede_propuesta_conserva_la_actual(): void
    {
        $this->ponerBetoEnOtraSede();
        $sedeOriginal = $this->carla->fresh()->sede_id;

        $movimiento = $this->mover()->ejecutar($this->admin, $this->carla->id, $this->oficinaB->id, $this->oficinaA->id, true, cambiarSede: false);

        $this->assertSame($sedeOriginal, $this->carla->fresh()->sede_id);
        $this->assertFalse($movimiento->cambioSede());
    }

    public function test_cambiar_sede_se_ignora_si_no_hay_propuesta(): void
    {
        $sedeOriginal = $this->carla->fresh()->sede_id;

        // Mismo sede entre los jefes: no hay nada que proponer, aunque llegue true.
        $this->mover()->ejecutar($this->admin, $this->carla->id, $this->oficinaB->id, $this->oficinaA->id, true, cambiarSede: true);

        $this->assertSame($sedeOriginal, $this->carla->fresh()->sede_id);
    }

    public function test_por_defecto_se_conservan_los_jefes_adicionales(): void
    {
        $this->carla->jefesInmediatosAdicionales()->attach($this->sergio->id, ['asignado_por_id' => $this->admin->id]);

        $this->moverYa($this->admin, $this->carla, $this->oficinaB);

        $this->assertSame([$this->sergio->id], $this->carla->jefesInmediatosAdicionales()->pluck('users.id')->all());
    }

    public function test_quitar_los_jefes_adicionales_los_anota_y_deshacer_los_devuelve(): void
    {
        $this->carla->jefesInmediatosAdicionales()->attach($this->sergio->id, ['asignado_por_id' => $this->admin->id]);

        $movimiento = $this->mover()->ejecutar($this->admin, $this->carla->id, $this->oficinaB->id, $this->oficinaA->id, true, quitarAdicionales: true);

        $this->assertSame(0, $this->carla->jefesInmediatosAdicionales()->count());
        $this->assertSame(
            [['jefe_id' => $this->sergio->id, 'asignado_por_id' => $this->admin->id]],
            $movimiento->jefes_adicionales_quitados,
        );

        $this->mover()->deshacer($this->admin, $movimiento);

        $this->assertSame([$this->sergio->id], $this->carla->jefesInmediatosAdicionales()->pluck('users.id')->all());
    }

    public function test_la_ventana_de_confirmacion_ofrece_sede_y_jefes_adicionales_y_los_aplica(): void
    {
        $norte = $this->ponerBetoEnOtraSede();
        $this->carla->jefesInmediatosAdicionales()->attach($this->sergio->id, ['asignado_por_id' => $this->admin->id]);

        Livewire::actingAs($this->admin)
            ->test(OrganigramaArbol::class)
            ->call('alternarEdicion')
            ->call('proponerMovimiento', $this->carla->id, $this->oficinaB->id)
            ->assertSet('propuesta.cambiar_sede', true)
            ->assertSet('propuesta.quitar_adicionales', false)
            ->assertSee('Cambiar la sede de')
            ->assertSee('Jefes inmediatos adicionales')
            ->set('propuesta.quitar_adicionales', true)
            ->call('confirmarMovimiento');

        $carla = $this->carla->fresh();
        $this->assertSame($norte->id, $carla->sede_id);
        $this->assertSame(0, $carla->jefesInmediatosAdicionales()->count());
    }
}
