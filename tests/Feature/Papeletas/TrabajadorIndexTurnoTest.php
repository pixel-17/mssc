<?php

namespace Tests\Feature\Papeletas;

use App\Livewire\Papeletas\TrabajadorIndex;
use App\States\Papeleta\Cerrada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * "Mis papeletas" muestra por defecto solo las del turno en curso (salen
 * de la vista cuando el turno termina) y un botón permite ver todas.
 */
class TrabajadorIndexTurnoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    /** @return array<int, int> */
    private function ids($lista): array
    {
        return collect($lista->items())->pluck('id')->sort()->values()->all();
    }

    public function test_por_defecto_solo_ve_las_papeletas_de_su_turno_en_curso(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $delTurno1 = $this->papeletaDePrueba($trabajador, Cerrada::class, ['fin_turno_at' => now()->addHours(3)]);
        $delTurno2 = $this->papeletaDePrueba($trabajador, Cerrada::class, ['fin_turno_at' => now()->addHours(3)]);
        $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'fin_turno_at' => now()->subHours(2),
            'dia_operativo' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($trabajador)
            ->test(TrabajadorIndex::class)
            ->assertViewHas('papeletas', fn ($l) => $this->ids($l) === collect([$delTurno1->id, $delTurno2->id])->sort()->values()->all());
    }

    public function test_sin_fin_de_turno_se_usa_el_dia_operativo_de_hoy(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $hoy = $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'fin_turno_at' => null,
            'dia_operativo' => now()->toDateString(),
        ]);
        $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'fin_turno_at' => null,
            'dia_operativo' => now()->subDays(2)->toDateString(),
        ]);

        Livewire::actingAs($trabajador)
            ->test(TrabajadorIndex::class)
            ->assertViewHas('papeletas', fn ($l) => $this->ids($l) === [$hoy->id]);
    }

    public function test_el_boton_todas_muestra_tambien_las_de_turnos_anteriores(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $vigente = $this->papeletaDePrueba($trabajador, Cerrada::class, ['fin_turno_at' => now()->addHours(3)]);
        $vieja = $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'fin_turno_at' => now()->subDays(3),
            'dia_operativo' => now()->subDays(3)->toDateString(),
        ]);

        Livewire::actingAs($trabajador)
            ->test(TrabajadorIndex::class)
            ->assertViewHas('papeletas', fn ($l) => $l->total() === 1)
            ->set('verTodas', true)
            ->assertViewHas('papeletas', fn ($l) => $this->ids($l) === collect([$vigente->id, $vieja->id])->sort()->values()->all())
            ->set('verTodas', false)
            ->assertViewHas('papeletas', fn ($l) => $l->total() === 1);
    }

    public function test_con_el_turno_vacio_ofrece_ver_todas_y_no_el_mensaje_de_primera_vez(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'fin_turno_at' => now()->subDays(3),
            'dia_operativo' => now()->subDays(3)->toDateString(),
        ]);

        Livewire::actingAs($trabajador)
            ->test(TrabajadorIndex::class)
            ->assertSee('No tienes papeletas en tu turno actual')
            ->assertSee('Ver todas mis papeletas')
            ->assertDontSee('Todavía no tienes papeletas');
    }

    public function test_sin_ninguna_papeleta_sigue_el_mensaje_de_primera_vez(): void
    {
        $trabajador = $this->usuarioDePrueba();

        Livewire::actingAs($trabajador)
            ->test(TrabajadorIndex::class)
            ->assertSee('Todavía no tienes papeletas')
            ->assertDontSee('No tienes papeletas en tu turno actual');
    }

    public function test_no_ve_papeletas_de_otros_trabajadores(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $otro = $this->usuarioDePrueba();

        $mia = $this->papeletaDePrueba($trabajador, Cerrada::class, ['fin_turno_at' => now()->addHours(3)]);
        $this->papeletaDePrueba($otro, Cerrada::class, ['fin_turno_at' => now()->addHours(3)]);

        Livewire::actingAs($trabajador)
            ->test(TrabajadorIndex::class)
            ->set('verTodas', true)
            ->assertViewHas('papeletas', fn ($l) => $this->ids($l) === [$mia->id]);
    }
}
