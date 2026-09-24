<?php

namespace Tests\Feature\Usuarios;

use App\Actions\Usuario\CrearUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Reglas de régimen al crear usuarios dentro de una unidad:
 * - Todos los jefes y trabajadores de una unidad son del mismo régimen
 *   que su jefe (UnidadOrganica::regimen()).
 * - Unidad 276: un solo jefe inmediato.
 * - Unidad 728: hasta 3 jefes, uno por turno (el primero queda como
 *   jefe_id, y todos quedan registrados en jefes_turno).
 */
class JefesPorRegimenTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $creador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        // Quien da el alta (jefe de área). Su propia unidad no interviene:
        // cada test usa la unidad destino que necesita.
        $this->creador = $this->usuarioDePrueba();
    }

    /** @return array<string, mixed> */
    private function datos(int $unidadId, string $tipo, string $regimen, string $dni, ?string $turno = null): array
    {
        return [
            'name' => 'Rosa',
            'apellido' => 'Quispe',
            'dni' => $dni,
            'email' => "u{$dni}@example.com",
            'regimen' => $regimen,
            'sede_id' => $this->sedeDePrueba()->id,
            'unidad_organica_id' => $unidadId,
            'tipo' => $tipo,
            'turno' => $turno,
            'fecha_ancla' => now()->toDateString(),
        ];
    }

    private function crear(array $datos): User
    {
        return app(CrearUsuarioAction::class)->ejecutar($this->creador, $datos, true);
    }

    public function test_un_trabajador_debe_tener_el_mismo_regimen_que_su_unidad(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina 728', 'jefe_id' => $jefe->id]);

        try {
            $this->crear($this->datos($unidad->id, 'trabajador', '276', '70000001'));

            $this->fail('Debió rechazar un trabajador 276 en una unidad 728.');
        } catch (UsuarioException $e) {
            $this->assertStringContainsString('mismo régimen', $e->getMessage());
        }

        $this->assertNull(User::where('dni', '70000001')->first());
    }

    public function test_una_unidad_276_solo_admite_un_jefe_inmediato(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina 276', 'jefe_id' => $jefe->id]);

        try {
            $this->crear($this->datos($unidad->id, 'jefe_inmediato', '276', '70000002'));

            $this->fail('Debió rechazar un segundo jefe en una unidad 276.');
        } catch (UsuarioException $e) {
            $this->assertStringContainsString('ya tiene su jefe inmediato', $e->getMessage());
        }

        $this->assertSame($jefe->id, $unidad->fresh()->jefe_id);
        $this->assertNull(User::where('dni', '70000002')->first());
    }

    public function test_en_una_unidad_728_el_primer_jefe_queda_como_jefe_id_y_jefe_de_su_turno(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $jefe = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000003', 'MANANA'));

        $this->assertSame($jefe->id, $unidad->fresh()->jefe_id);
        $this->assertDatabaseHas('jefes_turno', [
            'unidad_organica_id' => $unidad->id,
            'turno' => 'MANANA',
            'jefe_id' => $jefe->id,
        ]);
    }

    public function test_una_unidad_728_admite_tres_jefes_uno_por_turno_y_no_un_cuarto(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $manana = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000004', 'MANANA'));
        $tarde = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000005', 'TARDE'));
        $noche = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000006', 'NOCHE'));

        $this->assertSame($manana->id, $unidad->fresh()->jefe_id, 'solo el primero es jefe_id');
        $this->assertSame(3, JefeTurno::where('unidad_organica_id', $unidad->id)->count());
        $this->assertSame($tarde->id, $unidad->fresh()->resolverJefeInmediato('TARDE'));
        $this->assertSame($noche->id, $unidad->fresh()->resolverJefeInmediato('NOCHE'));

        try {
            $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000007', 'MANANA'));

            $this->fail('Debió rechazar un cuarto jefe.');
        } catch (UsuarioException $e) {
            $this->assertStringContainsString('ya tiene sus 3 jefes inmediatos', $e->getMessage());
        }

        $this->assertNull(User::where('dni', '70000007')->first());
    }

    public function test_no_se_puede_asignar_un_turno_que_ya_tiene_jefe_activo(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000008', 'MANANA'));

        try {
            $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000009', 'MANANA'));

            $this->fail('Debió rechazar un segundo jefe para el mismo turno.');
        } catch (UsuarioException $e) {
            $this->assertStringContainsString('ya tiene jefe inmediato', $e->getMessage());
        }

        $this->assertNull(User::where('dni', '70000009')->first());
        $this->assertSame(1, JefeTurno::where('unidad_organica_id', $unidad->id)->count());
    }

    public function test_un_turno_cuyo_jefe_fue_desactivado_puede_recibir_un_jefe_nuevo(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $anterior = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000010', 'MANANA'));
        $tarde = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000011', 'TARDE'));

        $tarde->update(['activo' => false]);

        $reemplazo = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000012', 'TARDE'));

        $this->assertSame($anterior->id, $unidad->fresh()->jefe_id);
        $this->assertSame($reemplazo->id, $unidad->fresh()->resolverJefeInmediato('TARDE'));
    }
}
