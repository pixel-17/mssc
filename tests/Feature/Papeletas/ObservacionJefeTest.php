<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\AprobarJefeAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\Cancelada;
use App\States\Papeleta\ObservadaPorJefe;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Rechazada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Observación del jefe: el trabajador SIEMPRE responde por escrito y, si
 * el jefe lo exigió al observar, además adjunta un archivo. Al responder
 * la papeleta vuelve a PENDIENTE_JEFE. Mientras espera, el jefe solo puede
 * rechazarla. RRHH ve el adjunto y la respuesta.
 */
class ObservacionJefeTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefe;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        Storage::fake('local');

        $this->jefe = $this->usuarioDePrueba();
        $this->trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);
    }

    private function observada(bool $requiereAdjunto): Papeleta
    {
        return $this->papeletaDePrueba($this->trabajador, ObservadaPorJefe::class, [
            'observacion_requiere_adjunto' => $requiereAdjunto,
            'contador_observaciones_jefe' => 1,
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function responder(Papeleta $papeleta, array $datos, ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->trabajador)->post(
            route('trabajador.papeletas.subsanar', $papeleta),
            $datos,
        );
    }

    private function archivo(): UploadedFile
    {
        return UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf');
    }

    public function test_el_jefe_observa_exigiendo_un_adjunto(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador);

        $this->actingAs($this->jefe)->post(route('jefe.papeletas.observar', $papeleta), [
            'comentario' => 'Falta el sustento del trámite.',
            'requiere_adjunto' => '1',
        ])->assertSessionHas('success');

        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(ObservadaPorJefe::class));
        $this->assertTrue($papeleta->observacion_requiere_adjunto);
    }

    public function test_el_jefe_observa_sin_exigir_adjunto(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador);

        $this->actingAs($this->jefe)->post(route('jefe.papeletas.observar', $papeleta), [
            'comentario' => 'Explícame por qué la salida es tan larga.',
        ])->assertSessionHas('success');

        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(ObservadaPorJefe::class));
        $this->assertFalse($papeleta->observacion_requiere_adjunto);
    }

    public function test_sin_adjunto_exigido_el_trabajador_responde_solo_con_texto_y_vuelve_al_jefe(): void
    {
        $papeleta = $this->observada(false);
        $papeleta->update(['escalado_jefe_area_at' => now()->subHour(), 'jefe_resuelto_at' => now()->subHour()]);

        $this->responder($papeleta, ['respuesta' => 'Fue por un trámite en la UGEL.'])
            ->assertRedirect(route('trabajador.papeletas.show', $papeleta));

        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(PendienteJefe::class));
        $this->assertSame('Fue por un trámite en la UGEL.', $papeleta->observacion_respuesta);
        $this->assertNull($papeleta->observacion_adjunto_path);
        $this->assertNotNull($papeleta->observacion_subsanada_at);
        $this->assertNotNull($papeleta->reloj_jefe_at);
        $this->assertNull($papeleta->escalado_jefe_area_at);
        $this->assertNull($papeleta->jefe_resuelto_at);

        $evento = $papeleta->historial()->latest('id')->first();
        $this->assertSame('trabajador', $evento->actor_tipo);
        $this->assertSame('Fue por un trámite en la UGEL.', $evento->justificacion);
    }

    public function test_con_adjunto_exigido_el_trabajador_responde_con_texto_y_archivo(): void
    {
        $papeleta = $this->observada(true);

        $this->responder($papeleta, ['respuesta' => 'Adjunto la constancia.', 'archivo' => $this->archivo()])
            ->assertRedirect(route('trabajador.papeletas.show', $papeleta));

        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(PendienteJefe::class));
        Storage::disk('local')->assertExists($papeleta->observacion_adjunto_path);
    }

    public function test_una_nueva_observacion_no_arrastra_el_adjunto_de_la_ronda_anterior(): void
    {
        $papeleta = $this->observada(true);

        $this->responder($papeleta, ['respuesta' => 'Adjunto la constancia.', 'archivo' => $this->archivo()]);

        $adjuntoPrimeraRonda = $papeleta->fresh()->observacion_adjunto_path;
        $this->assertNotNull($adjuntoPrimeraRonda);

        // Segunda ronda: el jefe observa otra vez, ahora sin exigir adjunto.
        $this->actingAs($this->jefe)->post(route('jefe.papeletas.observar', $papeleta), [
            'comentario' => 'Aclárame la hora de retorno.',
        ])->assertSessionHas('success');

        $this->assertNull($papeleta->fresh()->observacion_adjunto_path, 'la ronda nueva empieza sin adjunto');
        Storage::disk('local')->assertExists($adjuntoPrimeraRonda);

        $this->responder($papeleta, ['respuesta' => 'Regreso a las 3 pm.']);

        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(PendienteJefe::class));
        $this->assertNull($papeleta->observacion_adjunto_path, 'responder sin archivo no debe heredar el anterior');
    }

    public function test_con_adjunto_exigido_no_se_acepta_una_respuesta_sin_archivo(): void
    {
        $papeleta = $this->observada(true);

        $this->responder($papeleta, ['respuesta' => 'Ya te lo expliqué de palabra.'])
            ->assertSessionHasErrors('archivo');

        $this->assertTrue($papeleta->fresh()->estado->equals(ObservadaPorJefe::class));
    }

    public function test_la_respuesta_escrita_es_obligatoria(): void
    {
        $papeleta = $this->observada(true);

        $this->responder($papeleta, ['archivo' => $this->archivo()])->assertSessionHasErrors('respuesta');
        $this->responder($papeleta, ['respuesta' => 'no', 'archivo' => $this->archivo()])->assertSessionHasErrors('respuesta');

        $this->assertTrue($papeleta->fresh()->estado->equals(ObservadaPorJefe::class));
        $this->assertSame([], Storage::disk('local')->allFiles('papeletas/subsanaciones'));
    }

    public function test_si_la_papeleta_ya_no_esta_observada_no_queda_archivo_suelto(): void
    {
        $papeleta = $this->observada(true);
        DB::table('papeletas')->where('id', $papeleta->id)->update(['estado' => 'pendiente_jefe']);

        $this->responder($papeleta, ['respuesta' => 'Respuesta tardía.', 'archivo' => $this->archivo()])
            ->assertSessionHas('error');

        $this->assertSame([], Storage::disk('local')->allFiles('papeletas/subsanaciones'));
    }

    public function test_otro_trabajador_no_puede_responder_una_papeleta_ajena(): void
    {
        $papeleta = $this->observada(false);
        $otro = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);

        $this->responder($papeleta, ['respuesta' => 'Yo respondo por él.'], $otro)->assertForbidden();

        $this->assertTrue($papeleta->fresh()->estado->equals(ObservadaPorJefe::class));
    }

    public function test_el_trabajador_puede_cancelar_una_papeleta_observada(): void
    {
        $papeleta = $this->observada(false);

        $this->actingAs($this->trabajador)
            ->delete(route('trabajador.papeletas.cancelar', $papeleta))
            ->assertRedirect(route('trabajador.papeletas.index'));

        $this->assertTrue($papeleta->fresh()->estado->equals(Cancelada::class));
    }

    #[DataProvider('exigenAdjunto')]
    public function test_el_jefe_no_puede_aprobar_mientras_espera_la_respuesta(bool $requiereAdjunto): void
    {
        $papeleta = $this->observada($requiereAdjunto);

        $this->assertThrows(
            fn () => app(AprobarJefeAction::class)->ejecutar($papeleta, $this->jefe),
            PapeletaException::class,
        );

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.aprobar', $papeleta))
            ->assertSessionHas('error');

        $this->assertTrue($papeleta->fresh()->estado->equals(ObservadaPorJefe::class));
    }

    /** @return array<string, array{bool}> */
    public static function exigenAdjunto(): array
    {
        return ['sin adjunto exigido' => [false], 'con adjunto exigido' => [true]];
    }

    public function test_el_jefe_puede_rechazar_una_observada_sin_esperar_la_respuesta(): void
    {
        $papeleta = $this->observada(false);

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.rechazar', $papeleta), ['comentario' => 'No corresponde a la jornada.'])
            ->assertSessionHas('success');

        $this->assertTrue($papeleta->fresh()->estado->equals(Rechazada::class));
    }

    public function test_tras_la_respuesta_el_jefe_ya_puede_aprobar(): void
    {
        $papeleta = $this->observada(false);
        $this->responder($papeleta, ['respuesta' => 'Fue por un trámite en la UGEL.']);

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.aprobar', $papeleta))
            ->assertSessionHas('success');

        $this->assertFalse($papeleta->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_rrhh_puede_abrir_el_adjunto_de_la_respuesta(): void
    {
        $papeleta = $this->observada(true);
        $this->responder($papeleta, ['respuesta' => 'Adjunto la constancia.', 'archivo' => $this->archivo()]);

        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $this->actingAs($rrhh)
            ->get(route('papeletas.archivo', ['papeleta' => $papeleta, 'tipo' => 'justificacion-observacion']))
            ->assertOk();
    }

    public function test_un_trabajador_ajeno_no_puede_abrir_el_adjunto(): void
    {
        $papeleta = $this->observada(true);
        $this->responder($papeleta, ['respuesta' => 'Adjunto la constancia.', 'archivo' => $this->archivo()]);

        $ajeno = $this->usuarioDePrueba();

        $this->actingAs($ajeno)
            ->get(route('papeletas.archivo', ['papeleta' => $papeleta, 'tipo' => 'justificacion-observacion']))
            ->assertForbidden();
    }
}
