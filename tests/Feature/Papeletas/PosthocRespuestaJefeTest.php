<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\ResponderPosthocAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * La observación post-hoc de RRHH ya no es un final mudo: vuelve al MISMO
 * jefe que autorizó, que responde por escrito (adjunto opcional) y la
 * revisión pasa a 'respondida' para que RRHH decida otra vez. Mismo tope
 * que TOPE_OBSERVACIONES_RRHH; al alcanzarlo queda 'observada_firme'.
 */
class PosthocRespuestaJefeTest extends TestCase
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

    private function posthoc(array $atributos = []): Papeleta
    {
        return $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
            'resuelto_por_jefe_id' => $this->jefe->id,
            ...$atributos,
        ]);
    }

    public function test_observar_deja_la_revision_esperando_al_jefe_y_lo_notifica(): void
    {
        $papeleta = $this->posthoc();

        $papeleta = app(RevisionPosthocAction::class)->observar($papeleta, $this->rrhh, 'Falta el sustento de la salida');

        $this->assertSame('observada', $papeleta->revision_posthoc_estado);
        $this->assertSame(1, $papeleta->contador_observaciones_posthoc);
        $this->assertSame('Falta el sustento de la salida', $papeleta->posthoc_observacion);
        $this->assertTrue($papeleta->estado->equals(AutorizadaYCorriendo::class), 'el estado de la papeleta no cambia');

        Notification::assertSentTo($this->jefe, \App\Notifications\PapeletaNotification::class);
    }

    public function test_el_jefe_que_autorizo_responde_y_la_revision_vuelve_a_rrhh(): void
    {
        $papeleta = app(RevisionPosthocAction::class)->observar($this->posthoc(), $this->rrhh, 'Falta el sustento');

        $papeleta = app(ResponderPosthocAction::class)->ejecutar($papeleta, $this->jefe, 'Era una emergencia', 'papeletas/posthoc/x.pdf');

        $this->assertSame('respondida', $papeleta->revision_posthoc_estado);
        $this->assertSame('Era una emergencia', $papeleta->posthoc_respuesta);
        $this->assertSame('papeletas/posthoc/x.pdf', $papeleta->posthoc_adjunto_path);
        $this->assertNotNull($papeleta->posthoc_respondida_at);
    }

    public function test_rrhh_puede_aprobar_u_observar_de_nuevo_una_revision_respondida(): void
    {
        $observar = app(RevisionPosthocAction::class);
        $papeleta = $observar->observar($this->posthoc(), $this->rrhh, 'Falta el sustento');
        $papeleta = app(ResponderPosthocAction::class)->ejecutar($papeleta, $this->jefe, 'Era una emergencia');

        $otraVuelta = $observar->observar($papeleta, $this->rrhh, 'No basta con eso');
        $this->assertSame('observada', $otraVuelta->revision_posthoc_estado);
        $this->assertSame(2, $otraVuelta->contador_observaciones_posthoc);

        $respondida = app(ResponderPosthocAction::class)->ejecutar($otraVuelta, $this->jefe, 'Adjunto el parte médico');
        $aprobada = $observar->aprobar($respondida, $this->rrhh);

        $this->assertSame('aprobada', $aprobada->revision_posthoc_estado);
    }

    public function test_al_alcanzar_el_tope_la_observacion_queda_firme_y_ya_no_admite_respuesta(): void
    {
        $observar = app(RevisionPosthocAction::class);
        $responder = app(ResponderPosthocAction::class);

        $papeleta = $this->posthoc();

        // TOPE_OBSERVACIONES_RRHH = 3: las dos primeras esperan al jefe...
        foreach ([1, 2] as $vuelta) {
            $papeleta = $observar->observar($papeleta, $this->rrhh, "Observación {$vuelta}");
            $this->assertSame('observada', $papeleta->revision_posthoc_estado);
            $papeleta = $responder->ejecutar($papeleta, $this->jefe, "Respuesta {$vuelta}");
        }

        // ...la tercera alcanza el tope: reparo definitivo.
        $papeleta = $observar->observar($papeleta, $this->rrhh, 'Observación 3');

        $this->assertSame('observada_firme', $papeleta->revision_posthoc_estado);
        $this->assertSame(3, $papeleta->contador_observaciones_posthoc);

        $this->expectException(PapeletaException::class);
        $responder->ejecutar($papeleta, $this->jefe, 'Ya no debería poder responder');
    }

    public function test_sin_jefe_que_autorizara_la_observacion_queda_firme_de_una_vez(): void
    {
        $papeleta = $this->posthoc(['resuelto_por_jefe_id' => null]);

        $papeleta = app(RevisionPosthocAction::class)->observar($papeleta, $this->rrhh, 'Autorizada por el sistema, sin sustento');

        $this->assertSame('observada_firme', $papeleta->revision_posthoc_estado);
    }

    public function test_otro_jefe_no_puede_responder_solo_el_que_autorizo(): void
    {
        $papeleta = app(RevisionPosthocAction::class)->observar($this->posthoc(), $this->rrhh, 'Falta el sustento');
        $otroJefe = $this->usuarioDePrueba();

        $this->assertTrue($papeleta->puedeResponderPosthoc($this->jefe));
        $this->assertFalse($papeleta->puedeResponderPosthoc($otroJefe));

        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('Solo el jefe que autorizó esta papeleta puede responder la observación de RRHH.');

        app(ResponderPosthocAction::class)->ejecutar($papeleta, $otroJefe, 'Yo respondo por él');
    }

    public function test_no_se_puede_responder_si_la_revision_no_esta_observada(): void
    {
        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('Esta papeleta no tiene una observación post-hoc pendiente de respuesta.');

        app(ResponderPosthocAction::class)->ejecutar($this->posthoc(), $this->jefe, 'Nadie observó nada');
    }

    public function test_la_respuesta_escrita_es_obligatoria(): void
    {
        $papeleta = app(RevisionPosthocAction::class)->observar($this->posthoc(), $this->rrhh, 'Falta el sustento');

        $this->expectException(PapeletaException::class);

        app(ResponderPosthocAction::class)->ejecutar($papeleta, $this->jefe, '   ');
    }

    public function test_la_respuesta_notifica_a_rrhh(): void
    {
        $papeleta = app(RevisionPosthocAction::class)->observar($this->posthoc(), $this->rrhh, 'Falta el sustento');

        app(ResponderPosthocAction::class)->ejecutar($papeleta, $this->jefe, 'Era una emergencia');

        Notification::assertSentTo($this->rrhh, \App\Notifications\PapeletaNotification::class);
    }
}
