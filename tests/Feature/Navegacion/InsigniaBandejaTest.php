<?php

namespace Tests\Feature\Navegacion;

use App\Livewire\Navegacion\InsigniaBandeja;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El contador del sidebar. Antes eran dos COUNT dentro de layouts/app.blade.php
 * que comparaban `estado` contra el nombre de la clase y, desde que los estados
 * se guardan con $name, devolvían siempre 0.
 */
class InsigniaBandejaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_el_jefe_ve_solo_las_papeletas_pendientes_de_su_equipo(): void
    {
        $jefe = $this->usuarioDePrueba();
        $otroJefe = $this->usuarioDePrueba();

        $ana = $this->usuarioDePrueba(['jefe_inmediato_id' => $jefe->id]);
        $luis = $this->usuarioDePrueba(['jefe_inmediato_id' => $jefe->id]);
        $ajena = $this->usuarioDePrueba(['jefe_inmediato_id' => $otroJefe->id]);

        $this->papeletaDePrueba($ana);
        $this->papeletaDePrueba($luis);
        $this->papeletaDePrueba($ajena);

        Livewire::actingAs($jefe)
            ->test(InsigniaBandeja::class, ['bandeja' => 'jefe'])
            ->assertSee('2 papeletas por decidir');
    }

    public function test_no_cuenta_las_papeletas_que_ya_no_estan_pendientes_de_jefe(): void
    {
        $jefe = $this->usuarioDePrueba();
        $ana = $this->usuarioDePrueba(['jefe_inmediato_id' => $jefe->id]);

        $this->papeletaDePrueba($ana, PendienteRrhh::class);

        Livewire::actingAs($jefe)
            ->test(InsigniaBandeja::class, ['bandeja' => 'jefe'])
            ->assertDontSee('por decidir');
    }

    public function test_singular_cuando_hay_una(): void
    {
        $jefe = $this->usuarioDePrueba();
        $this->papeletaDePrueba($this->usuarioDePrueba(['jefe_inmediato_id' => $jefe->id]), PendienteJefe::class);

        Livewire::actingAs($jefe)
            ->test(InsigniaBandeja::class, ['bandeja' => 'jefe'])
            ->assertSee('1 papeleta por decidir');
    }

    public function test_rrhh_cuenta_todas_las_pendientes_de_rrhh_y_los_demas_no_ven_nada(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $trabajador = $this->usuarioDePrueba();

        $this->papeletaDePrueba($this->usuarioDePrueba(), PendienteRrhh::class);
        $this->papeletaDePrueba($this->usuarioDePrueba(), PendienteRrhh::class);
        $this->papeletaDePrueba($this->usuarioDePrueba(), PendienteJefe::class);

        Livewire::actingAs($rrhh)
            ->test(InsigniaBandeja::class, ['bandeja' => 'rrhh'])
            ->assertSee('2 papeletas por decidir');

        Livewire::actingAs($trabajador)
            ->test(InsigniaBandeja::class, ['bandeja' => 'rrhh'])
            ->assertDontSee('por decidir');
    }

    public function test_el_cliente_no_puede_cambiar_de_bandeja(): void
    {
        $jefe = $this->usuarioDePrueba();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($jefe)
            ->test(InsigniaBandeja::class, ['bandeja' => 'jefe'])
            ->set('bandeja', 'rrhh');
    }
}
