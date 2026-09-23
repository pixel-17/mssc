<?php

namespace Tests\Feature\Admin;

use App\Livewire\Feriados\FeriadoForm;
use App\Livewire\Feriados\FeriadoIndex;
use App\Models\Feriado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * CRUD de Feriados (los usa CalculadorDiasHabiles para las 48h hábiles de
 * sustento). Los feriados de partida se insertan con DB::table() para que
 * `fecha` quede como 'Y-m-d' puro, igual que en una columna DATE real; con
 * Feriado::create() SQLite guardaría 'Y-m-d 00:00:00' por el cast de fecha y
 * la regla unique del formulario (comparación exacta) no lo vería como
 * duplicado.
 */
class FeriadoCrudTest extends TestCase
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

    private function feriadoExistente(string $fecha, ?string $descripcion = null): Feriado
    {
        $id = DB::table('feriados')->insertGetId([
            'fecha' => $fecha,
            'descripcion' => $descripcion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Feriado::findOrFail($id);
    }

    public function test_el_admin_crea_un_feriado(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class)
            ->set('fecha', '2026-12-25')
            ->set('descripcion', 'Navidad')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('feriados.index'));

        $feriado = Feriado::whereDate('fecha', '2026-12-25')->firstOrFail();

        $this->assertSame('Navidad', $feriado->descripcion);
    }

    public function test_la_descripcion_es_opcional(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class)
            ->set('fecha', '2026-07-28')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue(Feriado::whereDate('fecha', '2026-07-28')->exists());
    }

    public function test_la_fecha_es_obligatoria_y_debe_ser_valida(): void
    {
        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class)
            ->set('fecha', '')
            ->call('guardar')
            ->assertHasErrors(['fecha' => 'required']);

        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class)
            ->set('fecha', 'no-es-una-fecha')
            ->call('guardar')
            ->assertHasErrors(['fecha' => 'date']);

        $this->assertSame(0, Feriado::count());
    }

    public function test_no_se_puede_repetir_la_fecha_de_un_feriado(): void
    {
        $this->feriadoExistente('2026-12-25', 'Navidad');

        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class)
            ->set('fecha', '2026-12-25')
            ->set('descripcion', 'Otra Navidad')
            ->call('guardar')
            ->assertHasErrors(['fecha' => 'unique']);

        $this->assertSame(1, Feriado::count());
    }

    public function test_editar_precarga_permite_conservar_la_fecha_y_cambiar_la_descripcion(): void
    {
        $feriado = $this->feriadoExistente('2026-12-08', 'Inmaculada');

        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class, ['feriado' => $feriado])
            ->assertSet('fecha', '2026-12-08')
            ->assertSet('descripcion', 'Inmaculada')
            ->set('descripcion', 'Inmaculada Concepción')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('feriados.index'));

        $this->assertSame('Inmaculada Concepción', $feriado->fresh()->descripcion);
        $this->assertSame(1, Feriado::count());
    }

    public function test_editar_no_puede_mover_el_feriado_a_una_fecha_ya_ocupada(): void
    {
        $this->feriadoExistente('2026-12-25', 'Navidad');
        $otro = $this->feriadoExistente('2026-12-08', 'Inmaculada');

        Livewire::actingAs($this->admin)
            ->test(FeriadoForm::class, ['feriado' => $otro])
            ->set('fecha', '2026-12-25')
            ->call('guardar')
            ->assertHasErrors(['fecha' => 'unique']);

        $this->assertSame('2026-12-08', $otro->fresh()->fecha->toDateString());
    }

    public function test_eliminar_borra_el_feriado(): void
    {
        $feriado = $this->feriadoExistente('2026-01-01', 'Año Nuevo');

        Livewire::actingAs($this->admin)
            ->test(FeriadoIndex::class)
            ->call('eliminar', $feriado->id);

        $this->assertModelMissing($feriado);
    }

    public function test_el_listado_muestra_los_feriados(): void
    {
        $this->feriadoExistente('2026-05-01', 'Día del Trabajo');

        Livewire::actingAs($this->admin)
            ->test(FeriadoIndex::class)
            ->assertSee('Día del Trabajo');
    }

    public function test_quien_no_es_admin_no_puede_guardar_ni_eliminar(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $feriado = $this->feriadoExistente('2026-01-01', 'Año Nuevo');

        Livewire::actingAs($trabajador)
            ->test(FeriadoIndex::class)
            ->call('eliminar', $feriado->id)
            ->assertForbidden();

        Livewire::actingAs($trabajador)
            ->test(FeriadoForm::class)
            ->set('fecha', '2026-02-02')
            ->call('guardar')
            ->assertForbidden();

        $this->assertModelExists($feriado);
        $this->assertSame(1, Feriado::count());
    }
}
