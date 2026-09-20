<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    public function test_ninguna_tarea_programada_deja_un_candado_de_24_horas(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'papeletas:') || str_contains((string) $e->command, 'turnos:'));

        $this->assertGreaterThanOrEqual(5, $eventos->count(), 'se esperaban las 5 tareas de papeletas y turnos');

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
