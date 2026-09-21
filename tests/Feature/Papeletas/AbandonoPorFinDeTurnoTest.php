<?php

namespace Tests\Feature\Papeletas;

use App\Models\Papeleta;
use App\Models\Retorno;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\FinalizadoSinRetorno;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El abandono no marcado se decide contra fin_turno_at: un 728 de turno
 * Noche que sale a las 23:30 y vuelve a las 00:30 ya no queda como
 * abandono falso a medianoche.
 */
class AbandonoPorFinDeTurnoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
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

    private function papeletaNocheEnCurso(): Papeleta
    {
        return $this->papeletaDePrueba($this->usuarioDePrueba(), AutorizadaYCorriendo::class, [
            'dia_operativo' => '2026-09-21',
            'fin_turno_at' => '2026-09-22 06:00:00',
            'hora_salida_real' => '2026-09-21 23:30:00',
        ]);
    }

    private function abandonos(): void
    {
        $this->artisan('papeletas:procesar-abandono-no-marcado')->assertSuccessful();
    }

    public function test_noche_no_se_marca_abandono_a_medianoche_mientras_el_turno_sigue(): void
    {
        $papeleta = $this->papeletaNocheEnCurso();

        $this->ir('2026-09-22 00:30:00');
        $this->abandonos();

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
    }

    public function test_noche_se_marca_abandono_al_terminar_el_turno_sin_retorno(): void
    {
        $papeleta = $this->papeletaNocheEnCurso();

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(FinalizadoSinRetorno::class));
        $this->assertSame('abandono_no_marcado', $papeleta->causa_finalizacion_sin_retorno);
        $this->assertTrue((bool) $papeleta->requiere_visto_bueno);
    }

    public function test_noche_con_retorno_registrado_no_es_abandono_al_terminar_el_turno(): void
    {
        $papeleta = $this->papeletaNocheEnCurso();

        Retorno::create([
            'papeleta_id' => $papeleta->id,
            'hora_servidor' => '2026-09-22 00:30:00',
            'marcado_manual' => false,
        ]);

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
    }
}
