<?php

namespace Tests\Feature\Usuarios;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Formulario «Crear usuario» (jefe de área). Solo cubre lo que se ve:
 * las reglas de sede y turno las prueban TurnoInicialAlCrearTest y
 * JefesPorRegimenTest contra CrearUsuarioAction.
 */
class FormularioCrearUsuarioTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefeDeArea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        // Jefe de área = trabajador que encabeza la unidad. Un admin no sirve aquí:
        // UserPolicy::puedeCrearAlgo() lo excluye a propósito (sus altas van por Cuentas).
        $this->jefeDeArea = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefeDeArea->id]);
        $this->conUnidadHija($unidad);
    }

    public function test_el_formulario_muestra_la_sede_que_heredara_el_trabajador(): void
    {
        $this->actingAs($this->jefeDeArea)
            ->get(route('usuarios.crear'))
            ->assertOk()
            ->assertSee('Sede del trabajador')
            // La sede del jefe de la unidad viaja al formulario para mostrarse en vivo.
            ->assertSee('Sede central');
    }

    public function test_el_alta_no_pide_turno_porque_el_trabajador_lo_programa_despues(): void
    {
        $this->actingAs($this->jefeDeArea)
            ->get(route('usuarios.crear'))
            ->assertOk()
            ->assertDontSee('name="turno"', false)
            ->assertSee('se carga después', false);
    }
}
