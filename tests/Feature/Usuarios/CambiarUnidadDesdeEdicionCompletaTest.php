<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Usuarios\UsuarioForm;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * «Edición completa» (UsuarioForm, usuarios.editar) escribe
 * unidad_organica_id directo, sin pasar por MoverTrabajadorAction. Si a
 * quien encabeza una unidad (jefe_id) se le cambiaba la unidad por aquí sin
 * reasignar antes esa jefatura, jefe_id de la unidad vieja no se tocaba:
 * la persona quedaba «duplicada» en el organigrama (jefe en una unidad,
 * miembro en otra). Mismo guard que ya protege el arrastre en
 * MoverTrabajadorAction (MoverTrabajadorTest).
 */
class CambiarUnidadDesdeEdicionCompletaTest extends TestCase
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

    public function test_no_se_puede_cambiar_de_unidad_a_quien_encabeza_otra_sin_reasignar_antes(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $unidadQueEncabeza = UnidadOrganica::create(['nombre' => 'Oficina A', 'jefe_id' => $jefe->id]);
        $otroJefe = $this->usuarioDePrueba(['regimen' => '276']);
        $otraUnidad = UnidadOrganica::create(['nombre' => 'Oficina B', 'jefe_id' => $otroJefe->id]);
        $jefe->update(['unidad_organica_id' => $unidadQueEncabeza->id]);

        Livewire::actingAs($this->admin)
            ->test(UsuarioForm::class, ['usuario' => $jefe->fresh()])
            ->set('unidadOrganicaId', $otraUnidad->id)
            ->call('guardar')
            ->assertHasErrors(['unidadOrganicaId']);

        $jefe = $jefe->fresh();
        $this->assertSame($unidadQueEncabeza->id, $jefe->unidad_organica_id, 'no se movió');
        $this->assertSame($jefe->id, $unidadQueEncabeza->fresh()->jefe_id, 'sigue siendo jefe de su unidad original');
        $this->assertFalse(
            User::where('unidad_organica_id', $otraUnidad->id)->where('id', $jefe->id)->exists(),
            'no quedó también como miembro de la unidad destino'
        );
    }

    public function test_si_no_encabeza_ninguna_unidad_el_cambio_de_unidad_se_guarda_normal(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefeDestino = $this->usuarioDePrueba(['regimen' => '276']);
        $destino = UnidadOrganica::create(['nombre' => 'Oficina C', 'jefe_id' => $jefeDestino->id]);

        Livewire::actingAs($this->admin)
            ->test(UsuarioForm::class, ['usuario' => $trabajador->fresh()])
            ->set('unidadOrganicaId', $destino->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $trabajador = $trabajador->fresh();
        $this->assertSame($destino->id, $trabajador->unidad_organica_id);
        $this->assertSame($jefeDestino->id, $trabajador->jefe_inmediato_id);
    }
}
