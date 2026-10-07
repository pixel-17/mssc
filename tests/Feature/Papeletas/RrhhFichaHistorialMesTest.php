<?php

namespace Tests\Feature\Papeletas;

use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * La ficha de RRHH muestra, junto a la decisión, el resumen del mes del
 * trabajador (sin contar la papeleta que se está mirando).
 */
class RrhhFichaHistorialMesTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_la_ficha_muestra_el_resumen_del_mes_sin_contar_la_papeleta_actual(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $maria = $this->usuarioDePrueba(['name' => 'María', 'apellido' => 'Quispe']);

        $actual = $this->papeletaDePrueba($maria, PendienteRrhh::class);
        $this->papeletaDePrueba($maria, Rechazada::class);
        $this->papeletaDePrueba($maria, PendienteRrhh::class, ['contador_observaciones_jefe' => 2]);

        $this->actingAs($rrhh)
            ->get(route('rrhh.papeletas.show', $actual))
            ->assertOk()
            ->assertSee('Historial de')
            ->assertSeeInOrder(['Otras papeletas', '2', 'Rechazadas', '1', 'Observaciones', '2']);
    }

    public function test_la_ficha_no_cuenta_papeletas_de_otro_mes(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $maria = $this->usuarioDePrueba(['name' => 'María', 'apellido' => 'Quispe']);

        $actual = $this->papeletaDePrueba($maria, PendienteRrhh::class);
        $this->papeletaDePrueba($maria, Rechazada::class, [
            'dia_operativo' => now()->subMonths(2)->toDateString(),
        ]);

        $this->actingAs($rrhh)
            ->get(route('rrhh.papeletas.show', $actual))
            ->assertOk()
            ->assertSeeInOrder(['Otras papeletas', '0', 'Rechazadas', '0']);
    }
}
