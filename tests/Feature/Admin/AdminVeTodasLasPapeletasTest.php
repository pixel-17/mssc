<?php

namespace Tests\Feature\Admin;

use App\Livewire\Papeletas\AdminPapeletasIndex;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El admin ve papeletas en solo lectura: las de un día (por defecto hoy)
 * o el historial completo de un trabajador.
 */
class AdminVeTodasLasPapeletasTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_por_defecto_solo_muestra_las_papeletas_de_hoy(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $hoy = now()->toDateString();
        $ayer = now()->subDay()->toDateString();

        $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'Hoy', 'apellido' => 'Visible']), PendienteJefe::class, ['dia_operativo' => $hoy]);
        $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'Ayer', 'apellido' => 'Oculto']), Cerrada::class, ['dia_operativo' => $ayer]);

        Livewire::actingAs($admin)
            ->test(AdminPapeletasIndex::class)
            ->assertSet('fecha', $hoy)
            ->assertSee('Visible')
            ->assertDontSee('Oculto');
    }

    public function test_al_elegir_un_trabajador_se_ve_todo_su_historial_de_todas_las_fechas(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $trabajador = $this->usuarioDePrueba(['name' => 'Pedro', 'apellido' => 'Historial']);
        $hoy = now()->toDateString();
        $hace30 = now()->subDays(30)->toDateString();

        $this->papeletaDePrueba($trabajador, PendienteRrhh::class, ['dia_operativo' => $hoy]);
        $this->papeletaDePrueba($trabajador, Cerrada::class, ['dia_operativo' => $hace30]);

        Livewire::actingAs($admin)
            ->test(AdminPapeletasIndex::class)
            ->call('verHistorial', $trabajador->id)
            ->assertSee('Historial de Pedro Historial')
            ->assertViewHas('papeletas', fn ($papeletas) => $papeletas->total() === 2);
    }

    public function test_un_trabajador_no_puede_abrir_el_listado_del_admin(): void
    {
        $trabajador = $this->usuarioDePrueba([], ['trabajador']);

        $this->actingAs($trabajador)->get(route('admin.papeletas.index'))->assertForbidden();
    }

    public function test_rrhh_tambien_puede_abrir_el_listado_por_dia_y_por_mes(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $this->actingAs($rrhh)->get(route('admin.papeletas.index'))->assertOk();

        Livewire::actingAs($rrhh)
            ->test(AdminPapeletasIndex::class)
            ->assertSet('vista', 'dia')
            ->call('cambiarVista', 'mes')
            ->assertSet('vista', 'mes');
    }

    public function test_ver_por_mes_muestra_las_papeletas_de_todo_el_mes_elegido(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $esteMes = now()->startOfMonth()->addDays(5)->toDateString();
        $mesPasado = now()->subMonthNoOverflow()->toDateString();

        $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'EsteMes', 'apellido' => 'Visible']), PendienteJefe::class, ['dia_operativo' => $esteMes]);
        $this->papeletaDePrueba($this->usuarioDePrueba(['name' => 'MesPasado', 'apellido' => 'Oculto']), Cerrada::class, ['dia_operativo' => $mesPasado]);

        Livewire::actingAs($admin)
            ->test(AdminPapeletasIndex::class)
            ->call('cambiarVista', 'mes')
            ->assertSet('mes', now()->format('Y-m'))
            ->assertSee('Visible')
            ->assertDontSee('Oculto');
    }
}
