<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\ReclasificarAParticularAction;
use App\Models\HistorialPapeleta;
use App\Models\User;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Regresión: transicionarConHistorial() tenía 5 parámetros y
 * ReclasificarAParticularAction le pasaba un 6to argumento (el array con
 * motivo_anterior_id/motivo_nuevo_id) que PHP descartaba en silencio sin
 * lanzar error. El historial de auditoría quedaba sin esos dos datos
 * pese a que la tabla historial_papeletas los tiene justo para este caso.
 */
class ReclasificarAParticularTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->trabajador = $this->usuarioDePrueba();
    }

    public function test_deja_motivo_anterior_y_nuevo_en_el_historial(): void
    {
        $motivoSalud = $this->motivoDe('SALUD');
        $motivoParticular = $this->motivoDe('PARTICULAR');

        $papeleta = $this->papeletaDePrueba($this->trabajador, EnJustificacion::class, [
            'motivo_id' => $motivoSalud->id,
        ]);

        app(ReclasificarAParticularAction::class)->ejecutar(
            $papeleta,
            null,
            'sistema',
            'Sustento de salud vencido sin presentar.',
        );

        $papeleta->refresh();

        $this->assertTrue($papeleta->estado->equals(Finalizada::class));
        $this->assertSame($motivoParticular->id, $papeleta->motivo_id);
        $this->assertSame($motivoSalud->id, $papeleta->motivo_original_id);

        $historial = HistorialPapeleta::where('papeleta_id', $papeleta->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($motivoSalud->id, $historial->motivo_anterior_id);
        $this->assertSame($motivoParticular->id, $historial->motivo_nuevo_id);
    }
}
