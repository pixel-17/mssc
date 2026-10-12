<?php

namespace Tests\Feature\Regresion;

use App\Livewire\Reportes\HorasAcumuladasIndex;
use App\Livewire\Reportes\TrabajadorHistorialIndex;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresiones de los hallazgos de la sesión de QA (octubre 2026).
 * Cada test describe el comportamiento correcto.
 */
class HallazgosQaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    /**
     * Con el driver `array` los objetos no se serializan y el fallo no se
     * ve: se fuerza `file`, igual que `database`/`redis` en producción.
     * `serializable_classes => false` convertía las colecciones Eloquent
     * cacheadas en objetos incompletos y el dashboard daba 500 en el
     * segundo render (dentro del TTL de 30 s).
     */
    public function test_el_dashboard_no_falla_en_el_segundo_render_con_cache_real(): void
    {
        config(['cache.default' => 'file']);
        Cache::flush();

        $trabajador = $this->usuarioDePrueba();
        $this->papeletaDePrueba($trabajador);

        foreach (['admin', 'rrhh'] as $rol) {
            $usuario = $this->usuarioDePrueba([], [$rol]);

            foreach (range(1, 3) as $visita) {
                $this->actingAs($usuario)->get(route('dashboard'))
                    ->assertOk("Dashboard de {$rol} falló en la visita {$visita}");
            }
        }

        Cache::flush();
    }

    private function assertXlsxValido($componente): void
    {
        $descarga = $componente->effects['download'] ?? null;

        $this->assertNotNull($descarga, 'La acción no devolvió una descarga');
        $this->assertSame('PK', substr(base64_decode($descarga['content']), 0, 2), 'El archivo no es un .xlsx (zip) válido');
    }

    /** maatwebsite/excel 4 exige `Concerns\Export` y `collection(): Enumerable`. */
    public function test_el_reporte_de_horas_acumuladas_se_descarga_como_xlsx(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $componente = Livewire::actingAs($rrhh)
            ->test(HorasAcumuladasIndex::class)
            ->call('exportar')
            ->assertFileDownloaded();

        $this->assertXlsxValido($componente);
    }

    public function test_el_historial_de_un_trabajador_se_descarga_como_xlsx(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $trabajador = $this->usuarioDePrueba();
        $this->papeletaDePrueba($trabajador);

        $componente = Livewire::actingAs($rrhh)
            ->test(TrabajadorHistorialIndex::class)
            ->set('trabajadorId', $trabajador->id)
            ->call('exportar')
            ->assertFileDownloaded();

        $this->assertXlsxValido($componente);
    }

    /** El mes viene del cliente: un valor mal formado se rechaza en vez de ir al nombre del archivo. */
    public function test_el_mes_del_reporte_se_valida_y_no_llega_al_nombre_del_archivo(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        foreach (['', 'abc-99', '2026-13'] as $mes) {
            Livewire::actingAs($rrhh)
                ->test(HorasAcumuladasIndex::class)
                ->set('mes', $mes)
                ->call('exportar')
                ->assertHasErrors('mes');
        }
    }

    /**
     * Un motivo sin plazo propio NO queda sin vencimiento: usa el global
     * SUSTENTO_HORAS_HABILES (48 por defecto). Por eso el campo es opcional.
     */
    public function test_un_motivo_sin_plazo_propio_usa_el_plazo_global(): void
    {
        $motivo = $this->motivoDe('SALUD');

        $motivo->update(['plazo_justificacion_horas_habiles' => null]);
        $this->assertSame(48, $motivo->fresh()->plazoJustificacionHorasHabiles());

        $motivo->update(['plazo_justificacion_horas_habiles' => 24]);
        $this->assertSame(24, $motivo->fresh()->plazoJustificacionHorasHabiles());
    }

    /** El layout cargaba `js/push.js` (inexistente): 404 en cada página. */
    public function test_el_layout_no_referencia_el_script_push_inexistente(): void
    {
        $this->actingAs($this->usuarioDePrueba())
            ->get('/turnos/calendario/mio')
            ->assertOk()
            ->assertDontSee('js/push.js', false);
    }

    /**
     * `migrate:fresh --seed` debe completarse y dejar turnos para los dos
     * jefes 728, o el trabajador de prueba no puede crear papeletas.
     */
    public function test_el_seed_completo_deja_turnos_a_los_jefes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-09 09:30:00', 'America/Lima'));

        $this->seed(DatabaseSeeder::class);

        foreach (['jefeinmediato@mssc.test', 'jefearea@mssc.test'] as $email) {
            $jefe = \App\Models\User::where('email', $email)->firstOrFail();

            $this->assertTrue(
                \App\Models\Turno::where('user_id', $jefe->id)->whereDate('fecha', '2026-10-09')->exists(),
                "{$email} no tiene turno el día sembrado",
            );
        }

        Carbon::setTestNow();
    }
}
