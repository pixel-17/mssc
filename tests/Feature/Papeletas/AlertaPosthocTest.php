<?php

namespace Tests\Feature\Papeletas;

use App\Models\Configuracion;
use App\Models\Papeleta;
use App\Notifications\AlertaOrganizacionalNotification;
use App\Services\AlertaPosthocService;
use App\States\Papeleta\AutorizadaYCorriendo;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Alerta cuando la cola de revisión post-hoc se acumula (cantidad) o
 * envejece (horas), como máximo una vez por día.
 */
class AlertaPosthocTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        $this->travelTo(Carbon::parse('2026-09-21 12:00:00'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function posthocPendiente(int $horasAtras = 1, string $revision = 'pendiente'): Papeleta
    {
        $trabajador = $this->usuarioDePrueba();

        return $this->papeletaDePrueba($trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => $revision,
            'hora_salida_real' => now()->subHours($horasAtras),
        ]);
    }

    private function ejecutar(): bool
    {
        return app(AlertaPosthocService::class)->evaluar();
    }

    private function configurar(string $clave, string $valor): void
    {
        Configuracion::where('clave', $clave)->firstOrFail()->update(['valor' => $valor]);
    }

    public function test_sin_pendientes_no_avisa(): void
    {
        Notification::fake();
        $this->usuarioDePrueba([], ['rrhh']);

        $this->assertFalse($this->ejecutar());
        Notification::assertNothingSent();
    }

    public function test_pocas_y_recientes_no_avisan(): void
    {
        Notification::fake();
        $this->usuarioDePrueba([], ['rrhh']);
        $this->posthocPendiente(2);
        $this->posthocPendiente(3);

        $this->assertFalse($this->ejecutar());
        Notification::assertNothingSent();
    }

    public function test_avisa_al_llegar_al_umbral_de_cantidad(): void
    {
        Notification::fake();
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $this->configurar('POSTHOC_ALERTA_CANTIDAD', '3');

        $this->posthocPendiente();
        $this->posthocPendiente();
        $this->assertFalse($this->ejecutar());

        $this->posthocPendiente();
        $this->assertTrue($this->ejecutar());

        Notification::assertSentTo($rrhh, AlertaOrganizacionalNotification::class);
    }

    public function test_avisa_si_la_mas_antigua_supera_el_umbral_de_horas(): void
    {
        Notification::fake();
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $this->posthocPendiente(30);

        $this->assertTrue($this->ejecutar());
        Notification::assertSentTo($rrhh, AlertaOrganizacionalNotification::class);
    }

    public function test_las_ya_revisadas_no_cuentan(): void
    {
        Notification::fake();
        $this->usuarioDePrueba([], ['rrhh']);

        $this->posthocPendiente(48, 'aprobada');
        $this->posthocPendiente(48, 'observada');

        $this->assertFalse($this->ejecutar());
        Notification::assertNothingSent();
    }

    public function test_solo_avisa_una_vez_por_dia(): void
    {
        Notification::fake();
        $this->usuarioDePrueba([], ['rrhh']);
        $this->posthocPendiente(30);

        $this->assertTrue($this->ejecutar());
        $this->assertFalse($this->ejecutar());

        $this->travel(1)->days();

        $this->assertTrue($this->ejecutar());
    }

    public function test_admin_recibe_el_aviso_y_un_rrhh_inactivo_no(): void
    {
        Notification::fake();
        $rrhhActivo = $this->usuarioDePrueba([], ['rrhh']);
        $rrhhInactivo = $this->usuarioDePrueba(['activo' => false], ['rrhh']);
        $admin = $this->usuarioDePrueba([], ['admin']);
        $this->posthocPendiente(30);

        $this->assertTrue($this->ejecutar());

        Notification::assertSentTo($rrhhActivo, AlertaOrganizacionalNotification::class);
        Notification::assertSentTo($admin, AlertaOrganizacionalNotification::class);
        Notification::assertNotSentTo($rrhhInactivo, AlertaOrganizacionalNotification::class);
    }

    public function test_el_comando_corre_sin_errores(): void
    {
        Notification::fake();
        $this->usuarioDePrueba([], ['rrhh']);
        $this->posthocPendiente(30);

        $this->artisan('papeletas:avisar-posthoc-acumulado')
            ->expectsOutputToContain('Alerta de post-hoc acumulado enviada')
            ->assertSuccessful();
    }
}
