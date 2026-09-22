<?php

namespace Tests\Feature\Papeletas;

use App\Actions\Papeleta\CrearPapeletaAction;
use App\Exceptions\PapeletaException;
use App\Models\Turno;
use App\Models\User;
use App\States\Papeleta\PendienteJefe;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Régimen 728: crear una papeleta exige SIEMPRE tener un turno vigente
 * cargado en `turnos` para hoy (fila real, y que no sea es_descanso).
 * Ya no es el interruptor MODO_ESTRICTO_728 que el admin podía
 * activar/desactivar: es la única regla, siempre activa. Fecha de
 * referencia: lunes 2026-09-21.
 */
class TurnoVigenteRequeridoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function ir(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
    }

    private function crear(User $trabajador): void
    {
        app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);
    }

    public function test_sin_ninguna_fila_de_turno_no_puede_crear_papeleta(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->ir('2026-09-21 10:00:00');

        $this->expectException(PapeletaException::class);
        $this->crear($trabajador);
    }

    public function test_con_fila_de_descanso_hoy_no_puede_crear_papeleta(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->ir('2026-09-21 10:00:00');

        $this->turnoDePrueba($trabajador, ['es_descanso' => true, 'hora_inicio' => null, 'hora_fin' => null]);

        $this->expectException(PapeletaException::class);
        $this->crear($trabajador);
    }

    public function test_con_turno_vigente_hoy_si_puede_crear_papeleta(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $this->ir('2026-09-21 10:00:00');

        $this->turnoDePrueba($trabajador);

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
    }

    /**
     * El turno Noche cargado ayer sigue vigente hasta su fin real hoy de
     * madrugada (Turno::vigenteParaUsuario resuelve el cruce de
     * medianoche): no exige una fila nueva para "hoy".
     */
    public function test_turno_noche_de_ayer_todavia_vigente_de_madrugada_si_permite_crear(): void
    {
        $trabajador = $this->usuarioDePrueba();

        Turno::create([
            'user_id' => $trabajador->id,
            'sede_id' => $this->sedeDePrueba()->id,
            'fecha' => '2026-09-20',
            'hora_inicio' => '22:00',
            'hora_fin' => '06:00',
            'es_descanso' => false,
            'turno' => 'NOCHE',
        ]);

        $this->ir('2026-09-21 02:00:00'); // madrugada: la noche empezó el 20

        $papeleta = app(CrearPapeletaAction::class)->ejecutar($trabajador, $this->motivoDe('PARTICULAR'), []);

        $this->assertTrue($papeleta->fresh()->estado->equals(PendienteJefe::class));
        $this->assertSame('2026-09-21 06:00:00', $papeleta->fresh()->fin_turno_at->format('Y-m-d H:i:s'));
    }
}
