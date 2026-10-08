<?php

namespace Tests\Feature\Papeletas;

use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El trabajador cancela hasta antes de AUTORIZADA_Y_CORRIENDO. Mientras la
 * papeleta espera a RRHH, solo si RRHH está en horario.
 *
 * Horario ordinario sembrado: 07:45-16:15, lunes a viernes. Lunes de
 * referencia: 2026-09-21.
 */
class CancelarPapeletaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        // Sin personal RRHH designado, RRHH nunca está "en horario".
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->trabajador = $this->usuarioDePrueba();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function cancelar(string $estado)
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, $estado);

        $this->actingAs($this->trabajador)
            ->delete(route('trabajador.papeletas.cancelar', $papeleta));

        return $papeleta->fresh();
    }

    public static function estadosEnFaseRrhh(): array
    {
        return [
            'pendiente de RRHH' => [PendienteRrhh::class],
            'observada por RRHH' => [ObservadaPorRrhh::class],
        ];
    }

    #[DataProvider('estadosEnFaseRrhh')]
    public function test_puede_cancelar_esperando_a_rrhh_si_rrhh_esta_en_horario(string $estado): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 10:00'));

        $this->assertTrue($this->cancelar($estado)->estado->equals(Cancelada::class));
    }

    #[DataProvider('estadosEnFaseRrhh')]
    public function test_no_puede_cancelar_esperando_a_rrhh_si_rrhh_no_trabaja(string $estado): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 17:00'));

        $this->assertTrue($this->cancelar($estado)->estado->equals($estado));
    }

    public function test_puede_cancelar_con_el_jefe_aunque_rrhh_no_trabaje(): void
    {
        $this->travelTo(Carbon::parse('2026-09-26 11:00')); // sábado

        $this->assertTrue($this->cancelar(PendienteJefe::class)->estado->equals(Cancelada::class));
    }

    public function test_no_puede_cancelar_una_papeleta_autorizada_y_corriendo(): void
    {
        $this->travelTo(Carbon::parse('2026-09-21 10:00'));

        $this->assertTrue($this->cancelar(AutorizadaYCorriendo::class)->estado->equals(AutorizadaYCorriendo::class));
    }

    public function test_el_boton_cancelar_sigue_la_misma_regla_en_el_detalle(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, PendienteRrhh::class);

        $this->travelTo(Carbon::parse('2026-09-21 10:00'));
        $this->actingAs($this->trabajador)
            ->get(route('trabajador.papeletas.show', $papeleta))
            ->assertSee('Cancelar papeleta');

        $this->travelTo(Carbon::parse('2026-09-21 17:00'));
        $this->actingAs($this->trabajador)
            ->get(route('trabajador.papeletas.show', $papeleta))
            ->assertDontSee('Cancelar papeleta');
    }
}
