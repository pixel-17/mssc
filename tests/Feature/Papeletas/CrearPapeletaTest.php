<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Models\HistorialPapeleta;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión del error "Ya tienes una papeleta activa...": un trabajador
 * puede tener varias papeletas al mismo tiempo y Emergencia ya no existe.
 *
 * Horario ordinario sembrado por ConfiguracionSeeder: 07:45-16:15,
 * lunes a viernes. Lunes de referencia: 2026-09-21.
 */
class CrearPapeletaTest extends TestCase
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

    private function ir(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
    }

    /**
     * Organigrama mínimo para probar la cascada de disponibilidad:
     *   Área (jefeArea, 276) -> Oficina (jefeInmediato, 728) -> trabajador
     */
    private function armarOrganigrama(string $regimenJefeInmediato = '728', string $regimenJefeArea = '276'): array
    {
        $jefeArea = $this->usuarioDePrueba(['regimen' => $regimenJefeArea], ['trabajador']);
        $jefeInmediato = $this->usuarioDePrueba(['regimen' => $regimenJefeInmediato], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => $regimenJefeInmediato], ['trabajador']);

        $area = UnidadOrganica::create(['nombre' => 'Área', 'jefe_id' => $jefeArea->id]);
        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $area->id, 'jefe_id' => $jefeInmediato->id]);

        $jefeArea->update(['unidad_organica_id' => $area->id]);
        $jefeInmediato->update(['unidad_organica_id' => $oficina->id]);
        $trabajador->update(['unidad_organica_id' => $oficina->id]);

        return [$jefeArea->fresh(), $jefeInmediato->fresh(), $trabajador->fresh()];
    }

    public function test_un_trabajador_puede_tener_varias_papeletas_a_la_vez(): void
    {
        [, , $trabajador] = $this->armarOrganigrama();
        $this->turnoDePrueba($trabajador);
        $crear = app(CrearPapeletaAction::class);

        $primera = $crear->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
        $crear->ejecutar($trabajador, $this->motivoDe('SALUD'), []);
        $crear->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);

        $this->assertSame(3, Papeleta::where('trabajador_id', $trabajador->id)->count());
        $this->assertTrue($primera->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_quien_no_tiene_jefe_arriba_envia_su_papeleta_directo_a_rrhh_si_rrhh_esta_en_horario(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->ir('2026-09-21 10:00:00'); // lunes, dentro de 07:45-16:15

        $tope = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);
        $this->turnoDePrueba($tope);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));
        $this->assertNull($papeleta->jefe_inmediato_id);
        $this->assertNull($papeleta->jefe_area_id);
        $this->assertTrue($papeleta->fresh()->sinJefatura());
    }

    public function test_quien_no_tiene_jefe_arriba_y_rrhh_fuera_de_horario_se_autoautoriza_con_posthoc(): void
    {
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->ir('2026-09-21 22:00:00'); // lunes, fuera de 07:45-16:15

        $tope = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);
        $this->turnoDePrueba($tope);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope->fresh(), $this->motivoDe('PARTICULAR'), []);
        $papeleta = $papeleta->fresh();

        $this->assertTrue($papeleta->estado->equals(AutorizadaYCorriendo::class));
        $this->assertTrue($papeleta->autorizado_con_rrhh_fuera_horario);
        $this->assertSame('pendiente', $papeleta->revision_posthoc_estado);
        $this->assertSame('2026-09-21 22:00:00', $papeleta->hora_salida_real->format('Y-m-d H:i:s'));

        $historial = HistorialPapeleta::where('papeleta_id', $papeleta->id)->latest('id')->first();
        $this->assertSame('sistema', $historial->actor_tipo);
    }

    public function test_jefe_inmediato_728_disponible_manda_a_pendiente_jefe_aunque_jefe_de_area_este_fuera_de_horario(): void
    {
        [, $jefeInmediato, $trabajador] = $this->armarOrganigrama(regimenJefeInmediato: '728', regimenJefeArea: '276');
        $this->ir('2026-09-21 22:00:00'); // jefe de área (276) fuera de horario, pero no importa: decide el jefe inmediato
        $this->turnoDePrueba($trabajador);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
        $this->assertSame($jefeInmediato->id, $papeleta->jefe_inmediato_id);
    }

    /**
     * El caso concreto planteado: jefe inmediato pide SU PROPIA papeleta
     * a las 22:00 (728, sin ventana de creación). Su decisor resuelto es
     * el jefe de área (276), fuera de su horario ordinario a esa hora.
     * RRHH (276) también fuera de horario. Nadie decide -> el sistema
     * autoriza y queda para revisión post-hoc, nunca el propio jefe.
     */
    public function test_jefe_inmediato_pide_su_propia_papeleta_de_noche_sin_jefe_de_area_ni_rrhh_disponibles(): void
    {
        [$jefeArea, $jefeInmediato] = $this->armarOrganigrama(regimenJefeInmediato: '728', regimenJefeArea: '276');
        $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->ir('2026-09-21 22:00:00'); // jefe de área y RRHH (276) fuera de horario
        $this->turnoDePrueba($jefeInmediato);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($jefeInmediato, $this->motivoDe('PARTICULAR'), []);
        $papeleta = $papeleta->fresh();

        $this->assertSame($jefeArea->id, $papeleta->jefe_inmediato_id); // fotografiado, aunque no decidió
        $this->assertTrue($papeleta->estado->equals(AutorizadaYCorriendo::class));
        $this->assertTrue($papeleta->autorizado_con_rrhh_fuera_horario);
        $this->assertSame('pendiente', $papeleta->revision_posthoc_estado);

        $historial = HistorialPapeleta::where('papeleta_id', $papeleta->id)->latest('id')->first();
        $this->assertSame('sistema', $historial->actor_tipo);
        $this->assertStringContainsString('Jefe inmediato fuera de su horario', $historial->justificacion);
    }

    public function test_rrhh_no_puede_aprobar_la_papeleta_propia_del_jefe_tope(): void
    {
        $tope = $this->usuarioDePrueba([], ['trabajador', 'rrhh']);
        $unidad = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);
        $this->turnoDePrueba($tope);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->expectException(\App\Exceptions\PapeletaException::class);
        app(\App\Actions\Papeleta\AprobarRrhhAction::class)->ejecutar($papeleta, $tope);
    }

    public function test_rrhh_no_puede_observar_una_papeleta_sin_jefatura(): void
    {
        $tope = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);
        $this->turnoDePrueba($tope);
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->expectException(\App\Exceptions\PapeletaException::class);
        app(\App\Actions\Papeleta\ObservarRrhhAction::class)->ejecutar($papeleta, $rrhh, 'falta detalle');
    }

    public function test_no_existe_el_motivo_emergencia(): void
    {
        $this->assertFalse(Motivo::where('codigo', 'EMERGENCIA')->exists());
    }

    public function test_la_bd_ya_no_tiene_columnas_de_emergencia_ni_de_slots(): void
    {
        foreach ([
            'slot_normal_activo',
            'slot_emergencia_activo',
            'es_emergencia',
            'visto_bueno_jefe_emergencia',
            'visto_bueno_rrhh_emergencia',
            'subsanacion_emergencia_fecha_limite',
            'subsanacion_emergencia_adjunto_path',
        ] as $columna) {
            $this->assertFalse(Schema::hasColumn('papeletas', $columna), "papeletas.{$columna} debería haberse eliminado");
        }

        foreach (['permite_bypass_aprobacion', 'participa_regla_exclusividad'] as $columna) {
            $this->assertFalse(Schema::hasColumn('motivos', $columna), "motivos.{$columna} debería haberse eliminado");
        }
    }
}
