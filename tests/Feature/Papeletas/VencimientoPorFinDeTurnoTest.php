<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Models\Papeleta;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\Vencida;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Una papeleta sin decidir vence al fin REAL de su turno (fin_turno_at,
 * fijado al crearla), no a medianoche. Fechas de referencia: lunes
 * 2026-09-21 (día laborable para 276) y martes 2026-09-22.
 */
class VencimientoPorFinDeTurnoTest extends TestCase
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
     * Inserta la fila cruda con `fecha` como Y-m-d (igual que una columna DATE
     * en MySQL): Turno::create la guardaría como "Y-m-d 00:00:00" en SQLite y
     * la búsqueda por fecha de Turno::vigenteParaUsuario no la encontraría.
     */
    private function turno(User $trabajador, string $fecha, string $codigo, string $inicio, string $fin): void
    {
        DB::table('turnos')->insert([
            'user_id' => $trabajador->id,
            'sede_id' => $this->sedeDePrueba()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'es_descanso' => false,
            'turno' => $codigo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crear(User $trabajador): Papeleta
    {
        return app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
    }

    private function vencimientos(): void
    {
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();
    }

    public function test_728_noche_no_vence_a_medianoche_y_el_jefe_aun_puede_decidir_a_la_1_02(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->conJefeDePrueba($trabajador);
        $this->turno($trabajador, '2026-09-21', 'NOCHE', '22:00', '06:00');

        $this->ir('2026-09-21 23:58:00');
        $papeleta = $this->crear($trabajador);

        $this->assertSame('2026-09-22 06:00:00', $papeleta->fresh()->fin_turno_at->format('Y-m-d H:i:s'));

        $this->ir('2026-09-22 01:02:00');
        $this->vencimientos();

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_728_noche_vence_recien_cuando_termina_el_turno_a_las_6(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->conJefeDePrueba($trabajador);
        $this->turno($trabajador, '2026-09-21', 'NOCHE', '22:00', '06:00');

        $this->ir('2026-09-21 23:58:00');
        $papeleta = $this->crear($trabajador);

        $this->ir('2026-09-22 05:59:00');
        $this->vencimientos();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));

        $this->ir('2026-09-22 06:01:00');
        $this->vencimientos();
        $this->assertTrue($papeleta->fresh()->estado->equals(Vencida::class));
    }

    public function test_728_noche_puede_crear_despues_de_medianoche_con_el_turno_del_dia_anterior(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->turno($trabajador, '2026-09-21', 'NOCHE', '22:00', '06:00');

        $this->ir('2026-09-22 01:00:00');
        $papeleta = $this->crear($trabajador);

        $this->assertSame('2026-09-21', $papeleta->fresh()->dia_operativo->toDateString());
        $this->assertSame('2026-09-22 06:00:00', $papeleta->fresh()->fin_turno_at->format('Y-m-d H:i:s'));
    }

    public function test_728_manana_vence_a_las_14_y_no_a_medianoche(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->conJefeDePrueba($trabajador);
        $this->turno($trabajador, '2026-09-21', 'MANANA', '06:00', '14:00');

        $this->ir('2026-09-21 13:00:00');
        $papeleta = $this->crear($trabajador);

        $this->ir('2026-09-21 13:59:00');
        $this->vencimientos();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));

        $this->ir('2026-09-21 14:01:00');
        $this->vencimientos();
        $this->assertTrue($papeleta->fresh()->estado->equals(Vencida::class));
    }

    public function test_728_sin_turno_no_puede_crear_papeleta(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $this->ir('2026-09-21 23:50:00');

        $this->expectException(\App\Exceptions\PapeletaException::class);
        $this->crear($trabajador);
    }

    public function test_276_vence_al_terminar_el_horario_ordinario(): void
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $this->conJefeDePrueba($trabajador);

        $this->ir('2026-09-21 15:00:00');
        $papeleta = $this->crear($trabajador);

        $this->assertSame('2026-09-21 16:15:59', $papeleta->fresh()->fin_turno_at->format('Y-m-d H:i:s'));

        $this->ir('2026-09-21 16:15:30');
        $this->vencimientos();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));

        $this->ir('2026-09-21 16:16:30');
        $this->vencimientos();
        $this->assertTrue($papeleta->fresh()->estado->equals(Vencida::class));
    }

    public function test_papeleta_anterior_sin_fin_turno_at_usa_el_criterio_legado(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $this->ir('2026-09-22 10:00:00');
        $papeleta = $this->papeletaDePrueba($trabajador, PendienteJefe::class, [
            'dia_operativo' => '2026-09-21',
            'fin_turno_at' => null,
        ]);

        $this->vencimientos();

        $this->assertTrue($papeleta->fresh()->estado->equals(Vencida::class));
    }
}
