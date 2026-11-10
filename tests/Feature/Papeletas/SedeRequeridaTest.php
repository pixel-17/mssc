<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un trabajador sin sede asignada (users.sede_id es nullable: al crear un
 * trabajador el formulario permite dejarla vacía, ver UsuarioForm::rules())
 * no entra al flujo de papeletas: se rechaza con un mensaje claro en vez de
 * fallar en el INSERT, ya que papeletas.sede_id es NOT NULL (fotografía
 * inmutable). Mismo criterio que RegimenRequeridoTest.
 */
class SedeRequeridaTest extends TestCase
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

    public function test_sin_sede_no_puede_crear_papeleta_y_no_toca_la_bd(): void
    {
        $trabajador = $this->usuarioDePrueba(['sede_id' => null]);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        try {
            app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
            $this->fail('Debió lanzar PapeletaException por falta de sede.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('sede', $e->getMessage());
        }

        $this->assertSame(0, Papeleta::count());
    }
}