<?php

namespace Tests\Feature\Papeletas;

use App\Livewire\Papeletas\RrhhIndex;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El campo "Buscar trabajador" de la bandeja de RRHH filtra las tres
 * listas (por decidir, post-hoc, sustentos) por nombre/apellido del
 * trabajador. Aquí solo se cubre "por decidir": las otras dos listas
 * comparten el mismo closure de filtro en RrhhIndex::render().
 */
class RrhhIndexBusquedaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_filtra_por_decidir_por_nombre_o_apellido_del_trabajador(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $maria = $this->usuarioDePrueba(['name' => 'María', 'apellido' => 'Quispe']);
        $jose = $this->usuarioDePrueba(['name' => 'José', 'apellido' => 'Fernández']);

        $this->papeletaDePrueba($maria, PendienteRrhh::class);
        $this->papeletaDePrueba($jose, PendienteRrhh::class);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->assertSee('María')
            ->assertSee('José')
            ->set('buscar', 'Quispe')
            ->assertSee('María')
            ->assertDontSee('José');
    }

    public function test_buscar_vacio_no_filtra_nada(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $maria = $this->usuarioDePrueba(['name' => 'María', 'apellido' => 'Quispe']);
        $this->papeletaDePrueba($maria, PendienteRrhh::class);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->set('buscar', '')
            ->assertSee('María');
    }
}
