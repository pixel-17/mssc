<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\MarcarRetornoAction;
use App\Services\AbrirJustificacion;
use App\Services\CalculadorDiasHabiles;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\EnJustificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El adjunto al crear la papeleta y la justificación son lo mismo: si el
 * trabajador ya adjuntó, el sustento nace `presentado` (solo falta el visto
 * bueno); si no, nace `pendiente` y se presenta dentro del plazo.
 */
class AdjuntoInicialComoJustificacionTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_con_adjunto_inicial_el_sustento_nace_presentado(): void
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba(), AutorizadaYCorriendo::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
            'adjunto_inicial_path' => 'papeletas/adjuntos-iniciales/certificado.pdf',
        ]);

        $desde = Carbon::parse('2026-09-22 10:00:00');
        $sustento = app(AbrirJustificacion::class)->para($papeleta, $desde);

        $this->assertSame('presentado', $sustento->estado);
        $this->assertSame('papeletas/adjuntos-iniciales/certificado.pdf', $sustento->archivo_path);
        $this->assertNotNull($sustento->presentado_at);

        // No basta con "no es null": el plazo viene del motivo
        // (plazo_justificacion_horas_habiles, hoy 48h para SALUD). Si ese
        // dato se pierde (p. ej. un seeder que lo deja en null), el plazo
        // cae a 0 y el sustento nace vencido sin que esta prueba lo note
        // a menos que compare contra el valor real esperado.
        $this->assertSame(48, $this->motivoDe('SALUD')->plazo_justificacion_horas_habiles);
        $esperado = app(CalculadorDiasHabiles::class)->agregarHorasHabiles($desde->copy(), 48);
        $this->assertTrue($sustento->fecha_limite->equalTo($esperado));
    }

    public function test_sin_adjunto_inicial_el_sustento_nace_pendiente(): void
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba(), AutorizadaYCorriendo::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
            'adjunto_inicial_path' => null,
        ]);

        $desde = Carbon::parse('2026-09-22 10:00:00');
        $sustento = app(AbrirJustificacion::class)->para($papeleta, $desde);

        $this->assertSame('pendiente', $sustento->estado);
        $this->assertNull($sustento->archivo_path);
        $this->assertNull($sustento->presentado_at);

        $esperado = app(CalculadorDiasHabiles::class)->agregarHorasHabiles($desde->copy(), 48);
        $this->assertTrue($sustento->fecha_limite->equalTo($esperado));
    }

    public function test_marcar_retorno_con_adjunto_inicial_deja_el_sustento_presentado_y_en_justificacion(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $papeleta = $this->papeletaDePrueba($trabajador, AutorizadaYCorriendo::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
            'adjunto_inicial_path' => 'papeletas/adjuntos-iniciales/certificado.pdf',
        ]);

        app(MarcarRetornoAction::class)->normal($papeleta, $trabajador, []);

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(EnJustificacion::class));
        $this->assertSame('presentado', $papeleta->sustentos()->first()->estado);
    }
}
