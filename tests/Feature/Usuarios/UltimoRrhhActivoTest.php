<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Livewire\Usuarios\UsuarioAdminIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Sin ningún RRHH activo, RrhhHorarioService da a RRHH por "fuera de
 * horario" y toda papeleta aprobada por el jefe se autoriza sola: no se
 * permite desactivar ni quitar el rol al último RRHH activo.
 */
class UltimoRrhhActivoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->rrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
    }

    public function test_no_se_puede_desactivar_al_unico_rrhh_activo(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->call('desactivar', $this->rrhh->id);

        $this->assertTrue($this->rrhh->fresh()->activo);
        $this->assertTrue($this->rrhh->fresh()->esUnicoRrhhActivo());
    }

    public function test_si_hay_otro_rrhh_activo_si_se_puede_desactivar(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->call('desactivar', $this->rrhh->id);

        $this->assertFalse($this->rrhh->fresh()->activo);
    }

    public function test_un_rrhh_inactivo_no_cuenta_como_reemplazo(): void
    {
        $this->usuarioDePrueba(['regimen' => '276', 'activo' => false], ['rrhh']);

        $this->assertTrue($this->rrhh->esUnicoRrhhActivo());

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->call('desactivar', $this->rrhh->id);

        $this->assertTrue($this->rrhh->fresh()->activo);
    }

    public function test_el_formulario_no_deja_desactivar_al_unico_rrhh_activo(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $this->rrhh])
            ->set('activo', false)
            ->call('guardar')
            ->assertHasErrors('activo');

        $this->assertTrue($this->rrhh->fresh()->activo);
    }

    public function test_el_formulario_no_deja_quitar_el_rol_al_unico_rrhh_activo(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $this->rrhh])
            ->set('rolesSeleccionados', [Role::where('name', 'trabajador')->value('id')])
            ->call('guardar')
            ->assertHasErrors('rolesSeleccionados');

        $this->assertTrue($this->rrhh->fresh()->hasRole('rrhh'));
    }

    public function test_el_formulario_permite_editar_otros_datos_del_unico_rrhh(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $this->rrhh])
            ->set('name', 'Nombre corregido')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Nombre corregido', $this->rrhh->fresh()->name);
        $this->assertTrue($this->rrhh->fresh()->hasRole('rrhh'));
    }
}
