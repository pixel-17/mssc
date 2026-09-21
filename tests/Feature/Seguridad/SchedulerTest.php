<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ->withSchedule() (bootstrap/app.php) solo se registra cuando el
        // Kernel de consola arranca de verdad (Artisan::starting); en un
        // test normal app(Schedule::class) queda vacío porque nunca se
        // llega a ejecutar un comando real. schedule:list no hace nada
        // (solo lista lo programado), pero dispara ese arranque y deja
        // el Schedule poblado para el resto del test. Bug conocido:
        // https://github.com/laravel/framework/issues/51369
        $this->artisan('schedule:list');
    }

    public function test_ninguna_tarea_programada_deja_un_candado_de_24_horas(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'papeletas:') || str_contains((string) $e->command, 'turnos:'));

        // Emergencia se eliminó del sistema (ver migración
        // quitar_emergencia_y_exclusividad y CrearPapeletaTest): quedan
        // 4 tareas reales, no 5. La 5ta era
        // papeletas:procesar-subsanacion-emergencia-vencida, que se borró
        // junto con el resto del código huérfano de Emergencia.
        $this->assertGreaterThanOrEqual(4, $eventos->count(), 'se esperaban las 4 tareas de papeletas y turnos');

        foreach ($eventos as $evento) {
            $this->assertTrue($evento->withoutOverlapping, "{$evento->command} debe usar withoutOverlapping");
            $this->assertLessThan(1440, $evento->expiresAt, "{$evento->command}: un proceso caído bloquearía la tarea 24 h");
        }
    }

    public function test_la_tarea_de_cada_minuto_se_recupera_en_pocos_minutos(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'papeletas:procesar-vencimientos'));

        $this->assertNotNull($evento);
        $this->assertLessThanOrEqual(10, $evento->expiresAt);
    }
}