<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CorregirPapeletaRrhhAction;
use App\Exceptions\PapeletaException;
use App\Livewire\Papeletas\RrhhAbandonos;
use App\Livewire\Papeletas\RrhhIndex;
use App\Models\HistorialPapeleta;
use App\Models\Retorno;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Mejoras del rol RRHH: filtros de la bandeja, corrección de papeletas
 * cerradas, lista de abandonos y accesos a los reportes de RRHH.
 */
class RrhhMejorasTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    // ---- Bandeja: filtros -------------------------------------------------

    public function test_la_bandeja_filtra_por_regimen_y_por_motivo(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $maria = $this->usuarioDePrueba(['name' => 'María', 'apellido' => 'Quispe', 'regimen' => '276']);
        $jose = $this->usuarioDePrueba(['name' => 'José', 'apellido' => 'Fernández', 'regimen' => '728']);

        $this->papeletaDePrueba($maria, PendienteRrhh::class);
        $this->papeletaDePrueba($jose, PendienteRrhh::class, ['motivo_id' => $this->motivoDe('SALUD')->id]);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->set('regimen', '276')
            ->assertSee('María')
            ->assertDontSee('José')
            ->set('regimen', '')
            ->set('motivoId', $this->motivoDe('SALUD')->id)
            ->assertSee('José')
            ->assertDontSee('María')
            ->call('limpiarFiltros')
            ->assertSee('María')
            ->assertSee('José');
    }

    public function test_una_fecha_mal_formada_no_rompe_la_bandeja(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'María']), PendienteRrhh::class);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->set('desde', 'no-es-fecha')
            ->assertOk()
            ->assertSee('María');
    }

    public function test_la_bandeja_ordena_las_mas_antiguas_primero(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $reciente = $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'Reciente']), PendienteRrhh::class);
        $antigua = $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'Antigua']), PendienteRrhh::class);
        $antigua->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->assertViewHas('porDecidir', fn ($lista) => $lista->first()->is($antigua)
                && $lista->last()->is($reciente));
    }

    // ---- Corrección de papeletas cerradas --------------------------------

    private function papeletaCerradaConRetorno(string $regimen = '728')
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => $regimen]);
        $salida = now()->subHours(3);

        $papeleta = $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'hora_salida_real' => $salida,
        ]);

        Retorno::create([
            'papeleta_id' => $papeleta->id,
            'hora_servidor' => $salida->copy()->addHours(2),
            'dentro_de_radio' => true,
        ]);

        return $papeleta->refresh();
    }

    public function test_rrhh_corrige_hora_de_retorno_y_motivo_y_queda_en_el_historial(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $papeleta = $this->papeletaCerradaConRetorno();
        $nuevaHora = $papeleta->hora_salida_real->copy()->addMinutes(30);
        $salud = $this->motivoDe('SALUD');

        app(CorregirPapeletaRrhhAction::class)
            ->ejecutar($papeleta, $rrhh, $nuevaHora, $salud->id, 'La hora se marcó mal por falla del celular.');

        $papeleta->refresh();
        $this->assertTrue($papeleta->retorno->hora_servidor->equalTo($nuevaHora));
        $this->assertSame($salud->id, $papeleta->motivo_id);
        $this->assertTrue($papeleta->estado->equals(Cerrada::class));

        $evento = HistorialPapeleta::where('papeleta_id', $papeleta->id)->latest('id')->first();
        $this->assertSame($rrhh->id, $evento->actor_id);
        $this->assertStringContainsString('Corrección de RRHH', $evento->justificacion);
        $this->assertArrayHasKey('hora_retorno', $evento->metadata['correccion_rrhh']);
        $this->assertArrayHasKey('motivo_id', $evento->metadata['correccion_rrhh']);
    }

    public function test_la_correccion_exige_justificacion_y_algo_que_corregir(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $papeleta = $this->papeletaCerradaConRetorno();
        $accion = app(CorregirPapeletaRrhhAction::class);

        try {
            $accion->ejecutar($papeleta, $rrhh, now()->subHour(), null, 'corto');
            $this->fail('Debió exigir justificación.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('justificación', $e->getMessage());
        }

        $this->expectException(PapeletaException::class);
        $accion->ejecutar($papeleta, $rrhh, null, null, 'Justificación suficientemente larga.');
    }

    public function test_no_se_corrige_una_papeleta_que_no_esta_cerrada_ni_con_retorno_antes_de_la_salida(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $accion = app(CorregirPapeletaRrhhAction::class);

        $pendiente = $this->papeletaDePrueba($this->usuarioDePrueba(), PendienteRrhh::class);

        try {
            $accion->ejecutar($pendiente, $rrhh, null, $this->motivoDe('SALUD')->id, 'Justificación suficientemente larga.');
            $this->fail('Una papeleta pendiente no se corrige.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('cerradas', $e->getMessage());
        }

        $cerrada = $this->papeletaCerradaConRetorno();

        $this->expectException(PapeletaException::class);
        $accion->ejecutar($cerrada, $rrhh, $cerrada->hora_salida_real->copy()->subMinute(), null, 'Justificación suficientemente larga.');
    }

    public function test_solo_rrhh_puede_corregir_por_http(): void
    {
        $papeleta = $this->papeletaCerradaConRetorno();
        $trabajador = $this->usuarioDePrueba();
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $this->actingAs($trabajador)
            ->post(route('rrhh.papeletas.corregir', $papeleta), ['motivo_id' => $this->motivoDe('SALUD')->id, 'comentario' => 'Justificación suficientemente larga.'])
            ->assertForbidden();

        $this->actingAs($rrhh)
            ->post(route('rrhh.papeletas.corregir', $papeleta), ['motivo_id' => $this->motivoDe('SALUD')->id, 'comentario' => 'Justificación suficientemente larga.'])
            ->assertRedirect();

        $this->assertSame($this->motivoDe('SALUD')->id, $papeleta->refresh()->motivo_id);
    }

    // ---- Abandonos y accesos ---------------------------------------------

    public function test_la_lista_de_abandonos_muestra_quien_lo_marco_o_sistema(): void
    {
        $rrhh = $this->usuarioDePrueba(['name' => 'Rosa', 'apellido' => 'Decisora'], ['rrhh']);
        $trabajador = $this->usuarioDePrueba(['name' => 'Pedro', 'apellido' => 'Ausente']);

        $papeleta = $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'causa_finalizacion_sin_retorno' => 'abandono_no_marcado',
        ]);

        HistorialPapeleta::create([
            'papeleta_id' => $papeleta->id,
            'actor_id' => $rrhh->id,
            'actor_tipo' => 'rrhh',
            'estado_anterior' => 'EnJustificacion',
            'estado_nuevo' => 'Cerrada',
            'justificacion' => 'No volvió y no hubo sustento.',
        ]);

        Livewire::actingAs($rrhh)
            ->test(RrhhAbandonos::class)
            ->assertSee('Pedro')
            ->assertSee('Rosa')
            ->assertSee('No volvió y no hubo sustento.');
    }

    public function test_una_cerrada_normal_no_es_abandono_ni_aparece_en_la_lista(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $normal = $this->usuarioDePrueba(['name' => 'Lucia', 'apellido' => 'Cumplida']);
        $ausente = $this->usuarioDePrueba(['name' => 'Pedro', 'apellido' => 'Ausente']);

        $cerradaNormal = $this->papeletaDePrueba($normal, Cerrada::class);
        $cerradaAbandono = $this->papeletaDePrueba($ausente, Cerrada::class, [
            'causa_finalizacion_sin_retorno' => 'abandono_no_marcado',
        ]);

        $this->assertFalse($cerradaNormal->esAbandono());
        $this->assertTrue($cerradaAbandono->esAbandono());

        Livewire::actingAs($rrhh)
            ->test(RrhhAbandonos::class)
            ->assertSee('Ausente')
            ->assertDontSee('Cumplida');

        // La etiqueta del estado las distingue a simple vista.
        $this->assertStringContainsString('Cerrada · abandono justificado', view('components.estado-papeleta', ['estado' => $cerradaAbandono->estado, 'abandono' => true])->render());
        $this->assertStringNotContainsString('Abandono', view('components.estado-papeleta', ['estado' => $cerradaNormal->estado, 'abandono' => false])->render());
    }

    public function test_los_reportes_de_rrhh_no_los_ve_un_trabajador(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $trabajador = $this->usuarioDePrueba();

        foreach (['reportes.resumen-papeletas', 'reportes.decisiones-rrhh'] as $ruta) {
            $this->actingAs($rrhh)->get(route($ruta))->assertOk();
            $this->actingAs($trabajador)->get(route($ruta))->assertForbidden();
        }

        $this->actingAs($trabajador)->get(route('rrhh.abandonos.index'))->assertForbidden();
    }
}
