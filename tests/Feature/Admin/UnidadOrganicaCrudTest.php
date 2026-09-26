<?php

namespace Tests\Feature\Admin;

use App\Livewire\UnidadesOrganicas\UnidadOrganicaForm;
use App\Livewire\UnidadesOrganicas\UnidadOrganicaIndex;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * CRUD de Unidades orgánicas: jerarquía padre/hijo sin ciclos y jefes por
 * turno (jefes_turno) que solo se asignan editando una unidad ya creada.
 * Los modelos van con ->fresh() al montar el formulario por los defaults de
 * BD (activo) que create() no trae.
 */
class UnidadOrganicaCrudTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
    }

    /** @param  array<string, mixed>  $atributos */
    private function unidad(string $nombre, array $atributos = []): UnidadOrganica
    {
        return UnidadOrganica::create(['nombre' => $nombre, ...$atributos])->fresh();
    }

    public function test_el_admin_crea_una_unidad_con_padre_y_jefe(): void
    {
        $padre = $this->unidad('Gerencia Municipal');
        $jefe = $this->usuarioDePrueba();

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class)
            ->set('nombre', 'Oficina de Personal')
            ->set('tipo', 'apoyo')
            ->set('parentId', $padre->id)
            ->set('jefeId', $jefe->id)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('unidades-organicas.index'));

        $unidad = UnidadOrganica::where('nombre', 'Oficina de Personal')->firstOrFail();

        $this->assertSame('apoyo', $unidad->tipo);
        $this->assertSame($padre->id, $unidad->parent_id);
        $this->assertSame($jefe->id, $unidad->jefe_id);
        $this->assertTrue($unidad->activo);
    }

    public function test_solo_el_nombre_es_obligatorio(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class)
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class)
            ->set('nombre', 'Solo nombre')
            ->call('guardar')
            ->assertHasNoErrors();

        $unidad = UnidadOrganica::where('nombre', 'Solo nombre')->firstOrFail();

        $this->assertNull($unidad->tipo);
        $this->assertNull($unidad->parent_id);
        $this->assertNull($unidad->jefe_id);
    }

    public function test_el_tipo_debe_ser_uno_de_los_conocidos(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class)
            ->set('nombre', 'Tipo raro')
            ->set('tipo', 'inventado')
            ->call('guardar')
            ->assertHasErrors(['tipo' => 'in']);

        foreach (array_keys(UnidadOrganicaForm::TIPOS) as $tipo) {
            Livewire::actingAs($this->admin)
                ->test(UnidadOrganicaForm::class)
                ->set('nombre', 'Unidad '.$tipo)
                ->set('tipo', $tipo)
                ->call('guardar')
                ->assertHasNoErrors();
        }
    }

    public function test_padre_y_jefe_deben_existir(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class)
            ->set('nombre', 'Huérfana')
            ->set('parentId', 999999)
            ->set('jefeId', 999999)
            ->call('guardar')
            ->assertHasErrors(['parentId' => 'exists', 'jefeId' => 'exists']);
    }

    public function test_al_crear_los_jefes_adicionales_se_ignoran(): void
    {
        $jefe = $this->usuarioDePrueba();

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class)
            ->set('nombre', 'Recién creada')
            ->set('jefesAdicionales.0', $jefe->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, JefeTurno::count(), 'los jefes adicionales solo se asignan editando una unidad ya creada');
    }

    public function test_editar_asigna_y_quita_jefes_adicionales(): void
    {
        $unidad = $this->unidad('Serenazgo');
        $jefeUno = $this->usuarioDePrueba();
        $jefeDos = $this->usuarioDePrueba();

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class, ['unidad' => $unidad])
            ->set('jefesAdicionales.0', $jefeUno->id)
            ->set('jefesAdicionales.1', $jefeDos->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, JefeTurno::where('unidad_organica_id', $unidad->id)->count());
        $this->assertTrue(JefeTurno::where('unidad_organica_id', $unidad->id)->where('jefe_id', $jefeUno->id)->exists());
        $this->assertTrue(JefeTurno::where('unidad_organica_id', $unidad->id)->where('jefe_id', $jefeDos->id)->exists());

        // Se reabre el formulario: precarga lo asignado y quitar uno lo elimina.
        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class, ['unidad' => $unidad->fresh()])
            ->assertSet('jefesAdicionales', [$jefeUno->id, $jefeDos->id])
            ->set('jefesAdicionales.0', null)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertFalse(JefeTurno::where('unidad_organica_id', $unidad->id)->where('jefe_id', $jefeUno->id)->exists());
        $this->assertTrue(JefeTurno::where('unidad_organica_id', $unidad->id)->where('jefe_id', $jefeDos->id)->exists());
    }

    public function test_reasignar_un_jefe_adicional_no_duplica_filas(): void
    {
        $unidad = $this->unidad('Limpieza pública');
        $primero = $this->usuarioDePrueba();
        $segundo = $this->usuarioDePrueba();

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $primero->id]);

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class, ['unidad' => $unidad->fresh()])
            ->set('jefesAdicionales.0', $segundo->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, JefeTurno::where('unidad_organica_id', $unidad->id)->count());
        $this->assertSame($segundo->id, JefeTurno::where('unidad_organica_id', $unidad->id)->value('jefe_id'));
    }

    public function test_una_unidad_no_puede_ser_su_propio_padre_ni_el_de_un_descendiente(): void
    {
        $raiz = $this->unidad('Gerencia');
        $hija = $this->unidad('Subgerencia', ['parent_id' => $raiz->id]);
        $nieta = $this->unidad('Oficina', ['parent_id' => $hija->id]);

        foreach ([$raiz->id, $hija->id, $nieta->id] as $padreProhibido) {
            Livewire::actingAs($this->admin)
                ->test(UnidadOrganicaForm::class, ['unidad' => $raiz->fresh()])
                ->set('parentId', $padreProhibido)
                ->call('guardar')
                ->assertHasErrors('parentId');
        }

        $this->assertNull($raiz->fresh()->parent_id, 'el árbol no debe volverse cíclico');
    }

    public function test_mover_una_unidad_a_un_padre_valido_funciona(): void
    {
        $raiz = $this->unidad('Gerencia');
        $hija = $this->unidad('Subgerencia', ['parent_id' => $raiz->id]);
        $otra = $this->unidad('Otra gerencia');

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class, ['unidad' => $hija->fresh()])
            ->set('parentId', $otra->id)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('unidades-organicas.index'));

        $this->assertSame($otra->id, $hija->fresh()->parent_id);
    }

    public function test_los_padres_disponibles_excluyen_a_la_unidad_y_a_sus_descendientes(): void
    {
        $raiz = $this->unidad('Gerencia');
        $hija = $this->unidad('Subgerencia', ['parent_id' => $raiz->id]);
        $ajena = $this->unidad('Ajena');

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaForm::class, ['unidad' => $raiz->fresh()])
            ->assertViewHas('padresDisponibles', fn ($padres) => $padres->keys()->all() === [$ajena->id]);
    }

    public function test_eliminar_una_unidad_conserva_a_sus_hijas_sin_padre(): void
    {
        $raiz = $this->unidad('Gerencia');
        $hija = $this->unidad('Subgerencia', ['parent_id' => $raiz->id]);

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaIndex::class)
            ->call('eliminar', $raiz->id);

        $this->assertModelMissing($raiz);
        $this->assertModelExists($hija);
        $this->assertNull($hija->fresh()->parent_id);
    }

    public function test_el_listado_muestra_las_unidades(): void
    {
        $this->unidad('Unidad visible en listado');

        Livewire::actingAs($this->admin)
            ->test(UnidadOrganicaIndex::class)
            ->assertSee('Unidad visible en listado');
    }

    public function test_quien_no_es_admin_no_puede_guardar_ni_eliminar(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $unidad = $this->unidad('Intocable');

        Livewire::actingAs($trabajador)
            ->test(UnidadOrganicaIndex::class)
            ->call('eliminar', $unidad->id)
            ->assertForbidden();

        Livewire::actingAs($trabajador)
            ->test(UnidadOrganicaForm::class)
            ->set('nombre', 'Colada')
            ->call('guardar')
            ->assertForbidden();

        $this->assertModelExists($unidad);
        $this->assertFalse(UnidadOrganica::where('nombre', 'Colada')->exists());
    }
}
