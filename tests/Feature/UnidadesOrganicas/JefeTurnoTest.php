<?php

namespace Tests\Feature\UnidadesOrganicas;

use App\Actions\Papeleta\CrearPapeletaAction;
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
 * 2. `UnidadOrganica::resolverJefeInmediato()` / `jefaturasDe()` deben
 *    usar `jefes_turno` cuando existe fila para el turno vigente del
 *    trabajador, y caer a `jefe_id` (comportamiento de siempre) cuando
 *    no hay fila para ese turno. Nada de esto tenía test antes.
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

    public function test_no_se_puede_repetir_el_mismo_turno_dos_veces_en_la_misma_unidad(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $otroJefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'MANANA', 'jefe_id' => $jefe->id]);

        $this->expectException(QueryException::class);
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'turno' => 'MANANA', 'jefe_id' => $otroJefe->id]);
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

    public function test_si_el_turno_vigente_no_tiene_fila_en_jefes_turno_cae_al_jefe_titular(): void
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

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertSame($jefeTitular->id, $papeleta->jefe_inmediato_id);
    }

    public function test_resolver_jefe_inmediato_devuelve_null_sin_fila_ni_jefe_titular(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Sin jefe']);

        $this->assertNull($unidad->resolverJefeInmediato('MANANA'));
        $this->assertNull($unidad->resolverJefeInmediato(null));
    }
}
