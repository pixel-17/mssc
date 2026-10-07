<?php

namespace Tests\Feature\Usuarios;

use App\Actions\Usuario\CrearUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\ConfiguracionTurno;
use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Reglas de régimen al crear usuarios dentro de una unidad:
 * - Todos los jefes y trabajadores de una unidad son del mismo régimen
 *   que su jefe (CrearUsuarioRequest::regimenEsperado(), vía HTTP).
 * - Unidad 276: un solo jefe inmediato.
 * - Unidad 728: hasta 3 jefes en total (el primero queda como jefe_id,
 *   titular; todos —titular incluido— quedan en jefes_turno). Un jefe
 *   nuevo NACE SIN TURNO: el que cubre sale de su propia programación
 *   (ConfiguracionTurno para la unidad, Turno vigente para crear
 *   papeletas), así que un jefe solo resuelve para un turno una vez
 *   programado (ver programarTurno()). No hay chequeo de turno
 *   ocupado: si dos jefes cubren el mismo turno, AlertaJefaturaService
 *   avisa los huecos.
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

    /**
     * "Programa" el turno de un jefe recién creado (nace sin él): su
     * configuración de ciclo y el Turno vigente de hoy, que son las dos
     * fuentes que consultan UnidadOrganica y CrearPapeletaAction.
     */
    private function programarTurno(User $jefe, string $turno): void
    {
        ConfiguracionTurno::create([
            'user_id' => $jefe->id,
            'turno' => $turno,
            'fecha_ancla' => now()->toDateString(),
            'dias_trabajo' => 6,
            'dias_descanso' => 1,
        ]);

        $this->turnoDePrueba($jefe, ['turno' => $turno]);
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
        // Jefe de la unidad (728) que da el alta, como en TurnoInicialAlCrearTest.
        $jefe = $this->usuarioDePrueba(['regimen' => '728'], ['admin']);
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina 728', 'jefe_id' => $jefe->id]);
        $this->conUnidadHija($unidad);

        $this->actingAs($jefe)
            ->post(route('usuarios.store'), [
                'name' => 'Rosa',
                'apellido' => 'Quispe',
                'dni' => '70000001',
                'email' => 'u70000001@example.com',
                'regimen' => '276',
                'unidad_organica_id' => $unidad->id,
                'tipo' => 'trabajador',
            ])
            ->assertSessionHasErrors(['regimen']);

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

    public function test_en_una_unidad_728_el_primer_jefe_queda_como_jefe_id_y_en_jefes_turno(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $jefe = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000003', 'MANANA'));

        $this->assertSame($jefe->id, $unidad->fresh()->jefe_id);
        $this->assertDatabaseHas('jefes_turno', [
            'unidad_organica_id' => $unidad->id,
            'jefe_id' => $jefe->id,
        ]);

        // Nace sin turno: no resuelve para ninguno hasta que se programe el suyo.
        $this->assertFalse(ConfiguracionTurno::where('user_id', $jefe->id)->exists());
        $this->assertNull($unidad->fresh()->resolverJefeInmediato('MANANA'));

        $this->programarTurno($jefe, 'MANANA');

        $this->assertSame($jefe->id, $unidad->fresh()->resolverJefeInmediato('MANANA'));
        $this->assertNull($unidad->fresh()->resolverJefeInmediato('TARDE'));
    }

    public function test_una_unidad_728_admite_tres_jefes_uno_por_turno_y_no_un_cuarto(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $manana = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000004', 'MANANA'));
        $tarde = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000005', 'TARDE'));
        $noche = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000006', 'NOCHE'));

        $this->assertSame($manana->id, $unidad->fresh()->jefe_id, 'solo el primero es jefe_id');
        $this->assertSame(3, JefeTurno::where('unidad_organica_id', $unidad->id)->count());

        // Cada jefe cubre el turno de su propio calendario (vigente hoy).
        $this->programarTurno($manana, 'MANANA');
        $this->programarTurno($tarde, 'TARDE');
        $this->programarTurno($noche, 'NOCHE');

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

    public function test_dos_jefes_pueden_cubrir_el_mismo_turno_y_ambos_resuelven(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $primero = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000008', 'MANANA'));
        $segundo = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000009', 'MANANA'));

        $this->assertSame(2, JefeTurno::where('unidad_organica_id', $unidad->id)->count());

        $this->programarTurno($primero, 'MANANA');
        $this->programarTurno($segundo, 'MANANA');

        $ids = $unidad->fresh()->resolverJefesInmediatos('MANANA');
        sort($ids);

        $this->assertSame([$primero->id, $segundo->id], $ids);
    }

    public function test_un_jefe_desactivado_deja_de_resolver_y_su_turno_puede_recibir_un_reemplazo(): void
    {
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina nueva']);

        $anterior = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000010', 'MANANA'));
        $tarde = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000011', 'TARDE'));
        $this->programarTurno($tarde, 'TARDE');

        $this->assertSame($tarde->id, $unidad->fresh()->resolverJefeInmediato('TARDE'));

        $tarde->update(['activo' => false]);

        $this->assertNull($unidad->fresh()->resolverJefeInmediato('TARDE'));

        $reemplazo = $this->crear($this->datos($unidad->id, 'jefe_inmediato', '728', '70000012', 'TARDE'));
        $this->programarTurno($reemplazo, 'TARDE');

        $this->assertSame($anterior->id, $unidad->fresh()->jefe_id);
        $this->assertSame($reemplazo->id, $unidad->fresh()->resolverJefeInmediato('TARDE'));
    }
}
