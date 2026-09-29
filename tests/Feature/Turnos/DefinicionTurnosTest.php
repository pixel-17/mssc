<?php

namespace Tests\Feature\Turnos;

use App\Livewire\Turnos\DefinicionTurnos;
use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El catálogo "Turnos" del admin solo define las horas de cada turno
 * (claves TURNO_*_HORA_* de `configuraciones`); no programa trabajadores.
 */
class DefinicionTurnosTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    public function test_muestra_las_horas_vigentes_de_cada_turno(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        Livewire::actingAs($admin)
            ->test(DefinicionTurnos::class)
            ->assertSet('horas.MANANA.inicio', '06:00')
            ->assertSet('horas.MANANA.fin', '14:00')
            ->assertSet('horas.NOCHE.inicio', '22:00')
            ->assertSet('horas.NOCHE.fin', '06:00')
            ->assertSet('horas.DIA.inicio', '07:45');
    }

    public function test_guarda_las_horas_en_configuraciones_y_se_leen_de_inmediato(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        // Calienta la caché de valorDe() para comprobar que se invalida al guardar.
        $this->assertSame('06:00', Configuracion::valorDe('TURNO_MANANA_HORA_INICIO'));

        Livewire::actingAs($admin)
            ->test(DefinicionTurnos::class)
            ->set('horas.MANANA.inicio', '07:00')
            ->set('horas.MANANA.fin', '15:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('configuraciones', ['clave' => 'TURNO_MANANA_HORA_INICIO', 'valor' => '07:00']);
        $this->assertDatabaseHas('configuraciones', ['clave' => 'TURNO_MANANA_HORA_FIN', 'valor' => '15:00']);
        $this->assertSame('07:00', Configuracion::valorDe('TURNO_MANANA_HORA_INICIO'));
    }

    public function test_rechaza_horas_mal_escritas_o_con_inicio_igual_al_fin(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        Livewire::actingAs($admin)
            ->test(DefinicionTurnos::class)
            ->set('horas.TARDE.inicio', '8am')
            ->set('horas.NOCHE.fin', '22:00')
            ->call('guardar')
            ->assertHasErrors(['horas.TARDE.inicio' => 'date_format', 'horas.NOCHE.fin' => 'different']);

        $this->assertDatabaseHas('configuraciones', ['clave' => 'TURNO_TARDE_HORA_INICIO', 'valor' => '14:00']);
    }

    public function test_un_no_admin_no_puede_abrir_ni_guardar(): void
    {
        $trabajador = $this->usuarioDePrueba([], ['trabajador']);

        Livewire::actingAs($trabajador)
            ->test(DefinicionTurnos::class)
            ->assertForbidden();
    }
}
