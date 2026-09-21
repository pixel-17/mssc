<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Models\ConfiguracionTurno;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Vencida;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * 728 sin fila de turno (modo no estricto): si su ciclo configurado es
 * Noche y crea la papeleta dentro de la ventana nocturna, el fin es el
 * amanecer, no las 23:59. Fecha de referencia: lunes 2026-09-21.
 */
class FinDeTurnoNocheSinTurnoCargadoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
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

    private function trabajadorConCiclo(?string $turno): User
    {
        $trabajador = $this->usuarioDePrueba();

        if ($turno !== null) {
            ConfiguracionTurno::create([
                'user_id' => $trabajador->id,
                'turno' => $turno,
                'fecha_ancla' => '2026-09-01',
                'dias_trabajo' => 6,
                'dias_descanso' => 1,
            ]);
        }

        return $trabajador;
    }

    private function crear(User $trabajador): Papeleta
    {
        return app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
    }

    private function finDe(Papeleta $papeleta): string
    {
        return $papeleta->fresh()->fin_turno_at->format('Y-m-d H:i:s');
    }

    public function test_de_noche_antes_de_medianoche_termina_al_amanecer_siguiente(): void
    {
        $this->ir('2026-09-21 23:30:00');

        $this->assertSame('2026-09-22 06:00:00', $this->finDe($this->crear($this->trabajadorConCiclo('NOCHE'))));
    }

    public function test_de_madrugada_termina_al_amanecer_de_hoy(): void
    {
        $this->ir('2026-09-22 03:00:00');

        $this->assertSame('2026-09-22 06:00:00', $this->finDe($this->crear($this->trabajadorConCiclo('NOCHE'))));
    }

    public function test_fuera_de_la_ventana_nocturna_cae_al_cierre_del_dia(): void
    {
        $this->ir('2026-09-21 10:00:00');

        $this->assertSame('2026-09-21 23:59:59', $this->finDe($this->crear($this->trabajadorConCiclo('NOCHE'))));
    }

    public function test_un_ciclo_que_no_es_noche_no_cambia_el_criterio(): void
    {
        $this->ir('2026-09-21 23:30:00');

        $this->assertSame('2026-09-21 23:59:59', $this->finDe($this->crear($this->trabajadorConCiclo('MANANA'))));
    }

    public function test_sin_ciclo_configurado_cae_al_cierre_del_dia(): void
    {
        $this->ir('2026-09-21 23:30:00');

        $this->assertSame('2026-09-21 23:59:59', $this->finDe($this->crear($this->trabajadorConCiclo(null))));
    }

    public function test_con_una_fila_de_descanso_reciente_no_se_infiere_turno(): void
    {
        $this->ir('2026-09-21 23:30:00');

        $trabajador = $this->trabajadorConCiclo('NOCHE');

        DB::table('turnos')->insert([
            'user_id' => $trabajador->id,
            'sede_id' => $this->sedeDePrueba()->id,
            'fecha' => '2026-09-21',
            'hora_inicio' => null,
            'hora_fin' => null,
            'es_descanso' => true,
            'turno' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('2026-09-21 23:59:59', $this->finDe($this->crear($trabajador)));
    }

    public function test_la_papeleta_no_vence_a_medianoche_sino_al_amanecer(): void
    {
        $this->ir('2026-09-21 23:30:00');

        $papeleta = $this->crear($this->trabajadorConCiclo('NOCHE'));

        $this->ir('2026-09-22 00:30:00');
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class), 'sigue vigente pasada la medianoche');

        $this->ir('2026-09-22 06:01:00');
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();

        $this->assertTrue($papeleta->fresh()->estado->equals(Vencida::class), 'vence al terminar la noche');
    }
}
