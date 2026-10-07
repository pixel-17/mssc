<?php

namespace Tests\Feature\Configuraciones;

use App\Livewire\Configuraciones\ConfiguracionIndex;
use App\Models\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class ConfiguracionIndexTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_las_horas_de_turno_no_aparecen_en_configuraciones_solo_en_turnos(): void
    {
        Configuracion::updateOrCreate(['clave' => 'TURNO_MANANA_HORA_INICIO'], ['valor' => '06:00', 'descripcion' => 'x']);
        Configuracion::updateOrCreate(['clave' => 'TURNO_DIA_HORA_FIN'], ['valor' => '16:15', 'descripcion' => 'x']);
        Configuracion::updateOrCreate(['clave' => 'HORARIO_ORDINARIO_HORA_INICIO'], ['valor' => '07:45', 'descripcion' => 'x']);
        Configuracion::updateOrCreate(['clave' => 'HORARIO_ORDINARIO_DIAS_LABORABLES'], ['valor' => '1,2,3,4,5', 'descripcion' => 'x']);
        Configuracion::updateOrCreate(['clave' => 'TOPE_OBSERVACIONES'], ['valor' => '3', 'descripcion' => 'x']);

        Livewire::actingAs($this->usuarioDePrueba([], ['admin']))
            ->test(ConfiguracionIndex::class)
            ->assertSee('TOPE_OBSERVACIONES')
            ->assertDontSee('TURNO_MANANA_HORA_INICIO')
            ->assertDontSee('TURNO_DIA_HORA_FIN')
            ->assertDontSee('HORARIO_ORDINARIO_HORA_INICIO')
            ->assertSee('HORARIO_ORDINARIO_DIAS_LABORABLES');
    }
}
