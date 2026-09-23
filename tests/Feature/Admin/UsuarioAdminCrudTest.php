<?php

namespace Tests\Feature\Admin;

use App\Livewire\Usuarios\UsuarioAdminForm;
use App\Livewire\Usuarios\UsuarioAdminIndex;
use App\Models\ConfiguracionTurno;
use App\Models\JefeTurno;
use App\Models\Turno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Alta, edición y listado de usuarios desde el panel de admin
 * (usuarios-admin.*). La desactivación/reactivación y la protección del
 * único RR. HH. ya tienen sus propios tests en tests/Feature/Usuarios.
 *
 * Los usuarios que se EDITAN son de régimen 276 a propósito: un 728 sin
 * ConfiguracionTurno obliga a cargar el turno en el mismo formulario.
 */
class UsuarioAdminCrudTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        $this->admin = $this->usuarioDePrueba([], ['admin']);
    }

    private function rolId(string $nombre): int
    {
        return (int) Role::where('name', $nombre)->value('id');
    }

    /**
     * Formulario de alta con los datos mínimos válidos para un 276.
     *
     * @param  array<string, mixed>  $datos
     */
    private function formularioDeAlta(array $datos = [])
    {
        $datos = [
            'name' => 'Lucía',
            'apellido' => 'Quispe',
            'dni' => '12345678',
            'email' => 'lucia@example.com',
            'regimen' => '276',
            ...$datos,
        ];

        $componente = Livewire::actingAs($this->admin)->test(UsuarioAdminForm::class);

        foreach ($datos as $campo => $valor) {
            $componente->set($campo, $valor);
        }

        return $componente;
    }

    // ---------------------------------------------------------------- alta

    public function test_el_admin_da_de_alta_un_276_con_su_rol_y_la_contrasena_es_su_dni(): void
    {
        $sede = $this->sedeDePrueba();

        $this->formularioDeAlta([
            'sedeId' => $sede->id,
            'rolesSeleccionados' => [$this->rolId('trabajador')],
        ])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios-admin.index'));

        $usuario = User::where('dni', '12345678')->firstOrFail();

        $this->assertSame('Lucía', $usuario->name);
        $this->assertSame('Quispe', $usuario->apellido);
        $this->assertSame('lucia@example.com', $usuario->email);
        $this->assertSame('276', $usuario->regimen);
        $this->assertSame($sede->id, $usuario->sede_id);
        $this->assertTrue($usuario->activo);
        $this->assertTrue($usuario->hasRole('trabajador'));
        $this->assertTrue(Hash::check('12345678', $usuario->password), 'la contraseña inicial es el DNI');
        $this->assertTrue((bool) $usuario->debe_actualizar_password);
    }

    public function test_al_crear_se_ignora_la_contrasena_escrita_y_se_usa_el_dni(): void
    {
        $this->formularioDeAlta(['password' => 'OtraClave12345'])
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('dni', '12345678')->firstOrFail();

        $this->assertTrue(Hash::check('12345678', $usuario->password));
        $this->assertFalse(Hash::check('OtraClave12345', $usuario->password));
    }

    public function test_los_campos_obligatorios_del_alta(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class)
            ->call('guardar')
            ->assertHasErrors(['name', 'apellido', 'dni', 'email', 'regimen']);
    }

    public function test_el_dni_debe_tener_8_digitos(): void
    {
        foreach (['1234567', '123456789', 'abcdefgh'] as $malo) {
            $this->formularioDeAlta(['dni' => $malo])
                ->call('guardar')
                ->assertHasErrors('dni');
        }

        $this->assertFalse(User::where('email', 'lucia@example.com')->exists());
    }

    public function test_dni_y_email_no_pueden_repetirse(): void
    {
        $existente = $this->usuarioDePrueba(['dni' => '87654321', 'email' => 'ya@example.com']);

        $this->formularioDeAlta(['dni' => $existente->dni])
            ->call('guardar')
            ->assertHasErrors(['dni' => 'unique']);

        $this->formularioDeAlta(['email' => $existente->email])
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_el_regimen_solo_puede_ser_276_o_728(): void
    {
        $this->formularioDeAlta(['regimen' => '1057'])
            ->call('guardar')
            ->assertHasErrors(['regimen' => 'in']);
    }

    public function test_el_email_debe_ser_valido(): void
    {
        $this->formularioDeAlta(['email' => 'no-es-un-correo'])
            ->call('guardar')
            ->assertHasErrors(['email' => 'email']);
    }

    public function test_un_728_nuevo_exige_su_turno_en_el_mismo_formulario(): void
    {
        $this->formularioDeAlta(['regimen' => '728'])
            ->call('guardar')
            ->assertHasErrors(['turno' => 'required']);

        $this->assertFalse(User::where('dni', '12345678')->exists());
    }

    public function test_un_728_no_acepta_el_turno_de_un_276(): void
    {
        $this->formularioDeAlta([
            'regimen' => '728',
            'turno' => 'DIA',
            'fechaAncla' => '2026-09-21',
        ])
            ->call('guardar')
            ->assertHasErrors(['turno' => 'in']);

        $this->assertFalse(User::where('dni', '12345678')->exists());
    }

    public function test_los_dias_de_trabajo_y_descanso_tienen_limites(): void
    {
        $this->formularioDeAlta([
            'regimen' => '728',
            'turno' => 'MANANA',
            'fechaAncla' => '2026-09-21',
            'diasTrabajo' => 0,
            'diasDescanso' => 31,
        ])
            ->call('guardar')
            ->assertHasErrors(['diasTrabajo' => 'min', 'diasDescanso' => 'max']);
    }

    public function test_un_728_con_turno_queda_con_su_configuracion_y_el_mes_generado(): void
    {
        $this->formularioDeAlta([
            'regimen' => '728',
            'turno' => 'MANANA',
            'fechaAncla' => '2026-09-21',
            'diasTrabajo' => 6,
            'diasDescanso' => 1,
            'rolesSeleccionados' => [$this->rolId('trabajador')],
        ])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios-admin.index'));

        $usuario = User::where('dni', '12345678')->firstOrFail();
        $configuracion = ConfiguracionTurno::where('user_id', $usuario->id)->firstOrFail();

        $this->assertSame('MANANA', $configuracion->turno);
        $this->assertSame(6, $configuracion->dias_trabajo);
        $this->assertSame(1, $configuracion->dias_descanso);
        $this->assertSame($this->admin->id, $configuracion->actualizado_por_id);

        // Septiembre de 2026 tiene 30 días: un turno o descanso por cada uno.
        $this->assertSame(30, Turno::where('user_id', $usuario->id)->count());
        $this->assertGreaterThan(0, Turno::where('user_id', $usuario->id)->where('es_descanso', true)->count());
    }

    public function test_al_dar_de_alta_en_una_unidad_se_calcula_su_jefe_inmediato(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina de Rentas', 'jefe_id' => $jefe->id]);

        $this->formularioDeAlta(['unidadOrganicaId' => $unidad->id])
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = User::where('dni', '12345678')->firstOrFail();

        $this->assertSame($unidad->id, $usuario->unidad_organica_id);
        $this->assertSame($jefe->id, $usuario->jefe_inmediato_id);
    }

    // ------------------------------------------------------------- edición

    public function test_editar_precarga_los_datos_y_conserva_la_contrasena(): void
    {
        $usuario = $this->usuarioDePrueba(['regimen' => '276', 'name' => 'Original'], ['trabajador']);
        $hashAntes = $usuario->password;

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->assertSet('name', 'Original')
            ->assertSet('dni', $usuario->dni)
            ->assertSet('regimen', '276')
            ->assertSet('rolesSeleccionados', [$this->rolId('trabajador')])
            ->set('name', 'Cambiado')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios-admin.index'));

        $usuario = $usuario->fresh();

        $this->assertSame('Cambiado', $usuario->name);
        $this->assertSame($hashAntes, $usuario->password);
        $this->assertFalse((bool) $usuario->debe_actualizar_password);
    }

    public function test_editar_permite_conservar_su_propio_dni_y_email_pero_no_tomar_los_de_otro(): void
    {
        $otro = $this->usuarioDePrueba(['dni' => '11112222', 'email' => 'otro@example.com']);
        $usuario = $this->usuarioDePrueba(['regimen' => '276']);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->call('guardar')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->set('dni', $otro->dni)
            ->set('email', $otro->email)
            ->call('guardar')
            ->assertHasErrors(['dni' => 'unique', 'email' => 'unique']);
    }

    public function test_editar_sincroniza_los_roles(): void
    {
        $usuario = $this->usuarioDePrueba(['regimen' => '276'], ['trabajador']);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->set('rolesSeleccionados', [$this->rolId('rrhh')])
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = $usuario->fresh();

        $this->assertTrue($usuario->hasRole('rrhh'));
        $this->assertFalse($usuario->hasRole('trabajador'));
    }

    public function test_el_admin_puede_resetear_la_contrasena_y_se_le_pedira_actualizarla(): void
    {
        $usuario = $this->usuarioDePrueba(['regimen' => '276']);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->set('password', 'ClaveNueva2026')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario = $usuario->fresh();

        $this->assertTrue(Hash::check('ClaveNueva2026', $usuario->password));
        $this->assertTrue((bool) $usuario->debe_actualizar_password);
    }

    public function test_una_contrasena_corta_se_rechaza_al_editar(): void
    {
        $usuario = $this->usuarioDePrueba(['regimen' => '276']);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->set('password', 'corta')
            ->call('guardar')
            ->assertHasErrors(['password' => 'min']);
    }

    public function test_cambiar_de_unidad_recalcula_el_jefe_inmediato(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina de Logística', 'jefe_id' => $jefe->id]);
        $usuario = $this->usuarioDePrueba(['regimen' => '276']);

        $this->assertNull($usuario->fresh()->jefe_inmediato_id);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->set('unidadOrganicaId', $unidad->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($jefe->id, $usuario->fresh()->jefe_inmediato_id);
    }

    public function test_editar_un_728_sin_configuracion_de_turno_la_exige(): void
    {
        $usuario = $this->usuarioDePrueba(['regimen' => '728']);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $usuario->fresh()])
            ->set('name', 'Sin turno')
            ->call('guardar')
            ->assertHasErrors(['turno' => 'required']);

        $this->assertNotSame('Sin turno', $usuario->fresh()->name);
    }

    public function test_no_se_puede_desactivar_desde_el_formulario_a_un_jefe_titular_de_turno(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina de Guardia', 'jefe_id' => $jefe->id]);
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'NOCHE', 'jefe_id' => $jefe->id]);

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminForm::class, ['usuario' => $jefe->fresh()])
            ->set('activo', false)
            ->call('guardar')
            ->assertHasErrors('activo');

        $this->assertTrue($jefe->fresh()->activo);
    }

    public function test_quien_no_es_admin_no_puede_guardar(): void
    {
        $trabajador = $this->usuarioDePrueba();

        Livewire::actingAs($trabajador)
            ->test(UsuarioAdminForm::class)
            ->set('name', 'Colado')
            ->set('apellido', 'Colado')
            ->set('dni', '99999999')
            ->set('email', 'colado@example.com')
            ->set('regimen', '276')
            ->call('guardar')
            ->assertForbidden();

        $this->assertFalse(User::where('dni', '99999999')->exists());
    }

    // ------------------------------------------------------------- listado

    public function test_el_listado_busca_por_nombre_apellido_dni_y_email(): void
    {
        $ana = $this->usuarioDePrueba(['name' => 'Xiomara', 'apellido' => 'Mamani', 'dni' => '11111111', 'email' => 'xiomara@example.com']);
        $beto = $this->usuarioDePrueba(['name' => 'Yaguar', 'apellido' => 'Condori', 'dni' => '22222222', 'email' => 'yaguar@example.com']);

        $ids = fn ($usuarios) => collect($usuarios->items())->pluck('id')->all();

        foreach (['Xiomara', 'Mamani', '11111111', 'xiomara@'] as $busqueda) {
            Livewire::actingAs($this->admin)
                ->test(UsuarioAdminIndex::class)
                ->set('buscar', $busqueda)
                ->assertViewHas('usuarios', fn ($usuarios) => $ids($usuarios) === [$ana->id]);
        }

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->set('buscar', 'Condori')
            ->assertViewHas('usuarios', fn ($usuarios) => $ids($usuarios) === [$beto->id]);
    }

    public function test_el_listado_filtra_por_regimen_y_por_rol(): void
    {
        $del276 = $this->usuarioDePrueba(['regimen' => '276', 'name' => 'Xiomara'], ['trabajador']);
        $del728 = $this->usuarioDePrueba(['regimen' => '728', 'name' => 'Yaguar'], ['rrhh']);

        $ids = fn ($usuarios) => collect($usuarios->items())->pluck('id')->all();

        Livewire::actingAs($this->admin)
            ->test(UsuarioAdminIndex::class)
            ->set('regimen', '276')
            ->assertViewHas('usuarios', fn ($usuarios) => in_array($del276->id, $ids($usuarios), true) && ! in_array($del728->id, $ids($usuarios), true))
            ->set('regimen', '728')
            ->assertViewHas('usuarios', fn ($usuarios) => in_array($del728->id, $ids($usuarios), true) && ! in_array($del276->id, $ids($usuarios), true))
            ->set('regimen', null)
            ->set('rolId', $this->rolId('rrhh'))
            ->assertViewHas('usuarios', fn ($usuarios) => $ids($usuarios) === [$del728->id]);
    }
}
