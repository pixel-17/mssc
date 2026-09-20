<?php

namespace Tests\Unit;

use App\Support\Minutos;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MinutosTest extends TestCase
{
    public function test_devuelve_minutos_completos_y_descarta_los_segundos(): void
    {
        $salida = Carbon::parse('2026-09-21 10:00:00');

        $this->assertSame(12, Minutos::entre($salida, Carbon::parse('2026-09-21 10:12:59')));
        $this->assertSame(0, Minutos::entre($salida, Carbon::parse('2026-09-21 10:00:59')));
        $this->assertSame(60, Minutos::entre($salida, Carbon::parse('2026-09-21 11:00:00')));
    }

    public function test_nunca_devuelve_negativos(): void
    {
        $this->assertSame(0, Minutos::entre(Carbon::parse('2026-09-21 10:30:00'), Carbon::parse('2026-09-21 10:00:00')));
    }

    public function test_cruza_la_medianoche(): void
    {
        $this->assertSame(150, Minutos::entre(Carbon::parse('2026-09-21 23:00:00'), Carbon::parse('2026-09-22 01:30:45')));
    }

    public function test_devuelve_siempre_un_entero(): void
    {
        $this->assertIsInt(Minutos::entre(Carbon::parse('2026-09-21 10:00:00'), Carbon::parse('2026-09-21 10:00:30')));
    }
}
