<?php

namespace Tests\Feature\Turnos;

use App\Models\Turno;
use App\Models\User;
use App\Services\DeterminadorFinDeTurno;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Vencida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Régimen 728: una papeleta de un turno que cruza medianoche (Noche 22:00-06:00)
 * no puede vencer antes de que termine el turno. Su dia_operativo es el día en
 * que EMPEZÓ el turno, así que antes vencía a las 00:00, o al minuto siguiente
 * si se creaba pasada la medianoche.
 */
class VencimientoNocturnoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private const DIA = '2026-09-20';

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->trabajador = $this->usuarioDePrueba(); // régimen 728
    }

    private function turno(string $inicio, string $fin, string $codigo): void
    {
        Turno::create([
            'user_id' => $this->trabajador->id,
            'sede_id' => $this->sedeDePrueba()->id,
            'fecha' => self::DIA,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'es_descanso' => false,
            'turno' => $codigo,
        ]);
    }

    /**
     * Igual que CrearPapeletaAction: el fin del turno se fija UNA vez, al
     * crear la papeleta (fin_turno_at). Sin turno cargado queda NULL y
     * DeterminadorFinDeTurno usa el cierre del día operativo.
     */
    private function papeleta()
    {
        $turno = Turno::where('user_id', $this->trabajador->id)->whereDate('fecha', self::DIA)->first();

        return $this->papeletaDePrueba($this->trabajador, PendienteJefe::class, [
            'dia_operativo' => self::DIA,
            'fin_turno_at' => $turno?->finReal(),
        ]);
    }

    private function terminado(string $ahora): bool
    {
        $papeleta = $this->papeleta();
        $this->travelTo($ahora);

        return app(DeterminadorFinDeTurno::class)->yaTermino($papeleta);
    }

    public function test_turno_noche_no_termina_a_medianoche(): void
    {
        $this->turno('22:00:00', '06:00:00', 'NOCHE');

        $this->assertFalse($this->terminado('2026-09-20 23:30:00'), 'antes de medianoche');
        $this->assertFalse($this->terminado('2026-09-21 00:01:00'), 'recién pasada la medianoche (antes vencía aquí)');
        $this->assertFalse($this->terminado('2026-09-21 03:00:00'), 'de madrugada');
        $this->assertFalse($this->terminado('2026-09-21 06:00:00'), 'justo a la hora de fin');
    }

    public function test_turno_noche_termina_pasada_su_hora_de_fin(): void
    {
        $this->turno('22:00:00', '06:00:00', 'NOCHE');

        $this->assertTrue($this->terminado('2026-09-21 06:01:00'));
    }

    public function test_turno_de_dia_termina_a_su_hora_de_fin_no_a_medianoche(): void
    {
        $this->turno('06:00:00', '14:00:00', 'MANANA');

        $this->assertFalse($this->terminado('2026-09-20 13:59:00'));
        $this->assertTrue($this->terminado('2026-09-20 14:01:00'));
    }

    public function test_sin_turno_cargado_se_usa_la_medianoche_del_dia_operativo(): void
    {
        $this->assertFalse($this->terminado('2026-09-20 23:59:00'));
        $this->assertTrue($this->terminado('2026-09-21 00:00:00'));
    }

    public function test_el_comando_no_vence_la_papeleta_de_madrugada_pero_si_al_terminar_el_turno(): void
    {
        $this->turno('22:00:00', '06:00:00', 'NOCHE');
        $papeleta = $this->papeleta();

        $this->travelTo('2026-09-21 01:00:00');
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class), 'sigue vigente a la 01:00');

        $this->travelTo('2026-09-21 06:01:00');
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();
        $this->assertTrue($papeleta->fresh()->estado->equals(Vencida::class), 'vence al terminar el turno');
    }

    public function test_turno_finReal_calcula_el_dia_siguiente_solo_si_cruza_medianoche(): void
    {
        $this->turno('22:00:00', '06:00:00', 'NOCHE');
        $noche = Turno::where('user_id', $this->trabajador->id)->first();

        $this->assertSame('2026-09-21 06:00:00', $noche->finReal()->format('Y-m-d H:i:s'));

        $noche->update(['hora_inicio' => '06:00:00', 'hora_fin' => '14:00:00']);
        $this->assertSame('2026-09-20 14:00:00', $noche->fresh()->finReal()->format('Y-m-d H:i:s'));

        $noche->update(['es_descanso' => true]);
        $this->assertNull($noche->fresh()->finReal());
    }
}
