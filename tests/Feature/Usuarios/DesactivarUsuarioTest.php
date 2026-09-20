<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Usuarios\UsuarioAdminIndex;
use App\Models\Papeleta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * "Desactivar" nunca borra: papeletas.trabajador_id tiene cascadeOnDelete
 * y borrar al usuario se llevaría su historial.
 */
class DesactivarUsuarioTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->trabajador = $this->usuarioDePrueba();
    }

    public function test_desactivar_conserva_al_usuario_y_su_historial(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->call('desactivar', $this->trabajador->id);

        $this->assertFalse($this->trabajador->fresh()->activo);
        $this->assertNotNull(Papeleta::find($papeleta->id), 'la papeleta no debe desaparecer');
    }

    public function test_reactivar_devuelve_el_acceso(): void
    {
        $this->trabajador->forceFill(['activo' => false])->save();

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->call('reactivar', $this->trabajador->id);

        $this->assertTrue($this->trabajador->fresh()->activo);
    }

    public function test_un_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->call('desactivar', $this->admin->id);

        $this->assertTrue($this->admin->fresh()->activo);
    }

    public function test_quien_no_es_admin_no_puede_desactivar_ni_reactivar(): void
    {
        $otro = $this->usuarioDePrueba();

        Livewire::actingAs($otro)
            ->test(UsuarioAdminIndex::class)
            ->call('desactivar', $this->trabajador->id)
            ->assertForbidden();

        $this->assertTrue($this->trabajador->fresh()->activo);

        $this->trabajador->forceFill(['activo' => false])->save();

        Livewire::actingAs($otro)
            ->test(UsuarioAdminIndex::class)
            ->call('reactivar', $this->trabajador->id)
            ->assertForbidden();

        $this->assertFalse($this->trabajador->fresh()->activo);
    }
}
