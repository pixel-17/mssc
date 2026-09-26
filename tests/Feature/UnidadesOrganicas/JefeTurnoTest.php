<?php

namespace Tests\Feature\UnidadesOrganicas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\ConfiguracionTurno;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión de dos problemas reales encontrados en Avance 00.60, y del
 * cambio de Avance 00.74: `jefes_turno` ya NO guarda un turno fijo
 * elegido a mano — solo dice QUIÉN es jefe inmediato adicional de la
 * unidad. El turno que cada uno cubre sale siempre de su propia
 * programación de calendario (`configuraciones_turno` / `turnos`, la
 * misma que usan los trabajadores):
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

    /** Da a $jefe su propia configuración de calendario para $turno (estructural, no depende de la hora real). */
    private function configurarTurnoDe(User $jefe, string $turno): void
    {
        ConfiguracionTurno::create([
            'user_id' => $jefe->id,
            'turno' => $turno,
            'fecha_ancla' => Carbon::parse('2026-09-01'),
            'dias_trabajo' => 6,
            'dias_descanso' => 1,
        ]);
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
            'jefe_id' => $jefe->id,
        ]);

        $this->assertDatabaseHas('jefes_turno', ['id' => $jefeTurno->id]);

        $unidad->delete();

        $this->assertDatabaseMissing('jefes_turno', ['id' => $jefeTurno->id]);
    }

    /**
     * Desde el cambio "sin turno fijo" (unique ahora es unidad+jefe_id):
     * una unidad SÍ puede tener varios jefes inmediatos adicionales.
     * Lo único que sigue bloqueado es agregar al MISMO jefe dos veces a
     * la misma unidad.
     */
    public function test_una_unidad_puede_tener_varios_jefes_adicionales_pero_no_el_mismo_jefe_repetido(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $otroJefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefe->id]);

        // Segundo jefe para la MISMA unidad: permitido.
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $otroJefe->id]);

        $this->assertSame(2, JefeTurno::where('unidad_organica_id', $unidad->id)->count());

        // Repetir al mismo jefe en la misma unidad sigue bloqueado.
        $this->expectException(QueryException::class);
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefe->id]);
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

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefeDeNoche->id]);

        // El jefe inmediato no tiene turno fijo: solo cuenta si, como el
        // trabajador, está literalmente de turno NOCHE en este momento.
        $this->turnoVigenteDePrueba($jefeDeNoche, 'NOCHE', '22:00:00', '06:00:00');
        $this->turnoVigenteDePrueba($trabajador, 'NOCHE', '22:00:00', '06:00:00');

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

        $this->assertSame($jefeDeNoche->id, $papeleta->jefe_inmediato_id);
        $this->assertNotSame($jefeTitular->id, $papeleta->jefe_inmediato_id);
    }

    public function test_si_ningun_jefe_inmediato_coincide_con_el_turno_vigente_se_informa_y_no_se_crea_la_papeleta(): void
    {
        $jefeTitular = $this->usuarioDePrueba([], ['trabajador']);
        $jefeDeNoche = $this->usuarioDePrueba([], ['trabajador']);
        $trabajador = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefeTitular->id]);
        $jefeTitular->update(['unidad_organica_id' => $unidad->id]);
        $jefeDeNoche->update(['unidad_organica_id' => $unidad->id]);
        $trabajador->update(['unidad_organica_id' => $unidad->id]);

        // El único jefe inmediato adicional está, hoy, de turno NOCHE;
        // el trabajador entra en MAÑANA: nadie coincide.
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefeDeNoche->id]);
        $this->turnoVigenteDePrueba($jefeDeNoche, 'NOCHE', '22:00:00', '06:00:00');

        $this->turnoVigenteDePrueba($trabajador, 'MANANA', '06:00:00', '14:00:00');

        try {
            app(CrearPapeletaAction::class)->ejecutar($trabajador->fresh(), $this->motivoDe('PARTICULAR'), []);

            $this->fail('Debió lanzar PapeletaException: nadie está de turno MAÑANA.');
        } catch (PapeletaException $e) {
            $this->assertStringContainsString('Tu turno actual no tiene un jefe inmediato activo asignado', $e->getMessage());
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

        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $jefeDeNoche->id]);
        $this->configurarTurnoDe($jefeDeNoche, 'NOCHE');

        $this->assertSame($jefeDeNoche->id, $unidad->fresh()->resolverJefeInmediato('NOCHE'));

        $jefeDeNoche->update(['activo' => false]);

        $this->assertNull($unidad->fresh()->resolverJefeInmediato('NOCHE'));

        $this->turnoVigenteDePrueba($trabajador, 'NOCHE', '22:00:00', '06:00:00');

        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('Tu turno actual no tiene un jefe inmediato activo asignado');

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

    public function test_el_tope_del_organigrama_728_tambien_se_bloquea_sin_jefe_de_area(): void
    {
        $tope = $this->usuarioDePrueba(['regimen' => '728'], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $tope->id]);
        $tope->update(['unidad_organica_id' => $unidad->id]);

        $this->turnoVigenteDePrueba($tope, 'MANANA', '06:00:00', '14:00:00');

        $this->expectException(PapeletaException::class);
        $this->expectExceptionMessage('Tu unidad no tiene un Jefe de Área activo asignado');

        app(CrearPapeletaAction::class)->ejecutar($tope->fresh(), $this->motivoDe('PARTICULAR'), []);
    }

    public function test_un_jefe_de_turno_que_no_es_jefe_id_responde_ante_el_jefe_del_mismo_turno_de_la_unidad_padre(): void
    {
        $jefePadre = $this->usuarioDePrueba([], ['trabajador']);
        $titular = $this->usuarioDePrueba([], ['trabajador']);
        $jefeDeManana = $this->usuarioDePrueba([], ['trabajador']);

        $padre = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefePadre->id]);
        JefeTurno::create(['unidad_organica_id' => $padre->id, 'jefe_id' => $jefePadre->id]);
        $this->configurarTurnoDe($jefePadre, 'MANANA');

        $hija = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $padre->id, 'jefe_id' => $titular->id]);
        JefeTurno::create(['unidad_organica_id' => $hija->id, 'jefe_id' => $jefeDeManana->id]);

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
