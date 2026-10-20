<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\Notifications\PapeletaNotification;
use App\States\Papeleta\PendienteJefe;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un solo jefe por papeleta: el jefe de la unidad del trabajador
 * (jefe_inmediato_id, fotografiado al crear). El turno vigente (728) ya no
 * se exige al decisor, solo a quien crea la papeleta; en la mayoría de
 * estos tests se le carga uno "de sobra" donde no es el foco del test. Lo
 * que SÍ se exige es que el jefe exista y esté activo.
 * Fecha de referencia: lunes 2026-09-21, 10:00.
 */
class JefeUnicoEnPapeletaTest extends TestCase
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

    public function test_trabajador_276_con_jefe_728_con_turno_la_papeleta_le_llega(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador); // 728
        $this->turnoDePrueba($jefe);

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
        $this->assertTrue($papeleta->tieneComoJefeInmediatoA($jefe));
        $this->assertSame(1, Papeleta::deJefeInmediato($jefe)->count());
    }

    public function test_jefe_728_sin_ningun_turno_cargado_ya_no_bloquea_la_creacion(): void
    {
        // El turno vigente se exige a quien CREA la papeleta, nunca a su
        // decisor: que el jefe inmediato (728) no tenga ningún turno
        // cargado ya no bloquea la papeleta de su trabajador.
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador); // 728, sin ningún turno cargado

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
    }

    public function test_trabajador_276_con_jefe_728_en_descanso_tambien_puede_crear(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($jefe, ['es_descanso' => true, 'hora_inicio' => null, 'hora_fin' => null]);

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
    }

    public function test_trabajador_728_sin_turno_vigente_no_puede_crear(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $this->conJefeDePrueba($trabajador);

        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('no tienes un turno vigente');

        $this->crear($trabajador->fresh());
    }

    public function test_trabajador_728_con_turno_y_jefe_con_turno(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($jefe);
        $this->turnoDePrueba($trabajador, ['turno' => 'MANANA', 'hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00']);

        $papeleta = $this->crear($trabajador->fresh());

        $this->assertSame($jefe->id, $papeleta->jefe_inmediato_id);
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_si_el_unico_jefe_esta_dado_de_baja_la_unidad_queda_sin_jefe_activo(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $jefe->forceFill(['activo' => false])->saveQuietly();

        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('no tiene un jefe activo asignado');

        $this->crear($trabajador->fresh());
    }

    public function test_la_notificacion_de_nueva_papeleta_va_al_unico_jefe(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $jefe = $this->conJefeDePrueba($trabajador);
        $this->turnoDePrueba($jefe);

        $this->crear($trabajador->fresh());

        Notification::assertSentTo($jefe, PapeletaNotification::class);
    }
}
