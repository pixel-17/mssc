<?php

namespace Tests\Feature\Admin;

use App\Livewire\Turnos\TurnoForm;
use App\Livewire\Turnos\TurnoIndex;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * CRUD manual de Turnos (un turno = un trabajador en una fecha).
 *
 * Los turnos de partida se insertan con DB::table(): así `fecha` queda como
 * 'Y-m-d' y las horas como 'HH:MM:SS', que es exactamente lo que devuelve una
 * columna DATE/TIME de MySQL (el motor real del proyecto), y no lo que
 * SQLite devolvería con Turno::create().
 */
class TurnoCrudTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->trabajador = $this->usuarioDePrueba();
    }

    /** @param  array<string, mixed>  $atributos */
    private function turnoExistente(User $trabajador, string $fecha, array $atributos = []): Turno
    {
        $id = DB::table('turnos')->insertGetId([
            'user_id' => $trabajador->id,
            'sede_id' => null,
            'fecha' => $fecha,
            'hora_inicio' => '07:00:00',
            'hora_fin' => '15:00:00',
            'es_descanso' => false,
            'turno' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$atributos,
        ]);

        return Turno::findOrFail($id);
    }

    public function test_el_admin_crea_un_turno_con_horas(): void
    {
        $sede = $this->sedeDePrueba();

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('userId', $this->trabajador->id)
            ->set('sedeId', $sede->id)
            ->set('fecha', '2026-09-21')
            ->set('horaInicio', '07:00')
            ->set('horaFin', '15:00')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('turnos.index'));

        $turno = Turno::where('user_id', $this->trabajador->id)->firstOrFail();

        $this->assertSame($sede->id, $turno->sede_id);
        $this->assertFalse($turno->es_descanso);
        $this->assertNull($turno->turno, 'un turno cargado a mano no arrastra código de turno');
        $this->assertStringStartsWith('07:00', $turno->hora_inicio);
        $this->assertStringStartsWith('15:00', $turno->hora_fin);
    }

    public function test_un_dia_de_descanso_se_guarda_sin_horas(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('userId', $this->trabajador->id)
            ->set('fecha', '2026-09-22')
            ->set('esDescanso', true)
            ->set('horaInicio', '07:00')
            ->set('horaFin', '15:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $turno = Turno::where('user_id', $this->trabajador->id)->firstOrFail();

        $this->assertTrue($turno->es_descanso);
        $this->assertNull($turno->hora_inicio, 'el descanso descarta las horas aunque se hayan escrito');
        $this->assertNull($turno->hora_fin);
    }

    public function test_sin_descanso_las_horas_son_obligatorias(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('userId', $this->trabajador->id)
            ->set('fecha', '2026-09-21')
            ->call('guardar')
            ->assertHasErrors(['horaInicio' => 'required', 'horaFin' => 'required']);

        $this->assertSame(0, Turno::count());
    }

    public function test_trabajador_y_fecha_son_obligatorios(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('horaInicio', '07:00')
            ->set('horaFin', '15:00')
            ->call('guardar')
            ->assertHasErrors(['userId' => 'required', 'fecha' => 'required']);
    }

    public function test_las_horas_mal_escritas_se_rechazan(): void
    {
        foreach (['7am', '25:00', '0700', '7:00'] as $malo) {
            Livewire::actingAs($this->admin)
                ->test(TurnoForm::class)
                ->set('userId', $this->trabajador->id)
                ->set('fecha', '2026-09-21')
                ->set('horaInicio', $malo)
                ->set('horaFin', '15:00')
                ->call('guardar')
                ->assertHasErrors(['horaInicio' => 'date_format']);
        }

        $this->assertSame(0, Turno::count());
    }

    public function test_la_sede_debe_existir(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('userId', $this->trabajador->id)
            ->set('sedeId', 999999)
            ->set('fecha', '2026-09-21')
            ->set('horaInicio', '07:00')
            ->set('horaFin', '15:00')
            ->call('guardar')
            ->assertHasErrors(['sedeId' => 'exists']);
    }

    public function test_un_trabajador_no_puede_tener_dos_turnos_el_mismo_dia(): void
    {
        $this->turnoExistente($this->trabajador, '2026-09-21');

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('userId', $this->trabajador->id)
            ->set('fecha', '2026-09-21')
            ->set('horaInicio', '15:00')
            ->set('horaFin', '23:00')
            ->call('guardar')
            ->assertHasErrors(['userId' => 'unique']);

        $this->assertSame(1, Turno::count());
    }

    public function test_el_mismo_dia_si_puede_tener_turno_otro_trabajador(): void
    {
        $this->turnoExistente($this->trabajador, '2026-09-21');
        $otro = $this->usuarioDePrueba();

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class)
            ->set('userId', $otro->id)
            ->set('fecha', '2026-09-21')
            ->set('horaInicio', '07:00')
            ->set('horaFin', '15:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, Turno::count());
    }

    public function test_editar_precarga_y_actualiza_el_turno(): void
    {
        $turno = $this->turnoExistente($this->trabajador, '2026-09-21', ['turno' => 'MANANA']);

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class, ['turno' => $turno])
            ->assertSet('userId', $this->trabajador->id)
            ->assertSet('fecha', '2026-09-21')
            ->set('horaInicio', '14:00')
            ->set('horaFin', '22:00')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('turnos.index'));

        $turno = $turno->fresh();

        $this->assertStringStartsWith('14:00', $turno->hora_inicio);
        $this->assertStringStartsWith('22:00', $turno->hora_fin);
        $this->assertNull($turno->turno, 'al cambiar las horas a mano se descarta el código anterior');
        $this->assertSame(1, Turno::count());
    }

    /**
     * MySQL devuelve las columnas TIME como 'HH:MM:SS' y mount() las copia
     * tal cual a horaInicio/horaFin, pero la regla es 'date_format:H:i'.
     * Si el admin edita SOLO la sede (sin retocar las horas), el guardado
     * debería funcionar. Si este test falla, es un bug real: la edición de
     * cualquier turno con horas queda bloqueada hasta reescribirlas.
     */
    public function test_editar_solo_la_sede_no_exige_reescribir_las_horas(): void
    {
        $sede = $this->sedeDePrueba();
        $turno = $this->turnoExistente($this->trabajador, '2026-09-21');

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class, ['turno' => $turno])
            ->set('sedeId', $sede->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($sede->id, $turno->fresh()->sede_id);
    }

    public function test_editar_puede_conservar_su_propia_fecha_pero_no_pisar_otro_turno(): void
    {
        $turno = $this->turnoExistente($this->trabajador, '2026-09-21');
        $this->turnoExistente($this->trabajador, '2026-09-22');

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class, ['turno' => $turno])
            ->set('horaInicio', '08:00')
            ->set('horaFin', '16:00')
            ->call('guardar')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin)
            ->test(TurnoForm::class, ['turno' => $turno->fresh()])
            ->set('fecha', '2026-09-22')
            ->set('horaInicio', '08:00')
            ->set('horaFin', '16:00')
            ->call('guardar')
            ->assertHasErrors(['userId' => 'unique']);

        $this->assertSame('2026-09-21', $turno->fresh()->fecha->toDateString());
    }

    public function test_eliminar_borra_el_turno(): void
    {
        $turno = $this->turnoExistente($this->trabajador, '2026-09-21');

        Livewire::actingAs($this->admin)
            ->test(TurnoIndex::class)
            ->call('eliminar', $turno->id);

        $this->assertModelMissing($turno);
    }

    public function test_el_listado_se_puede_filtrar_por_trabajador(): void
    {
        $otro = $this->usuarioDePrueba();
        $deTrabajador = $this->turnoExistente($this->trabajador, '2026-09-21');
        $deOtro = $this->turnoExistente($otro, '2026-09-21');

        Livewire::actingAs($this->admin)
            ->test(TurnoIndex::class)
            ->assertViewHas('turnos', fn ($turnos) => $turnos->total() === 2)
            ->set('userId', $this->trabajador->id)
            ->assertViewHas('turnos', fn ($turnos) => collect($turnos->items())->pluck('id')->all() === [$deTrabajador->id])
            ->set('userId', $otro->id)
            ->assertViewHas('turnos', fn ($turnos) => collect($turnos->items())->pluck('id')->all() === [$deOtro->id]);
    }

    public function test_quien_no_es_admin_no_puede_guardar_ni_eliminar(): void
    {
        $turno = $this->turnoExistente($this->trabajador, '2026-09-21');

        Livewire::actingAs($this->trabajador)
            ->test(TurnoIndex::class)
            ->call('eliminar', $turno->id)
            ->assertForbidden();

        Livewire::actingAs($this->trabajador)
            ->test(TurnoForm::class)
            ->set('userId', $this->trabajador->id)
            ->set('fecha', '2026-09-30')
            ->set('horaInicio', '07:00')
            ->set('horaFin', '15:00')
            ->call('guardar')
            ->assertForbidden();

        $this->assertModelExists($turno);
        $this->assertSame(1, Turno::count());
    }
}
