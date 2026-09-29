<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Actions\Papeleta\DecisorDisponibleService;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un usuario sin régimen 276/728 (users.regimen es nullable: p. ej. un
 * admin) no entra al flujo de papeletas: se rechaza con un mensaje claro
 * en vez de tratarlo como 276 por descarte y fallar en el INSERT.
 */
class RegimenRequeridoTest extends TestCase
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

    public function test_sin_regimen_no_puede_crear_papeleta_y_no_toca_la_bd(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => null]);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        try {
            app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
            $this->fail('Debió lanzar PapeletaException por falta de régimen.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('régimen', $e->getMessage());
        }

        $this->assertSame(0, Papeleta::count());
    }

    public function test_un_decisor_sin_regimen_nunca_esta_disponible(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => null]);

        $this->assertFalse(app(DecisorDisponibleService::class)->estaDisponible($jefe, Carbon::parse('2026-09-21 10:00:00')));
    }
}
