<?php

namespace Tests\Feature\Organigrama;

use App\Actions\Organigrama\GuardarUnidadOrganicaAction;
use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Cambiar el jefe de una unidad, moverla de padre y activar/desactivar.
 *
 * Escenario: Gerencia (Gina) → Oficina A (Omar, con Carla) y Oficina B
 * (Beto, con Nora). Rosa es del régimen 276.
 */
class JefaturaUnidadTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    private User $gina;

    private User $omar;

    private User $beto;

    private User $carla;

    private User $nora;

    private UnidadOrganica $gerencia;

    private UnidadOrganica $oficinaA;

    private UnidadOrganica $oficinaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
        $this->gina = $this->usuarioDePrueba(['name' => 'Gina']);
        $this->omar = $this->usuarioDePrueba(['name' => 'Omar']);
        $this->beto = $this->usuarioDePrueba(['name' => 'Beto']);

        $this->gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->gina->id]);
        $this->oficinaA = UnidadOrganica::create(['nombre' => 'Oficina A', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->omar->id]);
        $this->oficinaB = UnidadOrganica::create(['nombre' => 'Oficina B', 'parent_id' => $this->gerencia->id, 'jefe_id' => $this->beto->id]);

        $this->carla = $this->usuarioDePrueba(['name' => 'Carla', 'unidad_organica_id' => $this->oficinaA->id]);
        $this->nora = $this->usuarioDePrueba(['name' => 'Nora', 'unidad_organica_id' => $this->oficinaB->id]);
    }

    /** @param array<string, mixed> $cambios */
    private function guardar(UnidadOrganica $unidad, array $cambios, bool $ubicarJefe = false): UnidadOrganica
    {
        return app(GuardarUnidadOrganicaAction::class)->ejecutar($this->admin, $unidad, [
            'nombre' => $unidad->nombre,
            'tipo' => $unidad->tipo,
            'parent_id' => $unidad->parent_id,
            'jefe_id' => $unidad->jefe_id,
            'activo' => $unidad->activo,
            ...$cambios,
        ], null, $ubicarJefe);
    }

    public function test_un_jefe_de_otro_regimen_no_puede_encabezar_una_unidad_con_personas(): void
    {
        $rosa = $this->usuarioDePrueba(['name' => 'Rosa', 'regimen' => '276']);

        $this->expectException(UsuarioException::class);

        $this->guardar($this->oficinaA, ['jefe_id' => $rosa->id]);
    }

    public function test_un_usuario_desactivado_no_puede_encabezar_una_unidad(): void
    {
        $this->nora->forceFill(['activo' => false])->save();

        $this->expectException(UsuarioException::class);

        $this->guardar($this->oficinaA, ['jefe_id' => $this->nora->id]);
    }

    public function test_el_jefe_nuevo_pasa_a_la_unidad_y_su_superior_es_el_de_la_unidad_padre(): void
    {
        $this->guardar($this->oficinaA, ['jefe_id' => $this->nora->id], true);

        $nora = $this->nora->fresh();
        $this->assertSame($this->oficinaA->id, $nora->unidad_organica_id);
        $this->assertSame($this->gina->id, $nora->jefe_inmediato_id);

        // Quienes quedan en la unidad pasan a depender de ella.
        $this->assertSame($this->nora->id, $this->carla->fresh()->jefe_inmediato_id);
    }

    public function test_sin_pasarlo_a_la_unidad_el_jefe_nuevo_conserva_su_unidad(): void
    {
        $this->guardar($this->oficinaA, ['jefe_id' => $this->nora->id], false);

        $this->assertSame($this->oficinaB->id, $this->nora->fresh()->unidad_organica_id);
    }

    public function test_no_se_pasa_a_la_unidad_a_quien_ya_encabeza_otra(): void
    {
        $this->expectException(UsuarioException::class);

        $this->guardar($this->oficinaA, ['jefe_id' => $this->beto->id], true);
    }

    public function test_mover_la_unidad_de_padre_recalcula_el_jefe_de_area_de_su_gente(): void
    {
        $otra = UnidadOrganica::create(['nombre' => 'Otra gerencia', 'jefe_id' => $this->usuarioDePrueba(['name' => 'Ajena'])->id]);

        $this->guardar($this->oficinaA, ['parent_id' => $otra->id]);

        $this->assertSame($otra->jefe_id, $this->carla->fresh()->jefe_area_id);
    }

    public function test_no_se_mueve_una_unidad_bajo_una_unidad_desactivada(): void
    {
        $inactiva = UnidadOrganica::create(['nombre' => 'Cerrada', 'activo' => false]);

        $this->expectException(UsuarioException::class);

        $this->guardar($this->oficinaA, ['parent_id' => $inactiva->id]);
    }

    public function test_no_se_desactiva_a_un_jefe_con_personas_activas_a_cargo(): void
    {
        $this->expectException(UsuarioException::class);

        app(CambiarEstadoUsuarioAction::class)->desactivar($this->admin, $this->omar);
    }

    public function test_se_desactiva_al_jefe_cuando_su_unidad_ya_no_tiene_personas_activas(): void
    {
        $this->carla->forceFill(['activo' => false])->save();

        app(CambiarEstadoUsuarioAction::class)->desactivar($this->admin, $this->omar);

        $this->assertFalse($this->omar->fresh()->activo);
    }

    public function test_reactivar_devuelve_el_acceso(): void
    {
        $this->nora->forceFill(['activo' => false])->save();

        app(CambiarEstadoUsuarioAction::class)->reactivar($this->admin, $this->nora);

        $this->assertTrue($this->nora->fresh()->activo);
    }
}
