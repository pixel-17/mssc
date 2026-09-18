<?php

namespace Tests\Unit;

use App\Services\ProgramacionTurnoService;
use PHPUnit\Framework\TestCase;

class AdvertenciasProgramacionTest extends TestCase
{
    private array $horas = [
        'MANANA' => ['06:00', '14:00'],
        'TARDE' => ['14:00', '22:00'],
        'NOCHE' => ['22:00', '06:00'],
    ];

    /** @param  array<int, string>  $codigos */
    private function mes(array $codigos): array
    {
        $dias = [];
        foreach ($codigos as $i => $codigo) {
            $dias[sprintf('2026-10-%02d', $i + 1)] = $codigo;
        }

        return $dias;
    }

    public function test_rotacion_sana_no_genera_advertencias(): void
    {
        $dias = $this->mes(['MANANA', 'MANANA', 'TARDE', 'TARDE', 'NOCHE', 'NOCHE', 'DESCANSO']);

        $this->assertSame([], ProgramacionTurnoService::calcularAdvertencias($dias, $this->horas));
    }

    public function test_noche_seguida_de_manana_deja_cero_horas(): void
    {
        $r = ProgramacionTurnoService::calcularAdvertencias($this->mes(['NOCHE', 'MANANA']), $this->horas);

        $this->assertCount(1, $r);
        $this->assertStringContainsString('0 h', $r[0]);
    }

    public function test_tarde_seguida_de_manana_deja_ocho_horas(): void
    {
        $r = ProgramacionTurnoService::calcularAdvertencias($this->mes(['TARDE', 'MANANA']), $this->horas);

        $this->assertCount(1, $r);
        $this->assertStringContainsString('8 h', $r[0]);
    }

    public function test_racha_de_mas_de_seis_dias_avisa_una_sola_vez(): void
    {
        $r = ProgramacionTurnoService::calcularAdvertencias($this->mes(array_fill(0, 9, 'MANANA')), $this->horas);

        $this->assertCount(1, $r);
        $this->assertStringContainsString('Más de 6 días', $r[0]);
    }

    public function test_ciclo_6x1_no_avisa(): void
    {
        $codigos = [...array_fill(0, 6, 'MANANA'), 'DESCANSO', ...array_fill(0, 6, 'MANANA')];

        $this->assertSame([], ProgramacionTurnoService::calcularAdvertencias($this->mes($codigos), $this->horas));
    }

    public function test_un_dia_sin_programar_corta_la_comparacion(): void
    {
        $dias = ['2026-10-01' => 'NOCHE', '2026-10-03' => 'MANANA'];

        $this->assertSame([], ProgramacionTurnoService::calcularAdvertencias($dias, $this->horas));
    }
}
