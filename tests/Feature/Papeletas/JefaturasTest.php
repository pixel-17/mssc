<?php

namespace Tests\Feature\Papeletas;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Propagación de cambios de jefe por el organigrama y comando de backfill.
 *
 *   Concejo (alcalde)  ->  Gerencia (gerente)  ->  Oficina (jefeOficina)
 */
class JefaturasTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $alcalde;

    private User $gerente;

    private User $jefeOficina;

    private UnidadOrganica $concejo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->alcalde = $this->usuarioDePrueba();
        $this->gerente = $this->usuarioDePrueba();
        $this->jefeOficina = $this->usuarioDePrueba();

        $this->concejo = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $this->alcalde->id]);
        $gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'parent_id' => $this->concejo->id, 'jefe_id' => $this->gerente->id]);
        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $gerencia->id, 'jefe_id' => $this->jefeOficina->id]);

        $this->alcalde->update(['unidad_organica_id' => $this->concejo->id]);
        $this->gerente->update(['unidad_organica_id' => $gerencia->id]);
        $this->jefeOficina->update(['unidad_organica_id' => $oficina->id]);
    }

    public function test_cambiar_el_jefe_de_la_unidad_abuela_actualiza_al_jefe_de_area_del_jefe_nieto(): void
    {
        $nuevoAlcalde = $this->usuarioDePrueba();

        // jefeOficina está DOS niveles debajo del Concejo: el observer debe llegar hasta él.
        $this->concejo->update(['jefe_id' => $nuevoAlcalde->id]);

        $this->assertSame($nuevoAlcalde->id, $this->jefeOficina->fresh()->jefe_area_id);
        $this->assertSame($nuevoAlcalde->id, $this->gerente->fresh()->jefe_inmediato_id);
    }

    public function test_el_jefe_saliente_deja_de_figurar_como_su_propio_jefe(): void
    {
        $nuevoAlcalde = $this->usuarioDePrueba();

        $this->concejo->update(['jefe_id' => $nuevoAlcalde->id]);

        // El ex-alcalde sigue siendo miembro del Concejo, ahora como miembro común.
        $this->assertSame($nuevoAlcalde->id, $this->alcalde->fresh()->jefe_inmediato_id);
    }

    public function test_el_comando_corrige_datos_guardados_antes_del_arreglo(): void
    {
        DB::table('users')->where('id', $this->jefeOficina->id)->update([
            'jefe_inmediato_id' => $this->jefeOficina->id,
            'jefe_area_id' => $this->gerente->id,
        ]);

        $this->artisan('jefaturas:recalcular', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame($this->jefeOficina->id, $this->jefeOficina->fresh()->jefe_inmediato_id, 'dry-run no debe escribir');

        $this->artisan('jefaturas:recalcular')->assertSuccessful();

        $corregido = $this->jefeOficina->fresh();
        $this->assertSame($this->gerente->id, $corregido->jefe_inmediato_id);
        $this->assertSame($this->alcalde->id, $corregido->jefe_area_id);
    }

    public function test_con_papeletas_abiertas_solo_corrige_las_no_terminales(): void
    {
        DB::table('users')->where('id', $this->jefeOficina->id)->update(['jefe_inmediato_id' => $this->jefeOficina->id]);

        $abierta = $this->papeletaDePrueba($this->jefeOficina, PendienteJefe::class, [
            'jefe_inmediato_id' => $this->jefeOficina->id,
        ]);
        $cerrada = $this->papeletaDePrueba($this->jefeOficina, Cerrada::class, [
            'jefe_inmediato_id' => $this->jefeOficina->id,
        ]);

        $this->artisan('jefaturas:recalcular', ['--papeletas-abiertas' => true])->assertSuccessful();

        $this->assertSame($this->gerente->id, $abierta->fresh()->jefe_inmediato_id);
        $this->assertSame($this->jefeOficina->id, $cerrada->fresh()->jefe_inmediato_id, 'el historial cerrado no se reescribe');
    }
}
