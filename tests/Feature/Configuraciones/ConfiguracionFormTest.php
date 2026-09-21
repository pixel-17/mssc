<?php

namespace Tests\Feature\Configuraciones;

use App\Livewire\Configuraciones\ConfiguracionForm;
use App\Models\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class ConfiguracionFormTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function editar(string $clave, string $valorActual, string $valorNuevo)
    {
        $configuracion = Configuracion::create(['clave' => $clave, 'valor' => $valorActual, 'descripcion' => 'x']);

        return Livewire::actingAs($this->usuarioDePrueba([], ['admin']))
            ->test(ConfiguracionForm::class, ['configuracion' => $configuracion])
            ->set('valor', $valorNuevo)
            ->call('guardar');
    }

    public function test_una_hora_valida_se_guarda(): void
    {
        $this->editar('HORARIO_ORDINARIO_HORA_INICIO', '07:45', '08:30')->assertHasNoErrors();

        $this->assertSame('08:30', Configuracion::where('clave', 'HORARIO_ORDINARIO_HORA_INICIO')->value('valor'));
    }

    public function test_una_hora_mal_escrita_se_rechaza_y_no_se_guarda(): void
    {
        foreach (['8:00', '25:99', '8am', '0800', ''] as $malo) {
            Configuracion::where('clave', 'HORARIO_ORDINARIO_HORA_FIN')->delete();

            $this->editar('HORARIO_ORDINARIO_HORA_FIN', '16:15', $malo)->assertHasErrors('valor');

            $this->assertSame('16:15', Configuracion::where('clave', 'HORARIO_ORDINARIO_HORA_FIN')->value('valor'), "no debe guardar '{$malo}'");
        }
    }

    public function test_el_bloque_de_almuerzo_y_los_turnos_tambien_exigen_hora(): void
    {
        foreach (['BLOQUE_ALMUERZO_INICIO', 'TURNO_NOCHE_HORA_FIN'] as $clave) {
            Configuracion::where('clave', $clave)->delete();

            $this->editar($clave, '13:00', '1pm')->assertHasErrors('valor');
        }
    }

    public function test_los_dias_laborables_exigen_numeros_del_1_al_7(): void
    {
        $this->editar('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5', '1,2,3,4,5,6')->assertHasNoErrors();

        Configuracion::where('clave', 'HORARIO_ORDINARIO_DIAS_LABORABLES')->delete();

        foreach (['lunes,martes', '1,2,8', '0', '1,,2', '1,2,'] as $malo) {
            Configuracion::where('clave', 'HORARIO_ORDINARIO_DIAS_LABORABLES')->delete();

            $this->editar('HORARIO_ORDINARIO_DIAS_LABORABLES', '1,2,3,4,5', $malo)->assertHasErrors('valor');
        }
    }

    public function test_los_valores_numericos_deben_ser_enteros_positivos(): void
    {
        $this->editar('SUSTENTO_HORAS_HABILES', '48', '72')->assertHasNoErrors();

        foreach (['abc', '0', '-3', '2.5'] as $malo) {
            Configuracion::where('clave', 'TOPE_OBSERVACIONES')->delete();

            $this->editar('TOPE_OBSERVACIONES', '3', $malo)->assertHasErrors('valor');
        }
    }

    public function test_el_modo_estricto_sigue_aceptando_solo_0_o_1(): void
    {
        $this->editar('MODO_ESTRICTO_728', '0', '1')->assertHasNoErrors();

        Configuracion::where('clave', 'MODO_ESTRICTO_728')->delete();

        $this->editar('MODO_ESTRICTO_728', '0', '2')->assertHasErrors('valor');
    }

    public function test_las_claves_de_texto_libre_conservan_su_regla(): void
    {
        $this->editar('MODO_ESTRICTO_728_MENSAJE', 'Mensaje', 'Otro mensaje')->assertHasNoErrors();

        $this->assertContains('date_format:H:i', ConfiguracionForm::reglasParaClave('TURNO_MANANA_HORA_INICIO'));
        $this->assertNotContains('date_format:H:i', ConfiguracionForm::reglasParaClave('MODO_ESTRICTO_728_MENSAJE'));
    }
}
