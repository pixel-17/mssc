<?php

namespace Tests\Unit;

use App\Support\PatronDescansoSemanal;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para PatronDescansoSemanal.
 *
 * No tocan BD ni requieren el contenedor de Laravel — igual que
 * PatronTurnosTest, extienden directamente PHPUnit\Framework\TestCase.
 */
class PatronDescansoSemanalTest extends TestCase
{
    // ── esArcoContiguo ────────────────────────────────────────────────────────

    public function test_sabado_y_domingo_son_arco_contiguo(): void
    {
        $this->assertTrue(PatronDescansoSemanal::esArcoContiguo([6, 7]));
    }

    public function test_domingo_y_lunes_son_arco_contiguo_en_el_limite(): void
    {
        // Domingo(7) y lunes(1) son contiguos en la semana circular.
        $this->assertTrue(PatronDescansoSemanal::esArcoContiguo([1, 7]));
    }

    public function test_martes_y_viernes_no_son_arco_contiguo(): void
    {
        $this->assertFalse(PatronDescansoSemanal::esArcoContiguo([2, 5]));
    }

    public function test_lunes_miercoles_no_son_arco_contiguo(): void
    {
        $this->assertFalse(PatronDescansoSemanal::esArcoContiguo([1, 3]));
    }

    public function test_tres_dias_consecutivos_son_arco_contiguo(): void
    {
        $this->assertTrue(PatronDescansoSemanal::esArcoContiguo([5, 6, 7]));  // jue-vie-sab
        $this->assertTrue(PatronDescansoSemanal::esArcoContiguo([6, 7, 1]));  // sab-dom-lun
    }

    public function test_un_solo_dia_es_arco_contiguo(): void
    {
        $this->assertTrue(PatronDescansoSemanal::esArcoContiguo([3]));
    }

    // ── anclaYCiclo ───────────────────────────────────────────────────────────

    public function test_sabado_y_domingo_producen_ciclo_5_trabajo_2_descanso(): void
    {
        $referencia = Carbon::parse('2026-10-19');  // semana que contiene oct-2026

        $resultado = PatronDescansoSemanal::anclaYCiclo([6, 7], $referencia);

        $this->assertNotNull($resultado);
        $this->assertSame(5, $resultado['dias_trabajo']);
        $this->assertSame(2, $resultado['dias_descanso']);
    }

    public function test_ancla_resultante_es_un_lunes(): void
    {
        // Con sábado+domingo de descanso, el primer día de trabajo es lunes.
        $referencia = Carbon::parse('2026-10-19');

        $resultado = PatronDescansoSemanal::anclaYCiclo([6, 7], $referencia);

        $this->assertNotNull($resultado);
        $this->assertSame(Carbon::MONDAY, $resultado['fecha_ancla']->dayOfWeek);
    }

    public function test_con_solo_domingo_de_descanso_ciclo_es_6_1(): void
    {
        $referencia = Carbon::parse('2026-10-19');

        $resultado = PatronDescansoSemanal::anclaYCiclo([7], $referencia);

        $this->assertNotNull($resultado);
        $this->assertSame(6, $resultado['dias_trabajo']);
        $this->assertSame(1, $resultado['dias_descanso']);
    }

    public function test_seleccion_vacia_devuelve_null(): void
    {
        $this->assertNull(PatronDescansoSemanal::anclaYCiclo([], Carbon::now()));
    }

    public function test_los_7_dias_devuelve_null(): void
    {
        $this->assertNull(
            PatronDescansoSemanal::anclaYCiclo([1, 2, 3, 4, 5, 6, 7], Carbon::now())
        );
    }

    public function test_dias_no_contiguos_devuelven_null(): void
    {
        // Martes y viernes — no contiguos.
        $this->assertNull(PatronDescansoSemanal::anclaYCiclo([2, 5], Carbon::now()));
    }

    public function test_duplicados_en_la_entrada_se_ignoran(): void
    {
        // [6, 6, 7] equivale a [6, 7].
        $referencia = Carbon::parse('2026-10-19');

        $resultado = PatronDescansoSemanal::anclaYCiclo([6, 6, 7], $referencia);

        $this->assertNotNull($resultado);
        $this->assertSame(2, $resultado['dias_descanso']);
    }

    /**
     * Verifica que el ancla producida sea coherente con
     * GeneradorTurnoMensualService::esDescansoEnFecha: el primer día de
     * trabajo (lunes, cuando descansa S+D) NO debe ser descanso según el
     * servicio, y el sábado SÍ.
     *
     * Usamos la cuenta modular directamente (sin instanciar el servicio de
     * Laravel) para que el test siga siendo unitario puro.
     */
    public function test_ancla_es_coherente_con_la_cuenta_modular_del_generador(): void
    {
        $referencia = Carbon::parse('2026-10-19');  // lunes 19 oct 2026
        $resultado = PatronDescansoSemanal::anclaYCiclo([6, 7], $referencia);

        $this->assertNotNull($resultado);

        $ancla = $resultado['fecha_ancla'];
        $diasTrabajo = $resultado['dias_trabajo'];  // 5
        $diasDescanso = $resultado['dias_descanso']; // 2
        $ciclo = $diasTrabajo + $diasDescanso; // 7

        // El lunes de esa misma semana debe ser día de trabajo.
        $lunes = Carbon::parse('2026-10-19');
        $diffLunes = $ancla->diffInDays($lunes, false);
        $posLunes = (($diffLunes % $ciclo) + $ciclo) % $ciclo;
        $this->assertLessThan($diasTrabajo, $posLunes, 'El lunes debe ser día de trabajo');

        // El sábado de esa misma semana debe ser descanso.
        $sabado = Carbon::parse('2026-10-24');
        $diffSab = $ancla->diffInDays($sabado, false);
        $posSab = (($diffSab % $ciclo) + $ciclo) % $ciclo;
        $this->assertGreaterThanOrEqual($diasTrabajo, $posSab, 'El sábado debe ser descanso');
    }
}
