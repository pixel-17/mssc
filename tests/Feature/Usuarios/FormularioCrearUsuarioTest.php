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

        // Mismo atajo que TurnoInicialAlCrearTest: un admin dueño de la unidad hace de jefe de área.
        $this->jefeDeArea = $this->usuarioDePrueba([], ['admin']);
        UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefeDeArea->id]);
    }

    public function test_el_formulario_muestra_la_sede_que_heredara_el_trabajador(): void
    {
        $this->actingAs($this->jefeDeArea)
            ->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('Sede del trabajador')
            // La sede del jefe de la unidad viaja al formulario para mostrarse en vivo.
            ->assertSee('Sede central');
    }

    public function test_el_turno_se_ofrece_con_tilde_pero_el_valor_enviado_no_cambia(): void
    {
        $this->actingAs($this->jefeDeArea)
            ->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('<option value="MANANA"', false)
            ->assertSee('>Mañana</option>', false);
    }
}
