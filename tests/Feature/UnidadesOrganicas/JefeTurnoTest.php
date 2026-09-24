<?php

namespace Tests\Feature\UnidadesOrganicas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\JefeTurno;
use App\Models\Turno;
use App\Models\UnidadOrganica;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión de dos problemas reales encontrados en Avance 00.60:
 *
 * 1. La migración de `jefes_turno` apuntaba su FK a la tabla
 *    `unidades_organicas` (no existe; la tabla real es
 *    `unidad_organicas`). Esto rompía `migrate:fresh` por completo.
 *    Este test no puede "correr" una migración rota, pero al insertar
 *    contra la tabla real prueba que la FK quedó bien apuntada: si
 *    alguien revierte el nombre por error, `migrate:fresh` en el CI
 *    vuelve a fallar antes de llegar aquí.
 *
 * 2. `UnidadOrganica::resolverJefeInmediato()` / `jefaturasDe()` usan
 *    SOLO `jefes_turno` para los turnos rotativos (MANANA/TARDE/NOCHE):
 *    sin fila, o con el jefe desactivado, el turno no tiene jefe y la
 *    papeleta no se crea (se informa al trabajador). Ya no hay caída a
 *    `jefe_id` para esos turnos; para 276 (turno null) sigue usándose
 *    `jefe_id`.
 */
class JefeTurnoTest extends TestCase
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

    /**
     * Prueba directa de la FK: si `jefes_turno.unidad_organica_id` no
     * apuntara de verdad a `unidad_organicas`, borrar la unidad no
     * arrastraría (cascadeOnDelete) la fila de jefes_turno.
     */
    public function test_la_fk_de_jefes_turno_apunta_a_la_tabla_real_y_hace_cascade(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);

        $jefeTurno = JefeTurno::create([
            'unidad_organica_id' => $unidad->id,
            'turno' => 'MANANA',
            'jefe_id' => $jefe->id,
        ]);

        $this->assertDatabaseHas('jefes_turno', ['id' => $jefeTurno->id]);

        $unidad->delete();

        $this->assertDatabaseMissing('jefes_turno', ['id' => $jefeTurno->id]);
    }

    /**
     * Desde el cambio "varios jefes por turno" (unique ahora es
     * unidad+turno+jefe_id, no unidad+turno): un turno SÍ puede tener
     * más de un jefe asignado. Lo único que sigue bloqueado es
     * duplicar exactamente la misma fila (mismo jefe, mismo turno,
     * misma unidad) dos veces.
     */
    public function test_un_turno_puede_tener_varios_jefes_pero_no_la_misma_fila_repetida(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $otroJefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'MANANA', 'jefe_id' => $jefe->id]);

        // Segundo jefe para el MISMO turno: ahora permitido.
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'MANANA', 'jefe_id' => $otroJefe->id]);

        $this->assertSame(2, JefeTurno::where('unidad_organica_id', $unidad->id)->where('turno', 'MANANA')->count());

        // Repetir la fila exacta (mismo jefe, mismo turno, misma unidad) sigue bloqueado.
        $this->expectException(QueryException::class);
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'MANANA', 'jefe_id' => $jefe->id]);
    }

    public function test_la_papeleta_de_un_728_fotografia_al_jefe_del_turno_vigente_no_al_jefe_titular(): void
    {
        $jefeTitular = $this->usuarioDePrueba([], ['trabajador']);
        $jefeDeNoche = $this->usuarioDePrueba([], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeTitular->id]);
        $jefeTitular->update(['unidad_organica_id' => $unidad->id]);
        $jefeDeNoche->update(['unidad_organica_id' => $unidad->id]);
        $trabajador->update(['unidad_organica_id' => $unidad->id]);

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'NOCHE', 'jefe_id' => $jefeDeNoche->id]);

        $this->turnoVigenteDePrueba($trabajador, 'NOCHE', '22:00:00', '06:00:00');

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertSame($jefeDeNoche->id, $papeleta->jefe_inmediato_id);
        $this->assertNotSame($jefeTitular->id, $papeleta->jefe_inmediato_id);
    }

    public function test_si_el_turno_vigente_no_tiene_jefe_asignado_se_informa_y_no_se_crea_la_papeleta(): void
    {
        $jefeTitular = $this->usuarioDePrueba([], ['trabajador']);
        $jefeDeNoche = $this->usuarioDePrueba([], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeTitular->id]);
        $jefeTitular->update(['unidad_organica_id' => $unidad->id]);
        $jefeDeNoche->update(['unidad_organica_id' => $unidad->id]);
        $trabajador->update(['unidad_organica_id' => $unidad->id]);

        // Solo hay jefe asignado para NOCHE; el trabajador entra en MAÑANA.
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'NOCHE', 'jefe_id' => $jefeDeNoche->id]);

        $this->turnoVigenteDePrueba($trabajador, 'MANANA', '06:00:00', '14:00:00');

        try {
            app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

            $this->fail('Debió lanzar PapeletaException: el turno MAÑANA no tiene jefe asignado.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('no tiene un jefe inmediato asignado para el turno Mañana', $e->getMessage());
        }

        $this->assertDatabaseCount('papeletas', 0);
    }

    public function test_un_jefe_de_turno_desactivado_cuenta_como_turno_sin_jefe(): void
    {
        $jefeTitular = $this->usuarioDePrueba([], ['trabajador']);
        $jefeDeNoche = $this->usuarioDePrueba([], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeTitular->id]);
        $jefeTitular->update(['unidad_organica_id' => $unidad->id]);
        $jefeDeNoche->update(['unidad_organica_id' => $unidad->id]);
        $trabajador->update(['unidad_organica_id' => $unidad->id]);

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'NOCHE', 'jefe_id' => $jefeDeNoche->id]);

        $this->assertSame($jefeDeNoche->id, $unidad->fresh()->resolverJefeInmediato('NOCHE'));

        $jefeDeNoche->update(['activo' => false]);

        $this->assertNull($unidad->fresh()->resolverJefeInmediato('NOCHE'));

        $this->turnoVigenteDePrueba($trabajador, 'NOCHE', '22:00:00', '06:00:00');

        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('no tiene un jefe inmediato asignado para el turno Noche');

        app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);
    }

    public function test_un_turno_que_no_es_rotativo_usa_el_jefe_titular(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);

        $this->assertSame($jefe->id, $unidad->resolverJefeInmediato(null));
        $this->assertSame($jefe->id, $unidad->resolverJefeInmediato('DIA'));
        $this->assertNull($unidad->resolverJefeInmediato('MANANA'));
    }

    public function test_el_tope_del_organigrama_728_no_se_bloquea_por_no_tener_jefe_de_turno(): void
    {
        $tope = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);

        $this->turnoVigenteDePrueba($tope, 'MANANA', '06:00:00', '14:00:00');

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($tope->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertNull($papeleta->jefe_inmediato_id);
    }

    public function test_un_jefe_de_turno_que_no_es_jefe_id_responde_ante_el_jefe_del_mismo_turno_de_la_unidad_padre(): void
    {
        $jefePadre = $this->usuarioDePrueba([], ['trabajador']);
        $titular = $this->usuarioDePrueba([], ['trabajador']);
        $jefeDeManana = $this->usuarioDePrueba([], ['trabajador']);

        $padre = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefePadre->id]);
        JefeTurno::create(['unidad_organica_id' => $padre->id, 'turno' => 'MANANA', 'jefe_id' => $jefePadre->id]);

        $hija = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $padre->id, 'jefe_id' => $titular->id]);
        JefeTurno::create(['unidad_organica_id' => $hija->id, 'turno' => 'MANANA', 'jefe_id' => $jefeDeManana->id]);

        $this->assertTrue($hija->fresh()->esJefeDeLaUnidad($jefeDeManana));

        [$jefeInmediatoId, $jefeAreaId] = $hija->fresh()->jefaturasDe($jefeDeManana, 'MANANA');

        $this->assertSame($jefePadre->id, $jefeInmediatoId);
        $this->assertNull($jefeAreaId);
    }

    public function test_resolver_jefe_inmediato_devuelve_null_sin_fila_ni_jefe_titular(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Sin jefe']);

        $this->assertNull($unidad->resolverJefeInmediato('MANANA'));
        $this->assertNull($unidad->resolverJefeInmediato(null));
    }
}
