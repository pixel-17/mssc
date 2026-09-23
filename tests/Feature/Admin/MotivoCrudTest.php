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

    public function test_el_admin_crea_un_motivo_con_todas_sus_banderas(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', 'CAPACITACION')
            ->set('nombre', 'Capacitación')
            ->set('adjunto', 'opcional')
            ->set('sumaDescuento', true)
            ->set('permiteCierreSinRetorno', true)
            ->set('requiereSustentoEnRetorno', true)
            ->set('esDestinoReclasificacion', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('motivos.index'));

        $motivo = Motivo::where('codigo', 'CAPACITACION')->firstOrFail();

        $this->assertSame('Capacitación', $motivo->nombre);
        $this->assertSame('opcional', $motivo->adjunto);
        $this->assertTrue($motivo->activo);
        $this->assertTrue($motivo->suma_descuento);
        $this->assertTrue($motivo->permite_cierre_sin_retorno);
        $this->assertTrue($motivo->requiere_sustento_en_retorno);
        $this->assertTrue($motivo->es_destino_reclasificacion);
    }

    public function test_un_motivo_nuevo_nace_con_las_banderas_apagadas(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', 'SIMPLE')
            ->set('nombre', 'Simple')
            ->call('guardar')
            ->assertHasNoErrors();

        $motivo = Motivo::where('codigo', 'SIMPLE')->firstOrFail();

        $this->assertSame('no', $motivo->adjunto);
        $this->assertFalse($motivo->suma_descuento);
        $this->assertFalse($motivo->permite_cierre_sin_retorno);
        $this->assertFalse($motivo->requiere_sustento_en_retorno);
        $this->assertFalse($motivo->es_destino_reclasificacion);
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

    public function test_el_tipo_de_adjunto_debe_ser_uno_de_los_permitidos(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class)
            ->set('codigo', 'ADJ_MALO')
            ->set('nombre', 'Adjunto malo')
            ->set('adjunto', 'siempre')
            ->call('guardar')
            ->assertHasErrors(['adjunto' => 'in']);

        foreach (['no', 'opcional', 'flexible', 'obligatorio'] as $valido) {
            Livewire::actingAs($this->admin)
                ->test(MotivoForm::class)
                ->set('codigo', 'ADJ_'.strtoupper($valido))
                ->set('nombre', 'Adjunto '.$valido)
                ->set('adjunto', $valido)
                ->call('guardar')
                ->assertHasNoErrors();
        }
    }

    public function test_editar_precarga_y_permite_conservar_el_propio_codigo(): void
    {
        $motivo = $this->motivoNuevo(['suma_descuento' => true]);

        Livewire::actingAs($this->admin)
            ->test(MotivoForm::class, ['motivo' => $motivo])
            ->assertSet('codigo', 'MOTIVO_NUEVO')
            ->assertSet('sumaDescuento', true)
            ->set('nombre', 'Motivo renombrado')
            ->set('sumaDescuento', false)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('motivos.index'));

        $motivo = $motivo->fresh();

        $this->assertSame('MOTIVO_NUEVO', $motivo->codigo);
        $this->assertSame('Motivo renombrado', $motivo->nombre);
        $this->assertFalse($motivo->suma_descuento);
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
