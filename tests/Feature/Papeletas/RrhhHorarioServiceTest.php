<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\RrhhHorarioService;
use App\Models\Configuracion;
use App\Models\Feriado;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * RRHH está "en horario" si: hay personal RRHH activo, el día es laborable,
 * no es feriado y la hora cae en el horario ordinario (07:45-16:15 sembrado,
 * minuto final incluido). Lunes de referencia: 2026-09-21.
 */
class RrhhHorarioServiceTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);

        $this->rrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
    }

    private function enHorario(string $momento): bool
    {
        return app(RrhhHorarioService::class)->estaEnHorario(Carbon::parse($momento));
    }

    public function test_en_horario_un_dia_laborable(): void
    {
        $this->assertTrue($this->enHorario('2026-09-21 10:00:00'));
    }

    public function test_incluye_el_minuto_final_y_excluye_el_siguiente(): void
    {
        $this->assertTrue($this->enHorario('2026-09-21 16:15:59'));
        $this->assertFalse($this->enHorario('2026-09-21 16:16:00'));
        $this->assertFalse($this->enHorario('2026-09-21 07:44:59'));
        $this->assertTrue($this->enHorario('2026-09-21 07:45:00'));
    }

    public function test_fin_de_semana_no_esta_en_horario(): void
    {
        $this->assertFalse($this->enHorario('2026-09-26 10:00:00'));
    }

    public function test_un_feriado_no_esta_en_horario(): void
    {
        Feriado::create(['fecha' => '2026-09-21', 'descripcion' => 'Feriado de prueba']);

        $this->assertFalse($this->enHorario('2026-09-21 10:00:00'));
        $this->assertTrue($this->enHorario('2026-09-22 10:00:00'));
    }

    public function test_sin_personal_rrhh_activo_nunca_esta_en_horario(): void
    {
        $this->rrhh->update(['activo' => false]);

        $this->assertFalse($this->enHorario('2026-09-21 10:00:00'));
    }

    public function test_basta_un_rrhh_activo_aunque_haya_otro_inactivo(): void
    {
        $this->rrhh->update(['activo' => false]);
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);

        $this->assertTrue($this->enHorario('2026-09-21 10:00:00'));
    }

    public function test_una_hora_sin_cero_inicial_en_configuraciones_no_rompe_la_comparacion(): void
    {
        Configuracion::where('clave', 'HORARIO_ORDINARIO_HORA_INICIO')->first()->update(['valor' => '8:00']);

        $this->assertTrue($this->enHorario('2026-09-21 08:30:00'));
        $this->assertFalse($this->enHorario('2026-09-21 07:50:00'));
    }
}
