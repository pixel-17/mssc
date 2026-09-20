<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\MarcarAbandonoSobreRetornoPendienteAction;
use App\Actions\Papeleta\ReconocerObservacionRrhhAction;
use App\Actions\Papeleta\RevisarEmergenciaAction;
use App\Actions\Papeleta\RevisarSustentoAction;
use App\Actions\Papeleta\RevisionPosthocAction;
use App\Actions\Papeleta\SubsanarEmergenciaAction;
use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\ObservadaPorRrhh;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Estas Actions releen la papeleta bajo lockForUpdate() dentro de la
 * transacción. Aquí se simula lo que ese lock protege: el objeto que
 * llega a la Action quedó DESACTUALIZADO porque otra petición ya cambió
 * la fila. Con el estado viejo en memoria la Action antes lo pisaba; ahora
 * debe rechazar y dejar la fila como la dejó la otra petición.
 *
 * Ojo: en SQLite (y en estos tests) lockForUpdate() no bloquea nada; lo
 * que se prueba es la RELECTURA. La exclusión mutua real solo se ejerce
 * en MySQL con dos conexiones concurrentes.
 */
class LockRelecturaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $trabajador;

    private User $jefe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefe = $this->usuarioDePrueba();
        $this->trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);
    }

    /** Cambia la fila por debajo, sin pasar por el modelo (como lo haría otra petición). */
    private function cambiarEnBd(Papeleta $papeleta, array $columnas): void
    {
        DB::table('papeletas')->where('id', $papeleta->id)->update($columnas);
    }

    public function test_marcar_abandono_no_pisa_una_papeleta_que_ya_se_cerro(): void
    {
        $obsoleta = $this->papeletaDePrueba($this->trabajador, RetornoPendienteSustento::class);
        $this->cambiarEnBd($obsoleta, ['estado' => 'cerrada', 'slot_normal_activo' => null]);

        $this->assertThrows(
            fn () => app(MarcarAbandonoSobreRetornoPendienteAction::class)->ejecutar($obsoleta, $this->jefe, 'No volvió'),
            PapeletaException::class,
            'Esta acción solo aplica a papeletas en espera de sustento.',
        );

        $this->assertTrue($obsoleta->fresh()->estado->equals(Cerrada::class));
    }

    public function test_reconocer_observacion_no_reabre_una_papeleta_que_ya_avanzo(): void
    {
        $obsoleta = $this->papeletaDePrueba($this->trabajador, ObservadaPorRrhh::class);
        $this->cambiarEnBd($obsoleta, ['estado' => 'pendiente_jefe']);

        $this->assertThrows(
            fn () => app(ReconocerObservacionRrhhAction::class)->ejecutar($obsoleta, $this->jefe, 'Coordinado'),
            PapeletaException::class,
            'Esta papeleta no tiene una observación de RRHH pendiente de reconocer.',
        );
    }

    public function test_revision_posthoc_no_se_registra_dos_veces(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $obsoleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'autorizado_con_rrhh_fuera_horario' => true,
            'revision_posthoc_estado' => 'pendiente',
        ]);
        $this->cambiarEnBd($obsoleta, ['revision_posthoc_estado' => 'aprobada']);

        $this->assertThrows(
            fn () => app(RevisionPosthocAction::class)->observar($obsoleta, $rrhh, 'Fuera de norma'),
            PapeletaException::class,
            'Esta papeleta ya tiene una revisión post-hoc registrada.',
        );

        $this->assertSame('aprobada', $obsoleta->fresh()->revision_posthoc_estado);
    }

    public function test_revisar_sustento_rechaza_si_la_papeleta_ya_no_espera_sustento(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, RetornoPendienteSustento::class);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => 'sustentos/prueba.pdf',
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
        ]);
        $sustento->load('papeleta'); // relación precargada = dato viejo en memoria

        $this->cambiarEnBd($papeleta, ['estado' => 'cerrada', 'slot_normal_activo' => null]);

        $this->assertThrows(
            fn () => app(RevisarSustentoAction::class)->aprobar($sustento, $this->jefe),
            PapeletaException::class,
            'Esta papeleta ya no está esperando sustento.',
        );

        $this->assertSame('presentado', $sustento->fresh()->estado, 'no debe quedar escritura a medias');
    }

    public function test_revisar_sustento_aprobado_cierra_la_papeleta(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, RetornoPendienteSustento::class);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => 'sustentos/prueba.pdf',
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
        ]);

        app(RevisarSustentoAction::class)->aprobar($sustento, $this->jefe);

        $this->assertTrue($papeleta->fresh()->estado->equals(Cerrada::class));
        $this->assertSame('aprobado', $sustento->fresh()->estado);
        $this->assertSame($this->jefe->id, $sustento->fresh()->revisado_por_id);
    }

    public function test_un_revisor_no_puede_revisar_el_sustento_de_su_propia_papeleta(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh', 'trabajador']);
        $papeleta = $this->papeletaDePrueba($rrhh, RetornoPendienteSustento::class);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => 'sustentos/prueba.pdf',
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
        ]);

        $this->assertThrows(
            fn () => app(RevisarSustentoAction::class)->aprobar($sustento, $rrhh),
            PapeletaException::class,
            'No puedes decidir, revisar ni cerrar tu propia papeleta.',
        );

        $this->assertSame('presentado', $sustento->fresh()->estado);
    }

    public function test_subsanar_emergencia_rechaza_si_otra_peticion_ya_la_subsano(): void
    {
        $obsoleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'slot_normal_activo' => null,
            'slot_emergencia_activo' => true,
            'es_emergencia' => true,
            'visto_bueno_jefe_emergencia' => 'observado',
        ]);
        $this->cambiarEnBd($obsoleta, ['visto_bueno_jefe_emergencia' => 'pendiente']);

        $this->assertThrows(
            fn () => app(SubsanarEmergenciaAction::class)->ejecutar($obsoleta, $this->trabajador, 'Adjunto corregido'),
            PapeletaException::class,
            'Esta Emergencia no tiene ninguna observación pendiente de subsanar.',
        );
    }

    public function test_revisar_emergencia_no_permite_dar_el_visto_bueno_dos_veces(): void
    {
        $obsoleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'slot_normal_activo' => null,
            'slot_emergencia_activo' => true,
            'es_emergencia' => true,
            'visto_bueno_jefe_emergencia' => 'pendiente',
        ]);
        $this->cambiarEnBd($obsoleta, ['visto_bueno_jefe_emergencia' => 'aprobado']);

        $this->assertThrows(
            fn () => app(RevisarEmergenciaAction::class)->aprobar($obsoleta, $this->jefe),
            PapeletaException::class,
            'Ya diste tu visto bueno sobre esta Emergencia.',
        );
    }

    public function test_revisar_emergencia_registra_el_visto_bueno_del_jefe(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class, [
            'slot_normal_activo' => null,
            'slot_emergencia_activo' => true,
            'es_emergencia' => true,
            'visto_bueno_jefe_emergencia' => 'pendiente',
        ]);

        app(RevisarEmergenciaAction::class)->aprobar($papeleta, $this->jefe);

        $fresca = $papeleta->fresh();
        $this->assertSame('aprobado', $fresca->visto_bueno_jefe_emergencia);
        $this->assertSame($this->jefe->id, $fresca->visto_bueno_jefe_emergencia_por_id);
    }
}
