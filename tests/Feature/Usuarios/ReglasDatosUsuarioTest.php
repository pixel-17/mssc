<?php

namespace Tests\Feature\Usuarios;

use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Support\ReglasDatosUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Una sola regla de DNI/correo para el alta de jefes (CrearUsuarioRequest)
 * y para admin (UsuarioAdminForm), ver App\Support\ReglasDatosUsuario.
 */
class ReglasDatosUsuarioTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_admin_no_crea_con_el_dni_de_una_cuenta_desactivada_y_se_le_indica_reactivarla(): void
    {
        $this->usuarioDePrueba(['dni' => '40111222', 'activo' => false]);

        Livewire::actingAs($this->usuarioDePrueba([], ['admin']))
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Ana')
            ->set('apellido', 'Nueva')
            ->set('dni', '40111222')
            ->set('email', 'ana.nueva@example.com')
            ->set('regimen', '276')
            ->set('rolesSeleccionados', [(int) Role::where('name', 'trabajador')->value('id')])
            ->call('guardar')
            ->assertHasErrors(['dni' => 'unique']);

        $this->assertSame(1, User::where('dni', '40111222')->count());
    }

    public function test_el_jefe_sigue_pudiendo_reingresar_una_cuenta_desactivada_de_trabajador(): void
    {
        $desactivado = $this->usuarioDePrueba(['dni' => '40333444', 'activo' => false]);

        $this->assertFalse(
            validator(
                ['dni' => '40333444'],
                ['dni' => ReglasDatosUsuario::dni(soloActivos: true)],
            )->fails(),
            'un DNI de cuenta desactivada no debe bloquear el reingreso del jefe',
        );

        $this->assertFalse($desactivado->fresh()->activo);
    }
}
