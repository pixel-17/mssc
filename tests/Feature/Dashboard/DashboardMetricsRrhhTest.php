<?php

namespace Tests\Feature\Dashboard;

use App\Models\Papeleta;
use App\Models\Retorno;
use App\Services\DashboardMetricsService;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Rechazada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Los 4 widgets "por trabajador" agregados a
 * DashboardMetricsService::calcularRrhh(): quién está afuera ahora,
 * el top por cantidad de papeletas, el top de horas acumuladas del
 * mes (vía ReporteHorasAcumuladasService) y el seguimiento de
 * rechazadas/observadas.
 */
class DashboardMetricsRrhhTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function metricas(): array
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        return app(DashboardMetricsService::class)->paraRrhh($rrhh);
    }

    public function test_trabajadores_afuera_ordena_por_hora_de_salida_mas_antigua_primero(): void
    {
        $masReciente = $this->usuarioDePrueba(['name' => 'Ana', 'apellido' => 'Reciente']);
        $masAntiguo = $this->usuarioDePrueba(['name' => 'Beto', 'apellido' => 'Antiguo']);

        $this->papeletaDePrueba($masReciente, AutorizadaYCorriendo::class, [
            'hora_salida_real' => now()->subHour(),
        ]);
        $this->papeletaDePrueba($masAntiguo, AutorizadaYCorriendo::class, [
            'hora_salida_real' => now()->subHours(3),
        ]);

        $metricas = $this->metricas();

        $this->assertSame(2, $metricas['trabajadores_afuera_total']);
        $this->assertSame(
            'Beto Antiguo',
            $metricas['trabajadores_afuera']->first()->trabajador->nombre_completo,
        );
    }

    public function test_top_papeletas_por_trabajador_cuenta_y_ordena_de_mayor_a_menor(): void
    {
        $conDos = $this->usuarioDePrueba(['name' => 'Carla', 'apellido' => 'Conmas']);
        $conUna = $this->usuarioDePrueba(['name' => 'Dario', 'apellido' => 'Conmenos']);

        $this->papeletaDePrueba($conDos, Cerrada::class);
        $this->papeletaDePrueba($conDos, Cerrada::class);
        $this->papeletaDePrueba($conUna, Cerrada::class);

        $top = $this->metricas()['papeletas_por_trabajador'];

        $this->assertSame('Carla Conmas', $top->first()->etiqueta);
        $this->assertSame(2, (int) $top->first()->total);
    }

    public function test_seguimiento_cuenta_rechazadas_y_observadas_pero_no_papeletas_normales(): void
    {
        $rechazado = $this->usuarioDePrueba(['name' => 'Elsa', 'apellido' => 'Rechazada']);
        $observado = $this->usuarioDePrueba(['name' => 'Fabio', 'apellido' => 'Observado']);
        $sinIncidencias = $this->usuarioDePrueba(['name' => 'Gina', 'apellido' => 'Normal']);

        $this->papeletaDePrueba($rechazado, Rechazada::class);
        $this->papeletaDePrueba($observado, Cerrada::class, ['contador_observaciones_rrhh' => 1]);
        $this->papeletaDePrueba($sinIncidencias, Cerrada::class);

        $seguimiento = $this->metricas()['seguimiento_por_trabajador']->pluck('etiqueta');

        $this->assertTrue($seguimiento->contains('Elsa Rechazada'));
        $this->assertTrue($seguimiento->contains('Fabio Observado'));
        $this->assertFalse($seguimiento->contains('Gina Normal'));
    }

    public function test_horas_acumuladas_top_usa_el_resumen_del_mes_actual(): void
    {
        $trabajador = $this->usuarioDePrueba(['name' => 'Hugo', 'apellido' => 'Conhoras']);

        $salida = now()->startOfDay()->addHours(8);
        $papeleta = $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'hora_salida_real' => $salida,
        ]);

        Retorno::create([
            'papeleta_id' => $papeleta->id,
            'hora_servidor' => $salida->copy()->addHour(),
        ]);

        $top = $this->metricas()['horas_acumuladas_top'];

        $this->assertSame('Hugo Conhoras', $top->first()['trabajador']);
        $this->assertGreaterThan(0, $top->first()['minutos_totales']);
    }
}
