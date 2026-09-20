<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\AprobarJefeAction;
use App\Actions\Papeleta\AprobarRrhhAction;
use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Nadie decide su propia papeleta.
 *
 * Organigrama (cada jefe es además miembro de la unidad que encabeza):
 *
 *   Concejo (alcalde)
 *   └── Gerencia (gerente)
 *       └── Oficina (jefeOficina)
 *           └── trabajador
 */
class AutoDecisionTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $alcalde;

    private User $gerente;

    private User $jefeOficina;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->alcalde = $this->usuarioDePrueba();
        $this->gerente = $this->usuarioDePrueba();
        $this->jefeOficina = $this->usuarioDePrueba();
        $this->trabajador = $this->usuarioDePrueba();

        $concejo = UnidadOrganica::create(['nombre' => 'Concejo', 'jefe_id' => $this->alcalde->id]);
        $gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'parent_id' => $concejo->id, 'jefe_id' => $this->gerente->id]);
        $oficina = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $gerencia->id, 'jefe_id' => $this->jefeOficina->id]);

        $this->alcalde->update(['unidad_organica_id' => $concejo->id]);
        $this->gerente->update(['unidad_organica_id' => $gerencia->id]);
        $this->jefeOficina->update(['unidad_organica_id' => $oficina->id]);
        $this->trabajador->update(['unidad_organica_id' => $oficina->id]);

        foreach (['alcalde', 'gerente', 'jefeOficina', 'trabajador'] as $propiedad) {
            $this->{$propiedad} = $this->{$propiedad}->fresh();
        }
    }

    public function test_el_jefe_de_una_unidad_sube_un_nivel_y_no_es_su_propio_jefe(): void
    {
        $this->assertSame($this->gerente->id, $this->jefeOficina->jefe_inmediato_id);
        $this->assertSame($this->alcalde->id, $this->jefeOficina->jefe_area_id);

        $this->assertSame($this->alcalde->id, $this->gerente->jefe_inmediato_id);
        $this->assertNull($this->gerente->jefe_area_id);

        // La unidad raíz no tiene superior: no queda apuntando a sí mismo.
        $this->assertNull($this->alcalde->jefe_inmediato_id);
        $this->assertNull($this->alcalde->jefe_area_id);

        // Un miembro común sigue igual que siempre.
        $this->assertSame($this->jefeOficina->id, $this->trabajador->jefe_inmediato_id);
        $this->assertSame($this->gerente->id, $this->trabajador->jefe_area_id);
    }

    public function test_la_papeleta_del_jefe_fotografia_a_su_superior_y_no_a_si_mismo(): void
    {
        $papeleta = app(CrearPapeletaAction::class)->ejecutar(
            $this->jefeOficina,
            $this->motivoDe(), // 728 sin MODO_ESTRICTO_728: no depende del horario ni del turno
            ['justificacion' => 'Urgencia familiar'],
        );

        $this->assertSame($this->gerente->id, $papeleta->jefe_inmediato_id);
        $this->assertSame($this->alcalde->id, $papeleta->jefe_area_id);
    }

    public function test_un_jefe_no_puede_aprobar_su_propia_papeleta_ni_con_datos_viejos(): void
    {
        // Datos anteriores al arreglo: el jefe figuraba como su propio jefe inmediato.
        $papeleta = $this->papeletaDePrueba($this->jefeOficina, PendienteJefe::class, [
            'jefe_inmediato_id' => $this->jefeOficina->id,
        ]);

        $this->assertFalse(Gate::forUser($this->jefeOficina)->allows('decidirComoJefe', $papeleta));

        $this->assertThrows(
            fn () => app(AprobarJefeAction::class)->ejecutar($papeleta, $this->jefeOficina),
            PapeletaException::class,
            'No puedes decidir, revisar ni cerrar tu propia papeleta.',
        );

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_el_jefe_superior_si_puede_decidir_la_papeleta_de_su_subordinado(): void
    {
        $papeleta = $this->papeletaDePrueba($this->jefeOficina);

        $this->assertTrue(Gate::forUser($this->gerente)->allows('decidirComoJefe', $papeleta));
    }

    public function test_rrhh_que_tambien_es_trabajador_no_decide_su_propia_papeleta(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh', 'trabajador']);
        $otroRrhh = $this->usuarioDePrueba([], ['rrhh']);
        $papeleta = $this->papeletaDePrueba($rrhh, PendienteRrhh::class);

        $this->assertFalse(Gate::forUser($rrhh)->allows('decidirComoRrhh', $papeleta));
        $this->assertFalse(Gate::forUser($rrhh)->allows('revisarPosthoc', $papeleta));
        $this->assertTrue(Gate::forUser($otroRrhh)->allows('decidirComoRrhh', $papeleta));

        $this->assertThrows(
            fn () => app(AprobarRrhhAction::class)->ejecutar($papeleta, $rrhh),
            PapeletaException::class,
            'No puedes decidir, revisar ni cerrar tu propia papeleta.',
        );

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));
    }
}
