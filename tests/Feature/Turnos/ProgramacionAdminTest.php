<?php

namespace Tests\Feature\Turnos;

use App\Livewire\Turnos\CalendarioEquipoIndex;
use App\Livewire\Turnos\ProgramacionAdmin;
use App\Models\UnidadOrganica;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Programación para Admin: se busca a la persona, se ve si es
 * trabajador o jefe y, si es jefe, el admin programa a su equipo desde
 * la misma grilla que usa el jefe (CalendarioEquipoIndex con $jefeId).
 */
class ProgramacionAdminTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    public function test_solo_el_admin_puede_entrar_al_buscador(): void
    {
        $trabajador = $this->usuarioDePrueba([], ['trabajador']);

        Livewire::actingAs($trabajador)
            ->test(ProgramacionAdmin::class)
            ->assertForbidden();
    }

    public function test_la_busqueda_indica_si_es_trabajador_o_jefe(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $jefe = $this->usuarioDePrueba(['name' => 'Zoraida', 'apellido' => 'Quispe'], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $jefe->update(['unidad_organica_id' => $unidad->id]);

        $this->usuarioDePrueba([
            'name' => 'Zacarias',
            'apellido' => 'Mamani',
            'unidad_organica_id' => $unidad->id,
            'jefe_inmediato_id' => $jefe->id,
        ], ['trabajador']);

        Livewire::actingAs($admin)
            ->test(ProgramacionAdmin::class)
            ->set('buscar', 'Zoraida Quispe')
            ->assertSee('Jefe de área')
            ->assertSee('Programar su equipo')
            ->assertDontSee('Zacarias')
            ->set('buscar', 'Zacarias')
            ->assertSee('Trabajador')
            ->assertDontSee('Programar su equipo');
    }

    public function test_el_admin_guarda_los_turnos_del_equipo_de_un_jefe(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $jefe->update(['unidad_organica_id' => $unidad->id]);

        $trabajador = $this->usuarioDePrueba([
            'unidad_organica_id' => $unidad->id,
            'regimen' => '728',
            'activo' => true,
        ], ['trabajador']);

        Livewire::actingAs($admin)
            ->test(CalendarioEquipoIndex::class, ['jefeId' => $jefe->id])
            ->call('guardar', [
                (string) $trabajador->id => ['2026-10-01' => 'TARDE'],
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('turnos', [
            'user_id' => $trabajador->id,
            'fecha' => '2026-10-01',
            'turno' => 'TARDE',
        ]);
    }

    public function test_un_no_admin_no_puede_ver_el_equipo_de_otro_jefe(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $otro = $this->usuarioDePrueba([], ['trabajador']);

        Livewire::actingAs($jefe)
            ->test(CalendarioEquipoIndex::class, ['jefeId' => $otro->id])
            ->assertForbidden();
    }
}
