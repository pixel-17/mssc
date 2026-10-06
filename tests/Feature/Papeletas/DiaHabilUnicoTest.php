<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Actions\Papeleta\DecisorDisponibleService;
use App\Exceptions\PapeletaException;
use App\Models\Configuracion;
use App\Services\CalculadorDiasHabiles;
use App\Services\HorarioOrdinarioService;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * "Día hábil" tiene UNA sola definición (CalculadorDiasHabiles): día
 * laborable configurado. La ventana de 276, la disponibilidad
 * de decisores 276 y los plazos en horas hábiles deben coincidir siempre.
 * Fechas de referencia: lunes 2026-09-21, sábado 2026-09-26.
 */
class DiaHabilUnicoTest extends TestCase
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

    private function dentroDeVentana(string $momento): bool
    {
        return app(HorarioOrdinarioService::class)->estaDentroDeVentana(Carbon::parse($momento));
    }

    private function cargarDiasLaborables(string $dias): void
    {
        Configuracion::where('clave', 'HORARIO_ORDINARIO_DIAS_LABORABLES')->firstOrFail()->update(['valor' => $dias]);
    }

    public function test_cambiar_los_dias_laborables_mueve_ventana_y_plazos_juntos(): void
    {
        $sabado = Carbon::parse('2026-09-26 10:00:00');

        // Por defecto (lunes a viernes): sábado no es hábil en ningún lado.
        $this->assertFalse($this->dentroDeVentana('2026-09-26 10:00:00'));
        $this->assertFalse(app(CalculadorDiasHabiles::class)->esHabil($sabado));

        $this->cargarDiasLaborables('1,2,3,4,5,6');

        $this->assertTrue($this->dentroDeVentana('2026-09-26 10:00:00'));
        $this->assertTrue(app(CalculadorDiasHabiles::class)->esHabil($sabado));
    }

    public function test_las_horas_habiles_siguen_los_dias_laborables_configurados(): void
    {
        $calculador = app(CalculadorDiasHabiles::class);
        $viernes = Carbon::parse('2026-09-25 12:00:00');

        // Lunes a viernes: desde el viernes 12:00, las 24 h hábiles saltan
        // sábado y domingo y terminan el lunes 12:00.
        $this->assertSame('2026-09-28 12:00:00', $calculador->agregarHorasHabiles($viernes, 24)->format('Y-m-d H:i:s'));

        // Con sábado laborable, el sábado sí cuenta.
        $this->cargarDiasLaborables('1,2,3,4,5,6');

        $this->assertSame('2026-09-26 12:00:00', $calculador->agregarHorasHabiles($viernes, 24)->format('Y-m-d H:i:s'));
    }
}
