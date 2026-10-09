<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\Services\NotificarPapeletaService;
use App\States\Papeleta\PendienteJefe;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regla de negocio: para que la papeleta de un trabajador le llegue a su
 * jefe inmediato, el jefe inmediato también debe tener turno asignado y
 * vigente. Caso reportado: trabajador 276 con jefe inmediato 728 al que
 * nunca se le asignó turno — la papeleta igual le llegaba. Fecha de
 * referencia: lunes 2026-09-21, 10:00.
 */
class JefeConTurnoRequeridoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function crear(User $trabajador): Papeleta
    {
        return app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
    }

    public function test_trabajador_276_con_jefe_728_sin_turno_no_puede_crear_y_nadie_recibe_la_papeleta(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador); // jefe 728, SIN turno

        try {
            $this->crear($trabajador->fresh());
            $this->fail('Debió bloquear: el jefe 728 no tiene turno asignado.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('no tiene un turno vigente', $e->getMessage());
        }

        $this->assertSame(0, Papeleta::where('trabajador_id', $trabajador->id)->count());
        $this->assertSame(0, Papeleta::deJefeInmediato($jefe)->count());
    }

    public function test_trabajador_276_con_jefe_728_en_descanso_no_puede_crear(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($jefe, ['es_descanso' => true, 'hora_inicio' => null, 'hora_fin' => null]);

        $this->expectException(PapeletaException::class);
        $this->crear($trabajador->fresh());
    }

    public function test_trabajador_276_con_jefe_728_con_turno_vigente_si_crea_y_le_llega_al_jefe(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($jefe);

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
        $this->assertTrue($papeleta->tieneComoJefeInmediatoA($jefe));
        $this->assertSame(1, Papeleta::deJefeInmediato($jefe)->count());
    }

    public function test_trabajador_276_con_jefe_728_con_turno_de_otro_horario_no_puede_crear(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        // Turno Tarde (14:00-22:00): a las 10:00 el jefe NO está de servicio.
        $this->turnoDePrueba($jefe, ['turno' => 'TARDE', 'hora_inicio' => '14:00:00', 'hora_fin' => '22:00:00']);

        $this->expectException(PapeletaException::class);
        $this->crear($trabajador->fresh());
    }

    public function test_trabajador_276_con_jefe_276_no_exige_turno_al_jefe(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = User::factory()->create(['regimen' => '276', 'sede_id' => $trabajador->sede_id]);
        $this->conJefeDePrueba($trabajador, $jefe);

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
    }

    public function test_trabajador_728_exige_el_mismo_turno_en_el_jefe(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($trabajador, ['turno' => 'MANANA', 'hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00']);

        // Jefe en TARDE mientras el trabajador está en MAÑANA: no coinciden.
        $this->turnoDePrueba($jefe, ['turno' => 'TARDE', 'hora_inicio' => '14:00:00', 'hora_fin' => '22:00:00']);

        $this->expectException(PapeletaException::class);
        $this->crear($trabajador->fresh());
    }

    public function test_trabajador_728_con_jefe_en_el_mismo_turno_si_crea(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($trabajador, ['turno' => 'MANANA', 'hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00']);
        $this->turnoDePrueba($jefe, ['turno' => 'MANANA', 'hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00']);

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
        $this->assertTrue($papeleta->tieneComoJefeInmediatoA($jefe));
    }

    public function test_la_notificacion_de_nueva_papeleta_solo_va_al_jefe_con_turno(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($jefe);

        $papeleta = $this->crear($trabajador->fresh());

        \Illuminate\Support\Facades\Notification::assertSentTo($jefe, \App\Notifications\PapeletaNotification::class);
        $this->assertInstanceOf(NotificarPapeletaService::class, app(NotificarPapeletaService::class));
        $this->assertSame(1, $papeleta->jefesCandidatos()->count());
    }
}
