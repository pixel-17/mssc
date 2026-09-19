<?php

namespace Tests\Feature;

use App\Events\HorarioActualizado;
use App\Models\CargaTurnoMensual;
use App\Models\Turno;
use App\Models\User;
use App\Services\ProgramacionTurnoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramacionTurnoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['regimen' => '276']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_avisa_en_tiempo_real_al_trabajador_cuando_cambia_su_horario(): void
    {
        Event::fake([HorarioActualizado::class]);

        $trabajador = User::factory()->create(['regimen' => '728', 'activo' => true]);

        app(ProgramacionTurnoService::class)->guardarMes($trabajador, 2026, 10, [
            '2026-10-01' => 'MANANA',
        ], $this->admin());

        Event::assertDispatched(
            HorarioActualizado::class,
            fn (HorarioActualizado $evento) => $evento->userId === $trabajador->id
        );
    }

    public function test_guarda_el_mes_y_lo_marca_como_manual(): void
    {
        $trabajador = User::factory()->create(['regimen' => '728', 'activo' => true]);

        app(ProgramacionTurnoService::class)->guardarMes($trabajador, 2026, 10, [
            '2026-10-01' => 'MANANA',
            '2026-10-02' => 'NOCHE',
            '2026-10-03' => 'DESCANSO',
        ], $this->admin());

        $this->assertSame(3, Turno::where('user_id', $trabajador->id)->count());
        $this->assertSame('NOCHE', Turno::where('user_id', $trabajador->id)->whereDate('fecha', '2026-10-02')->value('turno'));
        $this->assertTrue((bool) Turno::where('user_id', $trabajador->id)->whereDate('fecha', '2026-10-03')->value('es_descanso'));
        $this->assertSame('manual', CargaTurnoMensual::where('user_id', $trabajador->id)->where('anio', 2026)->where('mes', 10)->value('origen'));
    }

    public function test_los_dias_no_enviados_se_borran(): void
    {
        $trabajador = User::factory()->create(['regimen' => '728', 'activo' => true]);
        $servicio = app(ProgramacionTurnoService::class);
        $admin = $this->admin();

        $servicio->guardarMes($trabajador, 2026, 10, ['2026-10-01' => 'MANANA', '2026-10-02' => 'TARDE'], $admin);
        $servicio->guardarMes($trabajador, 2026, 10, ['2026-10-01' => 'TARDE'], $admin);

        $this->assertSame(1, Turno::where('user_id', $trabajador->id)->count());
        $this->assertSame('TARDE', Turno::where('user_id', $trabajador->id)->value('turno'));
    }

    public function test_rechaza_trabajador_276(): void
    {
        $trabajador = User::factory()->create(['regimen' => '276', 'activo' => true]);

        $this->expectException(ValidationException::class);

        app(ProgramacionTurnoService::class)->guardarMes($trabajador, 2026, 10, ['2026-10-01' => 'MANANA'], $this->admin());
    }

    public function test_rechaza_fechas_de_otro_mes_o_turnos_invalidos(): void
    {
        $trabajador = User::factory()->create(['regimen' => '728', 'activo' => true]);
        $servicio = app(ProgramacionTurnoService::class);
        $admin = $this->admin();

        foreach ([['2026-11-01' => 'MANANA'], ['2026-10-01' => 'DIA'], ['2026-10-32' => 'MANANA']] as $dias) {
            try {
                $servicio->guardarMes($trabajador, 2026, 10, $dias, $admin);
                $this->fail('Debió lanzar ValidationException');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, Turno::count());
    }

    public function test_guardar_equipo_escribe_varios_trabajadores(): void
    {
        $a = User::factory()->create(['regimen' => '728', 'activo' => true]);
        $b = User::factory()->create(['regimen' => '728', 'activo' => true]);

        app(ProgramacionTurnoService::class)->guardarEquipo(
            collect([$a->id => $a, $b->id => $b]),
            2026,
            10,
            [$a->id => ['2026-10-01' => 'MANANA'], $b->id => ['2026-10-01' => 'NOCHE', '2026-10-02' => 'DESCANSO']],
            $this->admin(),
        );

        $this->assertSame(1, Turno::where('user_id', $a->id)->count());
        $this->assertSame(2, Turno::where('user_id', $b->id)->count());
    }

    public function test_guardar_equipo_rechaza_trabajador_fuera_del_equipo_sin_escribir_nada(): void
    {
        $dentro = User::factory()->create(['regimen' => '728', 'activo' => true]);
        $fuera = User::factory()->create(['regimen' => '728', 'activo' => true]);

        try {
            app(ProgramacionTurnoService::class)->guardarEquipo(
                collect([$dentro->id => $dentro]),
                2026,
                10,
                [$dentro->id => ['2026-10-01' => 'MANANA'], $fuera->id => ['2026-10-01' => 'TARDE']],
                $this->admin(),
            );
            $this->fail('Debió lanzar ValidationException');
        } catch (ValidationException) {
            $this->assertSame(0, Turno::count());
        }
    }

    public function test_advertencias_de_equipo_incluyen_el_nombre(): void
    {
        $a = User::factory()->create(['regimen' => '728', 'activo' => true, 'name' => 'Lucía']);

        $r = app(ProgramacionTurnoService::class)->advertenciasEquipo(
            collect([$a->id => $a]),
            [$a->id => ['2026-10-01' => 'NOCHE', '2026-10-02' => 'MANANA']],
        );

        $this->assertCount(1, $r);
        $this->assertStringStartsWith('Lucía', $r[0]);
    }
}
