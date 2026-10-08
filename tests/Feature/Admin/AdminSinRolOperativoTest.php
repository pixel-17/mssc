<?php

namespace Tests\Feature\Admin;

use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Models\Papeleta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El admin administra cuentas. No tiene trabajadores, jefes inmediatos,
 * bandeja de jefe ni papeletas propias.
 */
class AdminSinRolOperativoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_el_admin_no_es_jefe_de_equipo(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->assertFalse($admin->esJefeDeEquipo());
        $this->assertFalse($admin->can('crearTrabajadorPropio', \App\Models\User::class));
    }

    public function test_el_admin_no_puede_crear_papeletas_ni_con_el_rol_trabajador(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin', 'trabajador']);

        $this->assertFalse($admin->can('crear', Papeleta::class));
    }

    public function test_el_admin_no_ve_la_bandeja_de_jefe_ni_por_url(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->actingAs($admin)->get(route('jefe.papeletas.index'))->assertForbidden();
    }

    public function test_el_formulario_de_admin_rechaza_admin_mas_trabajador(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        Livewire::actingAs($admin)
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Mixto')
            ->set('apellido', 'Rol')
            ->set('dni', '40555666')
            ->set('email', 'mixto.rol@example.com')
            ->set('regimen', '276')
            ->set('rolesSeleccionados', [
                (int) Role::where('name', 'admin')->value('id'),
                (int) Role::where('name', 'trabajador')->value('id'),
            ])
            ->call('guardar')
            ->assertHasErrors(['rolesSeleccionados']);

        $this->assertDatabaseMissing('users', ['dni' => '40555666']);
    }

    public function test_nuevo_usuario_no_ofrece_ni_acepta_el_rol_admin(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $adminId = (int) Role::where('name', 'admin')->value('id');

        $componente = Livewire::actingAs($admin)->test(UsuarioAdminForm::class);

        $this->assertFalse($componente->viewData('roles')->contains('name', 'admin'));

        $componente
            ->set('name', 'Colado')
            ->set('apellido', 'Admin')
            ->set('dni', '40777888')
            ->set('email', 'colado.admin@example.com')
            ->set('regimen', '276')
            ->set('rolesSeleccionados', [$adminId])
            ->call('guardar')
            ->assertHasErrors(['rolesSeleccionados']);

        $this->assertDatabaseMissing('users', ['dni' => '40777888']);
    }

    public function test_el_admin_no_tiene_la_via_de_alta_de_jefes_pero_si_la_de_cuentas(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->assertFalse($admin->can('puedeCrearAlgo', \App\Models\User::class));

        $this->actingAs($admin)->get(route('usuarios.create'))->assertForbidden();
        $this->actingAs($admin)->get(route('usuarios.index'))->assertRedirect(route('usuarios-admin.index'));
        $this->actingAs($admin)->get(route('usuarios-admin.crear'))->assertOk();
    }
}
