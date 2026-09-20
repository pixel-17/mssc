<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Models\Motivo;
use App\Models\Papeleta;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión del error "Ya tienes una papeleta activa...": un trabajador
 * puede tener varias papeletas al mismo tiempo y Emergencia ya no existe.
 */
class CrearPapeletaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_un_trabajador_puede_tener_varias_papeletas_a_la_vez(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $crear = app(CrearPapeletaAction::class);

        $primera = $crear->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
        $crear->ejecutar($trabajador, $this->motivoDe('SALUD'), []);
        $crear->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);

        $this->assertSame(3, Papeleta::where('trabajador_id', $trabajador->id)->count());
        $this->assertTrue($primera->fresh()->estado->equals(PendienteJefe::class));
    }

    public function test_no_existe_el_motivo_emergencia(): void
    {
        $this->assertFalse(Motivo::where('codigo', 'EMERGENCIA')->exists());
    }

    public function test_la_bd_ya_no_tiene_columnas_de_emergencia_ni_de_slots(): void
    {
        foreach ([
            'slot_normal_activo',
            'slot_emergencia_activo',
            'es_emergencia',
            'visto_bueno_jefe_emergencia',
            'visto_bueno_rrhh_emergencia',
            'subsanacion_emergencia_fecha_limite',
            'subsanacion_emergencia_adjunto_path',
        ] as $columna) {
            $this->assertFalse(Schema::hasColumn('papeletas', $columna), "papeletas.{$columna} debería haberse eliminado");
        }

        foreach (['permite_bypass_aprobacion', 'participa_regla_exclusividad'] as $columna) {
            $this->assertFalse(Schema::hasColumn('motivos', $columna), "motivos.{$columna} debería haberse eliminado");
        }
    }
}
