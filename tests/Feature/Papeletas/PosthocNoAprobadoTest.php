<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\ResponderPosthocAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\Finalizada;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Post-hoc sin decisión a favor no queda en el aire: si RRHH NO aprueba (o
 * el reparo queda definitivo) la papeleta pasa a Particular, con descuento.
 * Lo que ya era Particular termina Particular.
 */
class PosthocNoAprobadoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefe;

    private User $trabajador;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        Notification::fake();

        $this->jefe = $this->usuarioDePrueba();
        $this->trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);
        $this->rrhh = $this->usuarioDePrueba([], ['rrhh']);
    }

    private function posthoc(string $estado, string $motivo = 'COMISION', array $atributos = []): Papeleta
    {
        return $this->papeletaDePrueba($this->trabajador, $estado, [
            'motivo_id' => $this->motivoDe($motivo)->id,
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
            'resuelto_por_jefe_id' => $this->jefe->id,
            ...$atributos,
        ]);
    }

    public function test_no_aprobar_una_papeleta_cerrada_la_pasa_a_particular_con_descuento(): void
    {
        $papeleta = app(RevisionPosthocAction::class)
            ->noAprobar($this->posthoc(Cerrada::class), $this->rrhh, 'La salida no tenía sustento.');

        $this->assertSame('no_aprobada', $papeleta->revision_posthoc_estado);
        $this->assertTrue($papeleta->estado->equals(Finalizada::class));
        $this->assertSame($this->motivoDe('PARTICULAR')->id, $papeleta->motivo_id);
        $this->assertSame($this->motivoDe('COMISION')->id, $papeleta->motivo_original_id);
        $this->assertTrue($papeleta->motivo->suma_descuento);

        Notification::assertSentTo($this->trabajador, \App\Notifications\PapeletaNotification::class);
    }

    public function test_si_el_trabajador_sigue_fuera_solo_cambia_el_motivo_y_al_volver_se_finaliza(): void
    {
        $papeleta = app(RevisionPosthocAction::class)
            ->noAprobar($this->posthoc(AutorizadaYCorriendo::class), $this->rrhh, 'No se justifica la salida.');

        $this->assertTrue($papeleta->estado->equals(AutorizadaYCorriendo::class));
        $this->assertSame($this->motivoDe('PARTICULAR')->id, $papeleta->motivo_id);
        $this->assertSame('descuenta', $papeleta->motivo->consecuenciaAlTerminar());
    }

    public function test_lo_que_ya_era_particular_termina_particular_sin_cambiar_nada_mas(): void
    {
        $papeleta = app(RevisionPosthocAction::class)
            ->noAprobar($this->posthoc(Finalizada::class, 'PARTICULAR'), $this->rrhh, 'No se aprueba la revisión.');

        $this->assertSame('no_aprobada', $papeleta->revision_posthoc_estado);
        $this->assertTrue($papeleta->estado->equals(Finalizada::class));
        $this->assertSame($this->motivoDe('PARTICULAR')->id, $papeleta->motivo_id);
        $this->assertNull($papeleta->motivo_original_id);
    }

    public function test_el_reparo_definitivo_tambien_pasa_a_particular(): void
    {
        $observar = app(RevisionPosthocAction::class);
        $responder = app(ResponderPosthocAction::class);

        $papeleta = $this->posthoc(Cerrada::class);

        foreach ([1, 2] as $vuelta) {
            $papeleta = $observar->observar($papeleta, $this->rrhh, "Observación {$vuelta}");
            $papeleta = $responder->ejecutar($papeleta, $this->jefe, "Respuesta {$vuelta}");
        }

        $papeleta = $observar->observar($papeleta, $this->rrhh, 'Tercera observación');

        $this->assertSame('observada_firme', $papeleta->revision_posthoc_estado);
        $this->assertTrue($papeleta->estado->equals(Finalizada::class));
        $this->assertSame($this->motivoDe('PARTICULAR')->id, $papeleta->motivo_id);
    }

    public function test_rrhh_puede_no_aprobar_desde_la_pantalla(): void
    {
        $papeleta = $this->posthoc(Cerrada::class);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.posthoc-no-aprobar', $papeleta), ['comentario' => 'No se justifica la salida.'])
            ->assertSessionHas('success');

        $this->assertTrue($papeleta->fresh()->estado->equals(Finalizada::class));
    }

    public function test_aprobar_la_revision_no_cambia_la_papeleta(): void
    {
        $papeleta = app(RevisionPosthocAction::class)->aprobar($this->posthoc(Cerrada::class), $this->rrhh);

        $this->assertSame('aprobada', $papeleta->revision_posthoc_estado);
        $this->assertTrue($papeleta->estado->equals(Cerrada::class));
        $this->assertSame($this->motivoDe('COMISION')->id, $papeleta->motivo_id);
    }
}
