<?php

namespace Tests\Feature\Admin;

use App\Livewire\Motivos\MotivoForm;
use App\Livewire\Motivos\MotivoIndex;
use App\Models\Motivo;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * CRUD de Motivos. Sus banderas (suma_descuento, cierre sin retorno...) son
 * la única fuente de verdad del flujo de papeletas, por eso se comprueba que
 * cada una viaje bien del formulario a la BD.
 */
class MotivoCrudTest extends TestCase
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
    private function motivoNuevo(array $atributos = []): Motivo
    {
        return Motivo::create([
            'codigo' => 'MOTIVO_NUEVO',
            'nombre' => 'Motivo nuevo',
            ...$atributos,
        ])->fresh();
    }

    public function test_un_motivo_nuevo_nace_sin_justificacion_ni_descuento(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', 'SIMPLE')
            ->set('nombre', 'Simple')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('motivos.index'));

        $motivo = Motivo::where('codigo', 'SIMPLE')->firstOrFail();

        $this->assertTrue($motivo->activo);
        $this->assertFalse($motivo->suma_descuento);
        $this->assertFalse($motivo->requiere_sustento_en_retorno);
        $this->assertFalse($motivo->es_destino_reclasificacion);
        $this->assertSame('libre', $motivo->consecuenciaAlTerminar());
    }

    public function test_las_dos_reglas_producen_una_sola_consecuencia(): void
    {
        $casos = [
            // [requiereJustificacion, aplicaDescuento, suma_descuento, requiere_sustento, consecuencia]
            'particular' => [false, true, true, false, 'descuenta'],
            'salud' => [true, false, false, true, 'justificar'],
            'comision' => [false, false, false, false, 'libre'],
            // Si requiere justificación, el descuento no se guarda: depende de si la presenta.
            'ambas' => [true, true, false, true, 'justificar'],
        ];

        foreach ($casos as $codigo => [$requiere, $descuento, $suma, $sustento, $consecuencia]) {
            Livewire::actingAs($this->admin)
                ->test(MotivoForm::class)
                ->set('codigo', 'REGLA_'.strtoupper($codigo))
                ->set('nombre', 'Regla '.$codigo)
                ->set('requiereJustificacion', $requiere)
                ->set('aplicaDescuento', $descuento)
                ->call('guardar')
                ->assertHasNoErrors();

            $motivo = Motivo::where('codigo', 'REGLA_'.strtoupper($codigo))->firstOrFail();
            $this->assertSame($suma, $motivo->suma_descuento, $codigo);
            $this->assertSame($sustento, $motivo->requiere_sustento_en_retorno, $codigo);
            $this->assertSame($consecuencia, $motivo->consecuenciaAlTerminar(), $codigo);
        }
    }

    public function test_el_plazo_solo_se_guarda_si_requiere_justificacion(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', 'CON_PLAZO')
            ->set('nombre', 'Con plazo')
            ->set('requiereJustificacion', true)
            ->set('plazoJustificacionHorasHabiles', 24)
            ->call('guardar')
            ->assertHasNoErrors();

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', 'SIN_PLAZO')
            ->set('nombre', 'Sin plazo')
            ->set('aplicaDescuento', true)
            ->set('plazoJustificacionHorasHabiles', 24)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(24, Motivo::where('codigo', 'CON_PLAZO')->firstOrFail()->plazo_justificacion_horas_habiles);
        $this->assertNull(Motivo::where('codigo', 'SIN_PLAZO')->firstOrFail()->plazo_justificacion_horas_habiles);
    }

    public function test_editar_precarga_las_reglas_y_permite_conservar_el_propio_codigo(): void
    {
        $motivo = $this->motivoNuevo(['suma_descuento' => true]);

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class, ['motivo' => $motivo])
            ->assertSet('codigo', 'MOTIVO_NUEVO')
            ->assertSet('aplicaDescuento', true)
            ->assertSet('requiereJustificacion', false)
            ->set('nombre', 'Motivo renombrado')
            ->set('aplicaDescuento', false)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('motivos.index'));

        $motivo = $motivo->fresh();

        $this->assertSame('MOTIVO_NUEVO', $motivo->codigo);
        $this->assertSame('Motivo renombrado', $motivo->nombre);
        $this->assertFalse($motivo->suma_descuento);
    }

    public function test_un_motivo_con_ambas_banderas_se_muestra_como_requiere_justificacion(): void
    {
        // Misma prioridad que MarcarRetornoAction: si pide justificación, ese camino manda.
        $motivo = $this->motivoNuevo(['suma_descuento' => true, 'requiere_sustento_en_retorno' => true]);

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class, ['motivo' => $motivo])
            ->assertSet('requiereJustificacion', true)
            ->assertSet('aplicaDescuento', false);
    }

    public function test_editar_el_motivo_particular_no_pierde_su_condicion_de_destino(): void
    {
        $particular = $this->motivoDe('PARTICULAR');
        $this->assertTrue($particular->es_destino_reclasificacion);

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class, ['motivo' => $particular])
            ->set('nombre', 'Particular (renombrado)')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue($particular->fresh()->es_destino_reclasificacion);
        $this->assertTrue($particular->fresh()->suma_descuento);
    }

    public function test_codigo_y_nombre_son_obligatorios(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->call('guardar')
            ->assertHasErrors(['codigo' => 'required', 'nombre' => 'required']);
    }

    public function test_el_codigo_no_puede_repetirse_al_crear(): void
    {
        $existente = $this->motivoDe('PARTICULAR');
        $antes = Motivo::count();

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', $existente->codigo)
            ->set('nombre', 'Duplicado')
            ->call('guardar')
            ->assertHasErrors(['codigo' => 'unique']);

        $this->assertSame($antes, Motivo::count());
    }

    public function test_editar_no_puede_tomar_el_codigo_de_otro_motivo(): void
    {
        $motivo = $this->motivoNuevo();
        $otro = $this->motivoDe('PARTICULAR');

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class, ['motivo' => $motivo])
            ->set('codigo', $otro->codigo)
            ->call('guardar')
            ->assertHasErrors(['codigo' => 'unique']);

        $this->assertSame('MOTIVO_NUEVO', $motivo->fresh()->codigo);
    }

    public function test_eliminar_un_motivo_sin_papeletas_lo_borra(): void
    {
        $motivo = $this->motivoNuevo();

        Livewire::actingAs($this->admin)
            ->test(MotivoIndex::class)
            ->call('eliminar', $motivo->id);

        $this->assertModelMissing($motivo);
    }

    public function test_eliminar_un_motivo_con_papeletas_lo_desactiva_y_conserva_el_historial(): void
    {
        $motivo = $this->motivoNuevo();
        $trabajador = $this->usuarioDePrueba();
        $papeleta = $this->papeletaDePrueba($trabajador, PendienteJefe::class, ['motivo_id' => $motivo->id]);

        Livewire::actingAs($this->admin)
            ->test(MotivoIndex::class)
            ->call('eliminar', $motivo->id);

        $this->assertModelExists($motivo);
        $this->assertFalse($motivo->fresh()->activo);
        $this->assertModelExists($papeleta);
    }

    public function test_el_listado_muestra_los_motivos_sembrados(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoIndex::class)
            ->assertSee($this->motivoDe('PARTICULAR')->nombre);
    }

    public function test_quien_no_es_admin_no_puede_guardar_ni_eliminar(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $motivo = $this->motivoNuevo();

        Livewire::actingAs($trabajador)
            ->test(MotivoIndex::class)
            ->call('eliminar', $motivo->id)
            ->assertForbidden();

        Livewire::actingAs($trabajador)
            ->test(MotivoForm::class)
            ->set('codigo', 'COLADO')
            ->set('nombre', 'Colado')
            ->call('guardar')
            ->assertForbidden();

        $this->assertModelExists($motivo);
        $this->assertFalse(Motivo::where('codigo', 'COLADO')->exists());
    }
}
