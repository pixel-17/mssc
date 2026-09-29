<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\RevisionPosthocAction;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Recorrido HTTP del ida y vuelta post-hoc: las vistas Blade renderizan,
 * el formulario del jefe (texto + adjunto opcional) responde y solo el
 * jefe que autorizó puede hacerlo.
 */
class PosthocRespuestaJefeHttpTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefe;

    private User $rrhh;

    private Papeleta $papeleta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        Notification::fake();
        Storage::fake('local');

        $this->jefe = $this->usuarioDePrueba();
        $trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);
        $this->rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $papeleta = $this->papeletaDePrueba($trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
            'resuelto_por_jefe_id' => $this->jefe->id,
        ]);

        $this->papeleta = app(RevisionPosthocAction::class)->observar($papeleta, $this->rrhh, 'Falta el sustento de la salida');
    }

    public function test_el_jefe_que_autorizo_ve_la_observacion_y_el_formulario(): void
    {
        $this->actingAs($this->jefe)
            ->get(route('jefe.papeletas.show', $this->papeleta))
            ->assertOk()
            ->assertSee('RRHH observó tu autorización')
            ->assertSee('Falta el sustento de la salida')
            ->assertSee('Enviar respuesta a RRHH');
    }

    public function test_el_jefe_responde_con_texto_y_adjunto_y_rrhh_lo_ve(): void
    {
        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.responder-posthoc', $this->papeleta), [
                'respuesta' => 'Fue una emergencia familiar',
                'archivo' => UploadedFile::fake()->create('sustento.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('jefe.papeletas.show', $this->papeleta));

        $papeleta = $this->papeleta->fresh();
        $this->assertSame('respondida', $papeleta->revision_posthoc_estado);
        $this->assertNotNull($papeleta->posthoc_adjunto_path);
        Storage::disk('local')->assertExists($papeleta->posthoc_adjunto_path);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.papeletas.show', $papeleta))
            ->assertOk()
            ->assertSee('El jefe respondió tu observación')
            ->assertSee('Fue una emergencia familiar');

        $this->actingAs($this->rrhh)
            ->get(route('papeletas.archivo', [$papeleta, 'respuesta-posthoc']))
            ->assertOk();
    }

    public function test_las_bandejas_reflejan_cada_estado_de_la_revision(): void
    {
        // observada: la lista el jefe que autorizó, RRHH aún no.
        $this->actingAs($this->jefe)
            ->get(route('jefe.papeletas.index'))
            ->assertOk()
            ->assertSee('RRHH observó tu autorización fuera de horario');

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.papeletas.index'))
            ->assertOk()
            ->assertDontSee('Jefe respondió');

        // respondida: pasa a la bandeja de RRHH con su etiqueta.
        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.responder-posthoc', $this->papeleta), ['respuesta' => 'Fue una emergencia']);

        $this->actingAs($this->rrhh)
            ->get(route('rrhh.papeletas.index'))
            ->assertOk()
            ->assertSee('Jefe respondió');

        $this->actingAs($this->jefe)
            ->get(route('jefe.papeletas.index'))
            ->assertOk()
            ->assertDontSee('RRHH observó tu autorización fuera de horario');
    }

    public function test_la_respuesta_sin_texto_se_rechaza_y_no_deja_archivos_sueltos(): void
    {
        $this->actingAs($this->jefe)
            ->from(route('jefe.papeletas.show', $this->papeleta))
            ->post(route('jefe.papeletas.responder-posthoc', $this->papeleta), [
                'respuesta' => '',
                'archivo' => UploadedFile::fake()->create('sustento.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('respuesta');

        $this->assertSame('observada', $this->papeleta->fresh()->revision_posthoc_estado);
        $this->assertSame([], Storage::disk('local')->allFiles('papeletas/posthoc'));
    }

    public function test_otro_jefe_recibe_403_al_intentar_responder(): void
    {
        $otro = $this->usuarioDePrueba();

        $this->actingAs($otro)
            ->post(route('jefe.papeletas.responder-posthoc', $this->papeleta), ['respuesta' => 'Yo respondo por él'])
            ->assertForbidden();

        $this->assertSame('observada', $this->papeleta->fresh()->revision_posthoc_estado);
    }
}
