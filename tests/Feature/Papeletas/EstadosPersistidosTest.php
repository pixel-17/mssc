<?php

namespace Tests\Feature\Papeletas;

use App\Exceptions\PapeletaException;
use App\Models\Papeleta;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\PendienteJefe;
use App\States\Papeleta\PendienteRrhh;
use App\States\Papeleta\Rechazada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class EstadosPersistidosTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function estadoCrudo(Papeleta $papeleta): string
    {
        return DB::table('papeletas')->where('id', $papeleta->id)->value('estado');
    }

    public function test_el_estado_se_guarda_con_un_nombre_estable_y_no_con_la_clase(): void
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba());

        $this->assertSame('pendiente_jefe', $this->estadoCrudo($papeleta));
    }

    public function test_where_state_encuentra_las_filas(): void
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba());

        $this->assertSame(1, Papeleta::whereState('estado', PendienteJefe::class)->count());
        $this->assertSame(0, Papeleta::whereState('estado', PendienteRrhh::class)->count());
        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_una_transicion_no_permitida_se_bloquea(): void
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba());

        $this->assertThrows(
            fn () => $papeleta->transicionarA(Cerrada::class),
            PapeletaException::class,
            'Transición de estado no permitida',
        );
    }

    public function test_una_transicion_permitida_se_persiste(): void
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba());

        $papeleta->transicionarA(Rechazada::class);
        $papeleta->save();

        $this->assertSame('rechazada', $this->estadoCrudo($papeleta));
    }
}
