<?php

namespace Tests\Feature\Papeletas;

use App\Livewire\Papeletas\RrhhIndex;
use App\Models\Papeleta;
use App\Models\Retorno;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El abandono no marcado se decide contra fin_turno_at: un 728 de turno
 * Noche que sale a las 23:30 y vuelve a las 00:30 ya no queda como
 * abandono falso a medianoche.
 */
class AbandonoPorFinDeTurnoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function ir(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
    }

    private function papeletaNocheEnCurso(string $motivo = 'PARTICULAR'): Papeleta
    {
        return $this->papeletaDePrueba($this->usuarioDePrueba(), AutorizadaYCorriendo::class, [
            'motivo_id' => $this->motivoDe($motivo)->id,
            'dia_operativo' => '2026-09-21',
            'fin_turno_at' => '2026-09-22 06:00:00',
            'hora_salida_real' => '2026-09-21 23:30:00',
        ]);
    }

    private function abandonos(): void
    {
        $this->artisan('papeletas:procesar-abandono-no-marcado')->assertSuccessful();
    }

    public function test_noche_no_se_marca_abandono_a_medianoche_mientras_el_turno_sigue(): void
    {
        $papeleta = $this->papeletaNocheEnCurso();

        $this->ir('2026-09-22 00:30:00');
        $this->abandonos();

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
    }

    public function test_noche_se_marca_abandono_al_terminar_el_turno_sin_retorno(): void
    {
        $papeleta = $this->papeletaNocheEnCurso();

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $papeleta = $papeleta->fresh();
        // Particular descuenta: termina de una vez en Finalizada, sin plazo ni
        // visto bueno, y queda marcada como abandono.
        $this->assertTrue($papeleta->estado->equals(Finalizada::class));
        $this->assertSame('abandono_no_marcado', $papeleta->causa_finalizacion_sin_retorno);
        $this->assertTrue($papeleta->esAbandono());
        $this->assertFalse((bool) $papeleta->requiere_visto_bueno);
        $this->assertNull($papeleta->regularizacion_fecha_limite);
    }

    public function test_noche_con_retorno_registrado_no_es_abandono_al_terminar_el_turno(): void
    {
        $papeleta = $this->papeletaNocheEnCurso();

        Retorno::create([
            'papeleta_id' => $papeleta->id,
            'hora_servidor' => '2026-09-22 00:30:00',
            'marcado_manual' => false,
        ]);

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
    }

    public function test_abandono_de_comision_cierra_sin_descuento(): void
    {
        $papeleta = $this->papeletaNocheEnCurso('COMISION');

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(Cerrada::class));
        $this->assertTrue($papeleta->esAbandono());
    }

    public function test_abandono_de_salud_queda_en_justificacion_con_plazo(): void
    {
        $papeleta = $this->papeletaNocheEnCurso('SALUD');

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(EnJustificacion::class));
        $this->assertTrue($papeleta->esAbandono());

        $sustento = $papeleta->sustentos()->first();
        $this->assertNotNull($sustento);
        $this->assertSame('pendiente', $sustento->estado);
        $this->assertTrue($sustento->fecha_limite->isFuture(), 'puede justificar al día siguiente');
    }

    public function test_abandono_de_salud_sin_justificar_termina_finalizada_con_descuento(): void
    {
        $papeleta = $this->papeletaNocheEnCurso('SALUD');

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $this->ir('2026-09-30 12:00:00');
        $this->artisan('papeletas:procesar-vencimiento-sustentos')->assertSuccessful();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(Finalizada::class));
        $this->assertTrue($papeleta->esAbandono(), 'la causa de abandono se conserva');
        $this->assertSame($this->motivoDe('PARTICULAR')->id, $papeleta->motivo_id, 'pasa a Particular para el descuento');
    }

    public function test_salud_con_adjunto_al_crear_nace_presentada_y_va_a_revision_de_rrhh(): void
    {
        $papeleta = $this->papeletaNocheEnCurso('SALUD');
        $papeleta->update(['adjunto_inicial_path' => 'papeletas/adjuntos-iniciales/certificado.pdf']);

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $papeleta = $papeleta->fresh();
        $this->assertTrue($papeleta->estado->equals(EnJustificacion::class));

        $sustento = $papeleta->sustentos()->first();
        $this->assertSame('presentado', $sustento->estado, 'el adjunto de la creación cuenta como justificación');
        $this->assertSame('papeletas/adjuntos-iniciales/certificado.pdf', $sustento->archivo_path);
        $this->assertNotNull($sustento->presentado_at);

        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->assertViewHas('sustentosPorRevisar', fn ($lista) => $lista->total() === 1);
    }

    public function test_salud_sin_adjunto_al_crear_queda_pendiente_para_presentarla_despues(): void
    {
        $papeleta = $this->papeletaNocheEnCurso('SALUD');

        $this->ir('2026-09-22 06:01:00');
        $this->abandonos();

        $sustento = $papeleta->fresh()->sustentos()->first();
        $this->assertSame('pendiente', $sustento->estado);
        $this->assertNull($sustento->archivo_path);
    }
}
