<?php

namespace Tests\Feature\Papeletas;

use App\Livewire\Papeletas\JefeIndex;
use App\Livewire\Papeletas\RrhhIndex;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Las bandejas de Jefe y RRHH pagan cada lista por separado (10 filas
 * por página). Antes cargaban TODAS las papeletas con ->get().
 */
class BandejasPaginacionTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    /** Crea $cantidad papeletas pendientes de RRHH, cada una de un trabajador distinto. */
    private function papeletasPendientesDeRrhh(int $cantidad, string $nombre = 'Lote'): void
    {
        for ($i = 1; $i <= $cantidad; $i++) {
            $trabajador = $this->usuarioDePrueba(['name' => $nombre, 'apellido' => "Prueba{$i}"]);
            $this->papeletaDePrueba($trabajador, PendienteRrhh::class);
        }
    }

    public function test_rrhh_muestra_diez_por_pagina_y_el_total_real(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $this->papeletasPendientesDeRrhh(12);

        $componente = Livewire::actingAs($rrhh)->test(RrhhIndex::class);

        $componente->assertViewHas('porDecidir', fn ($lista) => $lista->total() === 12
            && $lista->count() === 10
            && $lista->currentPage() === 1);
        $componente->assertSee('Por decidir (12)');

        $componente->call('gotoPage', 2, 'porDecidirPage')
            ->assertViewHas('porDecidir', fn ($lista) => $lista->currentPage() === 2 && $lista->count() === 2);
    }

    public function test_buscar_en_rrhh_vuelve_a_la_primera_pagina(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $this->papeletasPendientesDeRrhh(12);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->call('gotoPage', 2, 'porDecidirPage')
            ->set('buscar', 'Lote')
            ->assertViewHas('porDecidir', fn ($lista) => $lista->currentPage() === 1
                && $lista->total() === 12
                && $lista->count() === 10);
    }

    public function test_una_pagina_que_ya_no_existe_vuelve_a_la_ultima(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $this->papeletasPendientesDeRrhh(12);

        Livewire::actingAs($rrhh)
            ->test(RrhhIndex::class)
            ->call('setPage', 5, 'porDecidirPage')
            ->assertViewHas('porDecidir', fn ($lista) => $lista->currentPage() === 2
                && $lista->count() === 2);
    }

    public function test_el_jefe_pagina_por_decidir_y_no_repite_en_papeletas_del_turno(): void
    {
        $jefe = $this->usuarioDePrueba();

        for ($i = 1; $i <= 12; $i++) {
            $trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $jefe->id]);
            $this->papeletaDePrueba($trabajador, PendienteJefe::class, [
                'fin_turno_at' => now()->addHours(2),
            ]);
        }

        $componente = Livewire::actingAs($jefe)->test(JefeIndex::class);

        // Las 12 están en "Por decidir" (10 en la página 1, 2 en la página 2):
        // ninguna debe colarse en "Papeletas del turno", ni siquiera las de
        // la página que no se está viendo.
        $componente->assertViewHas('porDecidir', fn ($lista) => $lista->total() === 12 && $lista->count() === 10)
            ->assertViewHas('delTurno', fn ($lista) => $lista->total() === 0);

        $componente->call('gotoPage', 2, 'porDecidirPage')
            ->assertViewHas('porDecidir', fn ($lista) => $lista->count() === 2)
            ->assertViewHas('delTurno', fn ($lista) => $lista->total() === 0);
    }

    public function test_el_jefe_sigue_viendo_en_el_turno_lo_que_ya_decidio(): void
    {
        $jefe = $this->usuarioDePrueba();

        for ($i = 1; $i <= 11; $i++) {
            $trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $jefe->id]);
            $this->papeletaDePrueba($trabajador, Cerrada::class, [
                'fin_turno_at' => now()->addHours(2),
            ]);
        }

        Livewire::actingAs($jefe)
            ->test(JefeIndex::class)
            ->assertViewHas('porDecidir', fn ($lista) => $lista->total() === 0)
            ->assertViewHas('delTurno', fn ($lista) => $lista->total() === 11 && $lista->count() === 10);
    }
}
