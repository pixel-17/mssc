<?php

namespace Tests\Feature\Admin;

use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Actions\Usuario\AsignarJefeAdicionalAction;
use App\Exceptions\PapeletaException;
use App\Exceptions\UsuarioException;
use App\Livewire\Organigrama\UnidadModal;
use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El admin administra cuentas: no pertenece al organigrama, no encabeza
 * unidades, no es jefe de turno ni jefe inmediato adicional. Y puede crear
 * a otro admin desde «Nuevo administrador».
 */
class AdminFueraDelOrganigramaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $otroAdmin;

    private UnidadOrganica $gerencia;

    private UnidadOrganica $oficina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->otroAdmin = $this->usuarioDePrueba(['name' => 'Otro Admin'], ['admin']);

        $jefe = $this->usuarioDePrueba(['name' => 'Gina']);
        $this->gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefe->id]);
        $this->oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $this->gerencia->id]);
    }

    private function rolId(string $nombre): int
    {
        return (int) Role::where('name', $nombre)->value('id');
    }

    /** @param array<string, mixed> $cambios */
    private function guardarUnidad(UnidadOrganica $unidad, array $cambios, ?array $jefesAdicionales = null): UnidadOrganica
    {
        return app(GuardarUnidadOrganicaAction::class)->ejecutar($this->admin, $unidad, [
            'nombre' => $unidad->nombre,
            'tipo' => $unidad->tipo,
            'parent_id' => $unidad->parent_id,
            'jefe_id' => $unidad->jefe_id,
            'activo' => $unidad->activo,
            ...$cambios,
        ], $jefesAdicionales);
    }

    public function test_un_admin_no_puede_encabezar_una_unidad(): void
    {
        $this->expectException(UsuarioException::class);

        $this->guardarUnidad($this->oficina, ['jefe_id' => $this->otroAdmin->id]);
    }

    public function test_un_admin_no_puede_ser_jefe_de_turno_de_una_unidad(): void
    {
        $this->expectException(UsuarioException::class);

        $this->guardarUnidad($this->oficina, [], [$this->otroAdmin->id]);
    }

    public function test_un_admin_no_puede_ser_jefe_inmediato_adicional(): void
    {
        $trabajador = $this->usuarioDePrueba(['name' => 'Carla', 'unidad_organica_id' => $this->oficina->id]);

        $this->expectException(PapeletaException::class);

        app(AsignarJefeAdicionalAction::class)->ejecutar($trabajador, $this->otroAdmin, $this->admin, true);
    }

    public function test_los_admins_no_aparecen_como_candidatos_a_jefe_en_el_organigrama(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UnidadModal::class)
            ->call('abrir', $this->oficina->id)
            ->assertDontSee($this->otroAdmin->nombre_completo);
    }

    public function test_nuevo_administrador_viene_con_el_rol_admin_marcado(): void
    {
        $this->actingAs($this->admin)
            ->get(route('usuarios-admin.crear', ['tipo' => 'admin']))
            ->assertOk()
            ->assertSee('Nuevo administrador');
    }

    public function test_el_admin_crea_a_otro_admin_sin_unidad_ni_regimen(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Nuevo')
            ->set('apellido', 'Administrador')
            ->set('dni', '40111222')
            ->set('email', 'nuevo.admin@example.com')
            ->set('rolesSeleccionados', [$this->rolId('admin')])
            ->set('unidadOrganicaId', $this->oficina->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $nuevo = User::where('dni', '40111222')->firstOrFail();

        $this->assertTrue($nuevo->hasRole('admin'));
        $this->assertNull($nuevo->unidad_organica_id, 'un admin no pertenece a ninguna unidad');
        $this->assertNull($nuevo->regimen);
    }

    public function test_no_se_le_puede_dar_rol_admin_a_quien_encabeza_una_unidad(): void
    {
        $jefe = User::findOrFail($this->gerencia->jefe_id);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $jefe])
            ->set('rolesSeleccionados', [$this->rolId('admin')])
            ->call('guardar')
            ->assertHasErrors(['rolesSeleccionados']);

        $this->assertFalse($jefe->fresh()->hasRole('admin'));
    }
}
