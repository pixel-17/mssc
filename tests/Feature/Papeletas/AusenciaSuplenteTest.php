<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Models\AusenciaJefe;
use App\Models\HistorialPapeleta;
use App\Models\JefeSuplente;
use App\Models\UnidadOrganica;
use App\States\Papeleta\PendienteJefe;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Titular en ausencia temporal (AusenciaJefe): con suplente disponible
 * se fotografía a él; sin suplente, la papeleta se queda PENDIENTE_JEFE
 * sobre el titular hasta que venza — NUNCA cae al salto a RRHH / auto-
 * autorización que ya existe para "jefe fuera de horario" (opción A
 * confirmada: son ramas distintas).
 */
class AusenciaSuplenteTest extends TestCase
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

    private function armarEquipo(): array
    {
        $titular = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);

        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $titular->id]);

        $titular->update(['unidad_organica_id' => $oficina->id]);
        $trabajador->update(['unidad_organica_id' => $oficina->id]);

        return [$titular->fresh(), $trabajador->fresh(), $oficina];
    }

    public function test_con_suplente_disponible_se_fotografia_al_suplente(): void
    {
        [$titular, $trabajador, $oficina] = $this->armarEquipo();
        $suplente = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);

        AusenciaJefe::create([
            'jefe_id' => $titular->id,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(5)->toDateString(),
        ]);

        JefeSuplente::create([
            'unidad_organica_id' => $oficina->id,
            'turno' => 'MANANA',
            'jefe_suplente_id' => $suplente->id,
        ]);

        $this->turnoDePrueba($trabajador, ['turno' => 'MANANA']);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(PendienteJefe::class));
        $this->assertSame($suplente->id, $papeleta->jefe_inmediato_id);
    }

    /**
     * Titular 276 (para que su disponibilidad dependa del horario) fuera
     * de su ventana ordinaria: como RRHH comparte esa misma ventana
     * (RrhhHorarioService), sin la rama de ausencia esto normalmente
     * caería en auto-autorización (AutorizadaYCorriendo). Confirma que
     * la ausencia sin suplente NO sigue ese camino: se queda
     * PENDIENTE_JEFE fotografiando al titular ausente.
     */
    public function test_sin_suplente_queda_pendiente_sobre_el_titular_ausente_en_vez_de_autoautorizarse(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->ir('2026-09-21 22:00:00'); // lunes, fuera de 07:45-16:15 (jefe y RRHH, misma ventana)

        $titular = $this->usuarioDePrueba(['regimen' => '276'], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);
        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $titular->id]);
        $titular->update(['unidad_organica_id' => $oficina->id]);
        $trabajador->update(['unidad_organica_id' => $oficina->id]);

        AusenciaJefe::create([
            'jefe_id' => $titular->id,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(5)->toDateString(),
        ]);
        // Sin fila en jefes_suplentes para esta (unidad, turno): no hay a quién saltar.

        $this->turnoDePrueba($trabajador->fresh(), ['turno' => 'NOCHE']);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);
        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(PendienteJefe::class));
        $this->assertSame($titular->id, $papeleta->jefe_inmediato_id); // fotografiado el titular ausente
        $this->assertFalse($papeleta->autorizado_con_rrhh_fuera_horario);

        $historial = HistorialPapeleta::where('papeleta_id', $papeleta->id)->latest('id')->first();
        $this->assertSame('sistema', $historial->actor_tipo);
        $this->assertStringContainsString('ausencia temporal', $historial->justificacion);
    }

    private function ir(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
    }
}
