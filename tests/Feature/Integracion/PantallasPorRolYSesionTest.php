<?php

namespace Tests\Feature\Integracion;

use App\Livewire\Auth\ActualizarPasswordInicial;
use App\Models\UnidadOrganica;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class PantallasPorRolYSesionTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $rrhh;

    private User $jefe;

    private User $trabajador;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        $this->admin = $this->usuarioDePrueba(['regimen' => '276'], ['admin']);
        $this->rrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $this->ajeno = $this->usuarioDePrueba(['regimen' => '728']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefe->id]);
        $this->jefe->update(['unidad_organica_id' => $unidad->id]);
        $this->trabajador->update(['unidad_organica_id' => $unidad->id]);

        $this->jefe = $this->jefe->fresh();
        $this->trabajador = $this->trabajador->fresh();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_las_pantallas_sin_cobertura_previa_exigen_sesion(): void
    {
        foreach ([
            route('reportes.sustentos'),
            route('reportes.trabajador-historial'),
            route('turnos.calendario.equipo'),
            route('turnos.calendario.individual'),
            route('turnos.calendario.individual-de', $this->trabajador),
            route('turnos.programacion.equipo'),
            route('turnos.configuracion', $this->trabajador),
            route('turnos.programacion', $this->trabajador),
            route('password.actualizar-inicial'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_admin_rrhh_y_jefe_abren_los_reportes_generales(): void
    {
        foreach ([$this->admin, $this->rrhh, $this->jefe] as $usuario) {
            $this->actingAs($usuario)->get(route('reportes.sustentos'))->assertOk();
            $this->actingAs($usuario)->get(route('reportes.trabajador-historial'))->assertOk();
        }
    }

    public function test_un_trabajador_sin_jefatura_es_enviado_a_su_lista_de_papeletas_en_vez_de_ver_reportes(): void
    {
        foreach ([$this->trabajador, $this->ajeno] as $usuario) {
            foreach (['reportes.sustentos', 'reportes.trabajador-historial', 'dashboard'] as $ruta) {
                $this->actingAs($usuario)
                    ->get(route($ruta))
                    ->assertRedirect(route('trabajador.papeletas.index'));
            }
        }
    }

    public function test_el_calendario_individual_propio_abre_para_cualquier_usuario(): void
    {
        foreach ([$this->admin, $this->rrhh, $this->jefe, $this->trabajador, $this->ajeno] as $usuario) {
            $this->actingAs($usuario)->get(route('turnos.calendario.individual'))->assertOk();
        }
    }

    public function test_el_calendario_de_otro_trabajador_solo_lo_ven_el_dueno_su_jefe_y_admin(): void
    {
        $url = route('turnos.calendario.individual-de', $this->trabajador);

        foreach ([$this->trabajador, $this->jefe, $this->admin] as $usuario) {
            $this->actingAs($usuario)->get($url)->assertOk();
        }

        $this->actingAs($this->ajeno)->get($url)->assertForbidden();
    }

    public function test_la_configuracion_de_turno_de_un_trabajador_solo_la_abre_quien_puede_gestionarlo(): void
    {
        $url = route('turnos.configuracion', $this->trabajador);

        foreach ([$this->jefe, $this->admin] as $usuario) {
            $this->actingAs($usuario)->get($url)->assertOk();
        }

        foreach ([$this->ajeno, $this->trabajador] as $usuario) {
            $this->actingAs($usuario)->get($url)->assertForbidden();
        }
    }

    public function test_la_programacion_mensual_de_un_trabajador_respeta_la_misma_regla(): void
    {
        $url = route('turnos.programacion', $this->trabajador);

        foreach ([$this->jefe, $this->admin] as $usuario) {
            $this->actingAs($usuario)->get($url)->assertOk();
        }

        $this->actingAs($this->ajeno)->get($url)->assertForbidden();
    }

    public function test_la_programacion_de_equipo_abre_para_el_jefe_y_el_admin(): void
    {
        foreach ([$this->jefe, $this->admin] as $usuario) {
            $this->actingAs($usuario)->get(route('turnos.programacion.equipo'))->assertOk();
        }
    }

    public function test_quien_debe_actualizar_su_password_queda_atrapado_hasta_hacerlo(): void
    {
        $nuevo = User::factory()->create([
            'dni' => '12345678',
            'debe_actualizar_password' => true,
        ]);

        foreach ([route('dashboard'), route('reportes.sustentos'), route('turnos.calendario.individual')] as $url) {
            $this->actingAs($nuevo)->get($url)->assertRedirect(route('password.actualizar-inicial'));
        }

        $this->actingAs($nuevo)->get(route('password.actualizar-inicial'))->assertOk();
    }

    public function test_actualizar_password_inicial_valida_y_libera_al_usuario(): void
    {
        $nuevo = User::factory()->create([
            'dni' => '12345678',
            'password' => Hash::make('12345678'),
            'debe_actualizar_password' => true,
        ]);

        $this->actingAs($nuevo);

        Livewire::test(ActualizarPasswordInicial::class)
            ->set('password', 'corta')
            ->set('password_confirmation', 'corta')
            ->call('actualizar')
            ->assertHasErrors('password');

        Livewire::test(ActualizarPasswordInicial::class)
            ->set('password', '12345678')
            ->set('password_confirmation', '12345678')
            ->call('actualizar')
            ->assertHasErrors('password');

        Livewire::test(ActualizarPasswordInicial::class)
            ->set('password', 'Clave-Segura-2026')
            ->set('password_confirmation', 'otra-distinta')
            ->call('actualizar')
            ->assertHasErrors('password');

        $this->assertTrue($nuevo->fresh()->debe_actualizar_password);

        Livewire::test(ActualizarPasswordInicial::class)
            ->set('password', 'Clave-Segura-2026')
            ->set('password_confirmation', 'Clave-Segura-2026')
            ->call('actualizar')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $nuevo->refresh();
        $this->assertFalse($nuevo->debe_actualizar_password);
        $this->assertTrue(Hash::check('Clave-Segura-2026', $nuevo->password));

        $this->actingAs($nuevo)
            ->get(route('reportes.sustentos'))
            ->assertRedirect(route('trabajador.papeletas.index'));
    }

    public function test_la_baja_de_suscripcion_push_solo_borra_la_del_usuario_autenticado(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';
        $datos = [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'clave-publica-de-prueba', 'auth' => 'secreto-de-prueba'],
        ];

        $this->actingAs($this->trabajador)->postJson(route('push-subscriptions.store'), $datos)->assertOk();
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint]);

        $this->actingAs($this->ajeno)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $endpoint])
            ->assertOk();
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => $endpoint]);

        $this->actingAs($this->trabajador)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJson(['ok' => true]);
        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }

    public function test_la_baja_de_suscripcion_exige_endpoint_y_sesion(): void
    {
        $this->actingAs($this->trabajador)
            ->deleteJson(route('push-subscriptions.destroy'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');

        auth()->forgetGuards();
        $this->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/x'])
            ->assertUnauthorized();
    }
}
