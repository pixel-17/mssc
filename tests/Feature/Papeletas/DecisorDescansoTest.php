<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Actions\Papeleta\DecisorDisponibleService;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un decisor 728 está disponible las 24 h, salvo en su día de descanso.
 * Lunes de referencia: 2026-09-21.
 */
class DecisorDescansoTest extends TestCase
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

    private function disponible(User $decisor, string $momento): bool
    {
        return app(DecisorDisponibleService::class)->estaDisponible($decisor, Carbon::parse($momento));
    }

    /** @return array<string, mixed> */
    private function descanso(string $fecha): array
    {
        return ['fecha' => $fecha, 'es_descanso' => true, 'hora_inicio' => null, 'hora_fin' => null];
    }

    public function test_sin_programacion_cargada_se_asume_disponible(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);

        $this->assertTrue($this->disponible($jefe, '2026-09-21 10:00:00'));
    }

    public function test_en_turno_vigente_esta_disponible(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->turnoDePrueba($jefe, ['fecha' => '2026-09-21']);

        $this->assertTrue($this->disponible($jefe, '2026-09-21 10:00:00'));
    }

    public function test_en_su_dia_de_descanso_no_esta_disponible(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->turnoDePrueba($jefe, $this->descanso('2026-09-21'));

        $this->assertFalse($this->disponible($jefe, '2026-09-21 10:00:00'));
        $this->assertFalse($this->disponible($jefe, '2026-09-21 23:30:00'));
    }

    public function test_el_descanso_de_otro_dia_no_lo_afecta(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->turnoDePrueba($jefe, $this->descanso('2026-09-20'));

        $this->assertTrue($this->disponible($jefe, '2026-09-21 10:00:00'));
    }

    public function test_noche_de_ayer_que_sigue_corriendo_cuenta_como_en_turno_aunque_hoy_descanse(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->turnoDePrueba($jefe, ['fecha' => '2026-09-20', 'hora_inicio' => '22:00:00', 'hora_fin' => '06:00:00']);
        $this->turnoDePrueba($jefe, $this->descanso('2026-09-21'));

        // 03:00 del lunes: aún corre la Noche del domingo.
        $this->assertTrue($this->disponible($jefe, '2026-09-21 03:00:00'));
        // 07:00: la Noche ya terminó y hoy es su descanso.
        $this->assertFalse($this->disponible($jefe, '2026-09-21 07:00:00'));
    }

    public function test_el_descanso_no_afecta_a_un_decisor_276(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $this->turnoDePrueba($jefe, $this->descanso('2026-09-21'));

        // 276 se rige por el horario ordinario, no por filas de turnos.
        $this->assertTrue($this->disponible($jefe, '2026-09-21 10:00:00'));
    }

    /**
     * Organigrama: Gerencia (gerente) > Oficina (jefeOficina). El jefe de la
     * Oficina envía SU PROPIA papeleta, así que sube al gerente. Si el
     * gerente descansa hoy y RRHH está en horario, escala a RRHH.
     */
    private function escenarioJefeQueEnviaSuPropiaPapeleta(bool $gerenteDescansa): string
    {
        $gerente = $this->usuarioDePrueba(['regimen' => '728']);
        $jefeOficina = $this->usuarioDePrueba(['regimen' => '728']);
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);

        $gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $gerente->id]);
        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $gerencia->id, 'jefe_id' => $jefeOficina->id]);

        $gerente->update(['unidad_organica_id' => $gerencia->id]);
        $jefeOficina->update(['unidad_organica_id' => $oficina->id]);

        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        $this->turnoDePrueba($jefeOficina);

        if ($gerenteDescansa) {
            $this->turnoDePrueba($gerente, $this->descanso('2026-09-21'));
        }

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($jefeOficina->fresh(), $this->motivoDe(), ['justificacion' => 'Urgencia']);

        return class_basename($papeleta->fresh()->estado);
    }

    public function test_la_papeleta_de_un_jefe_escala_a_rrhh_si_su_superior_descansa(): void
    {
        $this->assertSame(class_basename(PendienteRrhh::class), $this->escenarioJefeQueEnviaSuPropiaPapeleta(true));
    }

    public function test_la_papeleta_de_un_jefe_espera_a_su_superior_si_este_no_descansa(): void
    {
        $this->assertSame(class_basename(PendienteJefe::class), $this->escenarioJefeQueEnviaSuPropiaPapeleta(false));
    }
}
