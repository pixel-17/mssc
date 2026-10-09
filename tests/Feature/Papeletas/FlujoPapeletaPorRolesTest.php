<?php

namespace Tests\Feature\Papeletas;

use App\Models\Papeleta;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Recorrido HTTP completo con TODOS los roles (trabajador, jefe inmediato,
 * jefe de área, RRHH y admin) sobre la regla: la papeleta solo le llega al
 * jefe inmediato si el jefe tiene turno vigente. Lunes 2026-09-21, 10:00
 * (dentro del horario de RRHH).
 *
 * Organigrama: Área (jefe de área, 276) -> Oficina (jefe inmediato, 728)
 * -> trabajador 276. Más un RRHH, un admin y un trabajador ajeno.
 */
class FlujoPapeletaPorRolesTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $rrhh;

    private User $jefeArea;

    private User $jefeInmediato;

    private User $trabajador;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));

        $this->admin = $this->usuarioDePrueba(['regimen' => '276'], ['admin']);
        $this->rrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->jefeArea = $this->usuarioDePrueba(['regimen' => '276']);
        $this->jefeInmediato = $this->usuarioDePrueba(['regimen' => '728']);
        $this->trabajador = $this->usuarioDePrueba(['regimen' => '276']);
        $this->ajeno = $this->usuarioDePrueba(['regimen' => '276']);

        $area = UnidadOrganica::create(['nombre' => 'Área', 'jefe_id' => $this->jefeArea->id]);
        $oficina = UnidadOrganica::create([
            'nombre' => 'Oficina',
            'parent_id' => $area->id,
            'jefe_id' => $this->jefeInmediato->id,
        ]);

        $this->jefeArea->update(['unidad_organica_id' => $area->id]);
        $this->jefeInmediato->update(['unidad_organica_id' => $oficina->id]);
        $this->trabajador->update(['unidad_organica_id' => $oficina->id]);

        foreach (['jefeArea', 'jefeInmediato', 'trabajador'] as $propiedad) {
            $this->{$propiedad} = $this->{$propiedad}->fresh();
        }
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function solicitar(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->trabajador)->post(route('trabajador.papeletas.store'), [
            'motivo_id' => $this->motivoDe('PARTICULAR')->id,
        ]);
    }

    public function test_sin_turno_en_el_jefe_la_papeleta_no_se_crea_y_ningun_rol_la_ve(): void
    {
        $this->solicitar()->assertSessionHas('error');

        $this->assertSame(0, Papeleta::count());

        foreach ([$this->jefeInmediato, $this->jefeArea, $this->rrhh] as $usuario) {
            $this->assertSame(0, Papeleta::deJefeInmediato($usuario)->count());
        }

        $this->actingAs($this->jefeInmediato)->get(route('jefe.papeletas.index'))->assertOk();
        $this->actingAs($this->rrhh)->get(route('rrhh.papeletas.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.papeletas.index'))->assertOk();
    }

    public function test_con_turno_en_el_jefe_recorre_todo_el_flujo_con_cada_rol(): void
    {
        $this->turnoDePrueba($this->jefeInmediato);

        // 1) Trabajador crea.
        $respuesta = $this->solicitar()->assertSessionDoesntHaveErrors();
        $papeleta = Papeleta::where('trabajador_id', $this->trabajador->id)->firstOrFail();
        $respuesta->assertRedirect(route('trabajador.papeletas.show', $papeleta));
        $this->assertTrue($papeleta->estado->equals(PendienteJefe::class));
        $this->assertSame($this->jefeInmediato->id, $papeleta->jefe_inmediato_id);

        $this->actingAs($this->trabajador)->get(route('trabajador.papeletas.index'))->assertOk();
        $this->actingAs($this->trabajador)->get(route('trabajador.papeletas.show', $papeleta))->assertOk();

        // 2) Solo su jefe inmediato la tiene en bandeja; un ajeno no.
        $this->actingAs($this->jefeInmediato)->get(route('jefe.papeletas.index'))->assertOk()
            ->assertSee($this->trabajador->name);
        $this->actingAs($this->jefeInmediato)->get(route('jefe.papeletas.show', $papeleta))->assertOk();
        $this->actingAs($this->ajeno)->get(route('jefe.papeletas.show', $papeleta))->assertForbidden();
        $this->assertSame(0, Papeleta::deJefeInmediato($this->ajeno)->count());

        // 3) Un ajeno no puede aprobarla.
        $this->actingAs($this->ajeno)->post(route('jefe.papeletas.aprobar', $papeleta))->assertForbidden();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));

        // 4) El jefe inmediato aprueba -> pasa a RRHH (en horario).
        $this->actingAs($this->jefeInmediato)->post(route('jefe.papeletas.aprobar', $papeleta))
            ->assertSessionDoesntHaveErrors();
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteRrhh::class));

        // 5) RRHH la ve y la autoriza.
        $this->actingAs($this->rrhh)->get(route('rrhh.papeletas.index'))->assertOk();
        $this->actingAs($this->rrhh)->get(route('rrhh.papeletas.show', $papeleta))->assertOk();
        $this->actingAs($this->rrhh)->post(route('rrhh.papeletas.aprobar', $papeleta))
            ->assertSessionDoesntHaveErrors();
        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));

        // 6) Admin: solo lectura.
        $this->actingAs($this->admin)->get(route('admin.papeletas.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.papeletas.show', $papeleta))->assertOk();
        $this->actingAs($this->admin)->post(route('jefe.papeletas.aprobar', $papeleta))->assertForbidden();
    }

    public function test_los_roles_no_se_pisan_las_rutas(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador);

        // RRHH y admin no son "trabajador": no tienen pantalla de crear.
        $this->actingAs($this->rrhh)->get(route('trabajador.papeletas.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('trabajador.papeletas.index'))->assertForbidden();

        // Trabajador y jefe no entran a RRHH ni a admin.
        $this->actingAs($this->trabajador)->get(route('rrhh.papeletas.index'))->assertForbidden();
        $this->actingAs($this->jefeInmediato)->get(route('rrhh.papeletas.index'))->assertForbidden();
        $this->actingAs($this->jefeInmediato)->get(route('admin.papeletas.index'))->assertForbidden();
        $this->actingAs($this->jefeArea)->get(route('admin.papeletas.show', $papeleta))->assertForbidden();

        // Admin y RRHH no aprueban como RRHH/jefe respectivamente.
        $this->actingAs($this->admin)->post(route('rrhh.papeletas.aprobar', $papeleta))->assertForbidden();
    }
}
