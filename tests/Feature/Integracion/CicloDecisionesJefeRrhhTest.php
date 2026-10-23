<?php

namespace Tests\Feature\Integracion;

use App\Models\HistorialPapeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class CicloDecisionesJefeRrhhTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $rrhh;

    private User $otroRrhh;

    private User $admin;

    private User $jefe;

    private User $trabajador;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        $this->rrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->otroRrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->admin = $this->usuarioDePrueba(['regimen' => '276'], ['admin']);
        $this->jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $this->ajeno = $this->usuarioDePrueba(['regimen' => '728']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefe->id]);
        $this->jefe->update(['unidad_organica_id' => $unidad->id]);
        $this->trabajador->update(['unidad_organica_id' => $unidad->id]);

        $this->jefe = $this->jefe->fresh();
        $this->trabajador = $this->trabajador->fresh();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_rrhh_observa_el_jefe_reconoce_aprueba_y_rrhh_rechaza_con_historial_completo(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, PendienteRrhh::class);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.observar', $papeleta), ['comentario' => 'Falta precisar el destino.'])
            ->assertSessionHas('success');
        $this->assertTrue($papeleta->fresh()->estado->equals(ObservadaPorRrhh::class));

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.reconocer-observacion-rrhh', $papeleta), ['comentario' => 'Enterado, lo reviso.'])
            ->assertSessionHas('success');
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.aprobar', $papeleta))
            ->assertSessionDoesntHaveErrors();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.rechazar', $papeleta), ['comentario' => 'No procede por el motivo indicado.'])
            ->assertSessionHas('warning');
        $this->assertTrue($papeleta->fresh()->estado->equals(Rechazada::class));

        $this->assertGreaterThanOrEqual(
            4,
            HistorialPapeleta::where('papeleta_id', $papeleta->id)->count(),
            'Cada transición del ciclo debe quedar en el historial.'
        );
    }

    public function test_observar_y_rechazar_exigen_comentario_de_al_menos_5_caracteres(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, PendienteRrhh::class);

        foreach (['rrhh.papeletas.observar', 'rrhh.papeletas.rechazar'] as $ruta) {
            $this->actingAs($this->rrhh)
                ->post(route($ruta, $papeleta), [])
                ->assertSessionHasErrors('comentario');

            $this->actingAs($this->rrhh)
                ->post(route($ruta, $papeleta), ['comentario' => 'abc'])
                ->assertSessionHasErrors('comentario');
        }

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));
    }

    public function test_solo_rrhh_ajeno_a_la_papeleta_puede_decidir(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, PendienteRrhh::class);
        $datos = ['comentario' => 'Comentario válido de prueba.'];

        foreach (['rrhh.papeletas.observar', 'rrhh.papeletas.rechazar'] as $ruta) {
            foreach ([$this->trabajador, $this->jefe, $this->ajeno, $this->admin] as $usuario) {
                $this->actingAs($usuario)->post(route($ruta, $papeleta), $datos)->assertForbidden();
            }
        }

        $propia = $this->papeletaDePrueba($this->rrhh, PendienteRrhh::class);
        $this->actingAs($this->rrhh)->post(route('rrhh.papeletas.rechazar', $propia), $datos)->assertForbidden();

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));
        $this->assertTrue($propia->fresh()->estado->equals(PendienteRrhh::class));
    }

    public function test_rrhh_no_puede_decidir_una_papeleta_que_ya_no_esta_pendiente_de_rrhh(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.rechazar', $papeleta), ['comentario' => 'Comentario válido de prueba.'])
            ->assertForbidden();

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
    }

    public function test_reconocer_observacion_exige_ser_jefe_inmediato_y_estado_observada(): void
    {
        $observada = $this->papeletaDePrueba($this->trabajador, ObservadaPorRrhh::class);
        $pendiente = $this->papeletaDePrueba($this->trabajador, PendienteRrhh::class);
        $datos = ['comentario' => 'Comentario válido de prueba.'];

        $this->actingAs($this->ajeno)
            ->post(route('jefe.papeletas.reconocer-observacion-rrhh', $observada), $datos)
            ->assertForbidden();

        $this->actingAs($this->trabajador)
            ->post(route('jefe.papeletas.reconocer-observacion-rrhh', $observada), $datos)
            ->assertForbidden();

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.reconocer-observacion-rrhh', $pendiente), $datos)
            ->assertForbidden();

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.reconocer-observacion-rrhh', $observada), ['comentario' => 'x'])
            ->assertSessionHasErrors('comentario');

        $this->assertTrue($observada->fresh()->estado->equals(ObservadaPorRrhh::class));
    }

    public function test_las_rutas_de_decision_exigen_autenticacion(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, PendienteRrhh::class);

        foreach ([
            'rrhh.papeletas.observar',
            'rrhh.papeletas.rechazar',
            'rrhh.papeletas.posthoc-aprobar',
            'rrhh.papeletas.posthoc-observar',
            'jefe.papeletas.reconocer-observacion-rrhh',
            'jefe.papeletas.retorno-manual',
        ] as $ruta) {
            $this->post(route($ruta, $papeleta), ['comentario' => 'Comentario válido de prueba.'])
                ->assertRedirect(route('login'));
        }
    }

    public function test_posthoc_solo_lo_revisa_rrhh_ajeno_y_exige_que_la_papeleta_lo_requiera(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        foreach ([$this->trabajador, $this->jefe, $this->ajeno, $this->admin] as $usuario) {
            $this->actingAs($usuario)->post(route('rrhh.papeletas.posthoc-aprobar', $papeleta))->assertForbidden();
            $this->actingAs($usuario)
                ->post(route('rrhh.papeletas.posthoc-observar', $papeleta), ['comentario' => 'Comentario válido de prueba.'])
                ->assertForbidden();
        }

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.posthoc-aprobar', $papeleta))
            ->assertSessionHas('error');

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.posthoc-observar', $papeleta), ['comentario' => 'x'])
            ->assertSessionHasErrors('comentario');
    }

    public function test_posthoc_aprobado_y_observado_actualiza_la_revision_cuando_la_autorizacion_fue_fuera_de_horario(): void
    {
        $aprobable = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
            'resuelto_por_jefe_id' => $this->jefe->id,
        ]);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.posthoc-aprobar', $aprobable))
            ->assertSessionHas('success');

        $aprobable->refresh();
        $this->assertSame('aprobada', $aprobable->revision_posthoc_estado);
        $this->assertSame($this->rrhh->id, $aprobable->revision_posthoc_por_id);

        $observable = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
            'resuelto_por_jefe_id' => $this->jefe->id,
        ]);

        $this->actingAs($this->otroRrhh)
            ->post(route('rrhh.papeletas.posthoc-observar', $observable), ['comentario' => 'Falta documentar la salida.'])
            ->assertSessionHas('success');

        $observable->refresh();
        $this->assertSame('observada', $observable->revision_posthoc_estado);
        $this->assertSame(1, (int) $observable->contador_observaciones_posthoc);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.papeletas.posthoc-aprobar', $aprobable))
            ->assertSessionHas('error');
    }
}
