<?php

namespace Tests\Feature\Papeletas;

use App\Models\Configuracion;
use App\Models\HistorialPapeleta;
use App\Models\Papeleta;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Vencida;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * 1) Papeleta que el jefe aprobó (PENDIENTE_RRHH) y RRHH sale de horario
 *    antes de decidir: pasa a AUTORIZADA_Y_CORRIENDO con revisión post-hoc.
 * 2) El reloj del jefe se lee de RELOJ_JEFE_MINUTOS (la clave que siembra
 *    el seeder y edita el admin), no de una clave inexistente.
 *
 * Horario ordinario sembrado: 07:45-16:15, lunes a viernes. Lunes de
 * referencia: 2026-09-21.
 */
class CierreDeRrhhYRelojDeJefeTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        // Sin personal RRHH designado, RRHH nunca está "en horario".
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function ir(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
    }

    private function vencimientos(): void
    {
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();
    }

    private function pendienteDeRrhh(string $finTurno): Papeleta
    {
        return $this->papeletaDePrueba($this->usuarioDePrueba(), PendienteRrhh::class, [
            'fin_turno_at' => $finTurno,
            'jefe_resuelto_at' => now(),
        ]);
    }

    public function test_mientras_rrhh_sigue_en_horario_la_papeleta_espera_su_decision(): void
    {
        $this->ir('2026-09-21 16:10:00');
        $papeleta = $this->pendienteDeRrhh('2026-09-21 22:00:00');

        $this->vencimientos();

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));
    }

    public function test_al_salir_rrhh_la_papeleta_aprobada_por_el_jefe_pasa_a_autorizada_con_revision_posthoc(): void
    {
        $this->ir('2026-09-21 16:10:00');
        $papeleta = $this->pendienteDeRrhh('2026-09-21 22:00:00');

        $this->ir('2026-09-21 16:16:00');
        $this->vencimientos();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(AutorizadaYCorriendo::class));
        $this->assertTrue($papeleta->autorizado_con_rrhh_fuera_horario);
        $this->assertSame('pendiente', $papeleta->revision_posthoc_estado);
        $this->assertSame('2026-09-21 16:16:00', $papeleta->hora_salida_real->format('Y-m-d H:i:s'));

        $historial = HistorialPapeleta::where('papeleta_id', $papeleta->id)->latest('id')->first();
        $this->assertSame('sistema', $historial->actor_tipo);
        $this->assertSame('PendienteRrhh', $historial->estado_anterior);
        $this->assertSame('AutorizadaYCorriendo', $historial->estado_nuevo);
    }

    public function test_en_fin_de_semana_tambien_se_autoriza_porque_rrhh_no_esta_en_horario(): void
    {
        $this->ir('2026-09-26 10:00:00'); // sábado
        $papeleta = $this->pendienteDeRrhh('2026-09-26 22:00:00');

        $this->vencimientos();

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
    }

    public function test_si_el_turno_ya_termino_queda_vencida_y_no_autorizada(): void
    {
        $this->ir('2026-09-21 15:00:00');
        $papeleta = $this->pendienteDeRrhh('2026-09-21 16:00:00');

        $this->ir('2026-09-21 16:16:00');
        $this->vencimientos();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(Vencida::class));
        $this->assertFalse((bool) $papeleta->autorizado_con_rrhh_fuera_horario);
    }

    public function test_el_reloj_del_jefe_se_lee_de_reloj_jefe_minutos(): void
    {
        $this->ir('2026-09-21 10:00:00');

        $jefeArea = $this->usuarioDePrueba(['regimen' => '276']);
        $trabajador = $this->usuarioDePrueba();
        $papeleta = $this->papeletaDePrueba($trabajador, PendienteJefe::class, [
            'jefe_area_id' => $jefeArea->id,
            'fin_turno_at' => '2026-09-21 22:00:00',
        ]);
        DB::table('papeletas')->where('id', $papeleta->id)->update(['created_at' => now()->subMinutes(7)]);

        // Con 10 minutos, a los 7 todavía no escala.
        Configuracion::where('clave', 'RELOJ_JEFE_MINUTOS')->first()->update(['valor' => '10']);
        $this->vencimientos();
        $this->assertNull($papeleta->fresh()->escalado_jefe_area_at);

        // Con 5 minutos, sí.
        Configuracion::where('clave', 'RELOJ_JEFE_MINUTOS')->first()->update(['valor' => '5']);
        $this->vencimientos();
        $this->assertNotNull($papeleta->fresh()->escalado_jefe_area_at);
    }
}
