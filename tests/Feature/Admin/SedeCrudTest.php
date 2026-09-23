<?php

namespace Tests\Feature\Admin;

use App\Livewire\Sedes\SedeForm;
use App\Livewire\Sedes\SedeIndex;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * CRUD de Sedes (Blade + Livewire). Ojo: los modelos que se pasan a los
 * componentes van con ->fresh() porque create() no trae los defaults de la
 * BD (radio_metros, activo) y las propiedades tipadas del formulario no
 * aceptan null.
 */
class SedeCrudTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
    }

    /** @param  array<string, mixed>  $atributos */
    private function sedeNueva(array $atributos = []): Sede
    {
        return Sede::create([
            'nombre' => 'Sede auxiliar',
            'latitud' => -13.5200000,
            'longitud' => -71.9700000,
            ...$atributos,
        ])->fresh();
    }

    public function test_el_admin_crea_una_sede_con_su_ubicacion(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SedeForm::class)
            ->set('nombre', 'Sede norte')
            ->set('direccion', 'Av. El Sol 123')
            ->set('latitud', -13.52)
            ->set('longitud', -71.97)
            ->set('radioMetros', 200)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('sedes.index'));

        $sede = Sede::where('nombre', 'Sede norte')->firstOrFail();

        $this->assertSame('Av. El Sol 123', $sede->direccion);
        $this->assertSame(200, $sede->radio_metros);
        $this->assertTrue($sede->activo);
        $this->assertEqualsWithDelta(-13.52, (float) $sede->latitud, 0.0001);
        $this->assertEqualsWithDelta(-71.97, (float) $sede->longitud, 0.0001);
    }

    public function test_no_se_guarda_una_sede_sin_marcar_la_ubicacion_en_el_mapa(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SedeForm::class)
            ->set('nombre', 'Sin mapa')
            ->call('guardar')
            ->assertHasErrors(['latitud' => 'required', 'longitud' => 'required']);

        $this->assertFalse(Sede::where('nombre', 'Sin mapa')->exists());
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SedeForm::class)
            ->set('nombre', '')
            ->set('latitud', -13.5)
            ->set('longitud', -71.9)
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required']);
    }

    public function test_coordenadas_y_radio_fuera_de_rango_se_rechazan(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SedeForm::class)
            ->set('nombre', 'Fuera de rango')
            ->set('latitud', 91)
            ->set('longitud', 181)
            ->set('radioMetros', 5)
            ->call('guardar')
            ->assertHasErrors(['latitud' => 'between', 'longitud' => 'between', 'radioMetros' => 'min']);

        $this->assertFalse(Sede::where('nombre', 'Fuera de rango')->exists());
    }

    public function test_editar_precarga_los_datos_y_actualiza_la_sede(): void
    {
        $sede = $this->sedeNueva(['nombre' => 'Sede vieja', 'direccion' => 'Calle 1']);

        Livewire::actingAs($this->admin)
            ->test(SedeForm::class, ['sede' => $sede])
            ->assertSet('nombre', 'Sede vieja')
            ->assertSet('direccion', 'Calle 1')
            ->assertSet('radioMetros', 150)
            ->assertSet('activo', true)
            ->set('nombre', 'Sede renovada')
            ->set('radioMetros', 300)
            ->set('activo', false)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('sedes.index'));

        $sede = $sede->fresh();

        $this->assertSame('Sede renovada', $sede->nombre);
        $this->assertSame(300, $sede->radio_metros);
        $this->assertFalse($sede->activo);
    }

    public function test_eliminar_una_sede_sin_dependencias_la_borra(): void
    {
        $sede = $this->sedeNueva(['nombre' => 'Sede prescindible']);

        Livewire::actingAs($this->admin)
            ->test(SedeIndex::class)
            ->call('eliminar', $sede->id);

        $this->assertModelMissing($sede);
    }

    public function test_eliminar_una_sede_con_papeletas_la_desactiva_y_conserva_el_historial(): void
    {
        $sede = $this->sedeNueva(['nombre' => 'Sede con historial']);
        $trabajador = $this->usuarioDePrueba(['sede_id' => $sede->id]);
        $papeleta = $this->papeletaDePrueba($trabajador);

        Livewire::actingAs($this->admin)
            ->test(SedeIndex::class)
            ->call('eliminar', $sede->id);

        $this->assertModelExists($sede);
        $this->assertFalse($sede->fresh()->activo);
        $this->assertModelExists($papeleta);
    }

    public function test_el_listado_muestra_las_sedes(): void
    {
        $this->sedeNueva(['nombre' => 'Sede visible en el listado']);

        Livewire::actingAs($this->admin)
            ->test(SedeIndex::class)
            ->assertSee('Sede visible en el listado')
            ->assertSee('Sede central');
    }

    public function test_quien_no_es_admin_no_puede_guardar_ni_eliminar(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $sede = $this->sedeNueva(['nombre' => 'Intocable']);

        Livewire::actingAs($trabajador)
            ->test(SedeIndex::class)
            ->call('eliminar', $sede->id)
            ->assertForbidden();

        Livewire::actingAs($trabajador)
            ->test(SedeForm::class)
            ->set('nombre', 'Colada')
            ->set('latitud', -13.5)
            ->set('longitud', -71.9)
            ->call('guardar')
            ->assertForbidden();

        $this->assertModelExists($sede);
        $this->assertFalse(Sede::where('nombre', 'Colada')->exists());
    }
}
