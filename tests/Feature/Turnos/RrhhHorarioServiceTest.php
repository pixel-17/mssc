<?php

namespace Tests\Feature\Turnos;

use App\Actions\Papeleta\RrhhHorarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class RrhhHorarioServiceTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->travelTo('2026-09-21 10:00:00'); // lunes, dentro del horario
    }

    public function test_esta_en_horario_si_hay_personal_de_rrhh_activo(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);

        $this->assertTrue(app(RrhhHorarioService::class)->estaEnHorarioAhora());
    }

    public function test_no_cuenta_a_las_cuentas_de_rrhh_desactivadas(): void
    {
        $this->usuarioDePrueba(['regimen' => '276', 'activo' => false], ['rrhh']);

        $this->assertFalse(app(RrhhHorarioService::class)->estaEnHorarioAhora());
    }

    public function test_sin_personal_de_rrhh_nunca_esta_en_horario(): void
    {
        $this->assertFalse(app(RrhhHorarioService::class)->estaEnHorarioAhora());
    }

    public function test_fuera_de_los_dias_laborables_no_esta_en_horario(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->travelTo('2026-09-20 10:00:00'); // domingo

        $this->assertFalse(app(RrhhHorarioService::class)->estaEnHorarioAhora());
    }
}
