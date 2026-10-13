<?php

namespace Tests\Feature\Usuarios;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Hay UN solo jefe inmediato por trabajador: el jefe de su unidad
 * (users.jefe_inmediato_id, derivado de unidades_organicas.jefe_id). Ese
 * jefe tiene las mismas capacidades sobre él sin importar su régimen.
 */
class JefeInmediatoUnicoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefe;

    private User $ajeno;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefe = $this->usuarioDePrueba();
        $this->ajeno = $this->usuarioDePrueba();

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefe->id]);

        $this->trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $this->trabajador->update(['unidad_organica_id' => $unidad->id]);
        $this->trabajador = $this->trabajador->fresh();
    }

    public function test_el_jefe_de_la_unidad_es_el_jefe_inmediato_del_trabajador(): void
    {
        $this->assertSame($this->jefe->id, $this->trabajador->jefe_inmediato_id);
        $this->assertTrue($this->jefe->esJefeInmediatoDe($this->trabajador));
        $this->assertFalse($this->ajeno->esJefeInmediatoDe($this->trabajador));
    }

    public function test_el_jefe_inmediato_ve_y_edita_al_trabajador(): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->view($this->jefe, $this->trabajador));
        $this->assertTrue($policy->editar($this->jefe, $this->trabajador));

        $this->assertFalse($policy->view($this->ajeno, $this->trabajador));
        $this->assertFalse($policy->editar($this->ajeno, $this->trabajador));
    }

    public function test_el_jefe_inmediato_puede_gestionar_el_turno_del_trabajador(): void
    {
        $this->assertTrue($this->jefe->puedeGestionarTurnoDe($this->trabajador));
        $this->assertFalse($this->ajeno->puedeGestionarTurnoDe($this->trabajador));
    }

    public function test_jefes_inmediatos_lista_solo_al_jefe_de_la_unidad(): void
    {
        $this->assertSame(
            [$this->jefe->id],
            $this->trabajador->jefesInmediatos()->pluck('id')->all(),
        );
    }

    public function test_quien_no_encabeza_unidad_no_es_jefe_de_nadie_por_tener_turno(): void
    {
        // Antes un "jefe de turno" podía ser jefe de un trabajador sin ser
        // el jefe de la unidad. Ahora solo cuenta jefe_inmediato_id.
        $otro = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::find($this->trabajador->unidad_organica_id);
        $unidad->update(['jefe_id' => null]);

        $this->assertNull($this->trabajador->fresh()->jefe_inmediato_id);
        $this->assertFalse($otro->esJefeInmediatoDe($this->trabajador->fresh()));
        $this->assertFalse($this->jefe->fresh()->esJefeInmediatoDe($this->trabajador->fresh()));
    }
}
