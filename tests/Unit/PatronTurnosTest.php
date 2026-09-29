<?php

namespace Tests\Unit;

use App\Support\PatronTurnos;
use PHPUnit\Framework\TestCase;

class PatronTurnosTest extends TestCase
{
    public function test_parsea_siglas_con_espacios_y_comas_sin_importar_mayusculas(): void
    {
        $this->assertSame(
            ['MANANA', 'MANANA', 'TARDE', 'TARDE', 'NOCHE', 'DESCANSO'],
            PatronTurnos::parsear('M M t,t n d')
        );
    }

    public function test_rechaza_patron_vacio_o_con_siglas_invalidas(): void
    {
        $this->assertNull(PatronTurnos::parsear('   '));
        $this->assertNull(PatronTurnos::parsear('M X D'));
    }

    public function test_los_predefinidos_son_validos(): void
    {
        foreach (PatronTurnos::PREDEFINIDOS as $patron) {
            $this->assertNotNull(PatronTurnos::parsear($patron), $patron);
        }
    }

    public function test_genera_repitiendo_el_patron_desde_el_offset(): void
    {
        $pasos = PatronTurnos::parsear('M T D');

        $this->assertSame([
            '2026-10-30' => 'TARDE',
            '2026-10-31' => 'DESCANSO',
            '2026-11-01' => 'MANANA',
            '2026-11-02' => 'TARDE',
        ], PatronTurnos::generar($pasos, '2026-10-30', '2026-11-02', 1));
    }

    public function test_continua_la_rotacion_donde_termino_el_mes_anterior(): void
    {
        $pasos = PatronTurnos::parsear('M M T T N D');

        // Terminó en T (2.ª T) tras M M T T: el siguiente paso es N.
        $offset = PatronTurnos::offsetContinuacion($pasos, ['MANANA', 'MANANA', 'TARDE', 'TARDE']);
        $this->assertSame(4, $offset);
        $this->assertSame('NOCHE', $pasos[$offset]);
    }

    public function test_al_terminar_el_patron_vuelve_al_inicio(): void
    {
        $pasos = PatronTurnos::parsear('M M T T N D');

        $this->assertSame(0, PatronTurnos::offsetContinuacion($pasos, ['TARDE', 'NOCHE', 'DESCANSO']));
    }

    public function test_sin_dias_previos_o_sin_coincidencia_arranca_desde_el_principio(): void
    {
        $pasos = PatronTurnos::parsear('M M T T N D');

        $this->assertSame(0, PatronTurnos::offsetContinuacion($pasos, []));
        $this->assertSame(0, PatronTurnos::offsetContinuacion($pasos, [null, null]));
        $this->assertSame(0, PatronTurnos::offsetContinuacion(PatronTurnos::parsear('M D'), ['NOCHE']));
    }
}
