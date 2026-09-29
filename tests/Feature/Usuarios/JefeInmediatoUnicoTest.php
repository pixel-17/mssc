<?php

namespace Tests\Feature\Usuarios;

use App\Models\JefeTurno;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * "Jefe inmediato" es un solo tipo: quien lo sea de un trabajador —por
 * su unidad (titular), por jefes_turno (728) o por asignación manual—
 * tiene exactamente las mismas capacidades y privilegios sobre él.
 */
class JefeInmediatoUnicoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $titular;

    private User $deUnidad;

    private User $manual;

    private User $ajeno;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->titular = $this->usuarioDePrueba();
        $this->deUnidad = $this->usuarioDePrueba();
        $this->manual = $this->usuarioDePrueba();
        $this->ajeno = $this->usuarioDePrueba();

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->titular->id]);
        JefeTurno::create(['unidad_organica_id' => $unidad->id, 'jefe_id' => $this->deUnidad->id]);

        $this->trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $this->trabajador->forceFill([
            'unidad_organica_id' => $unidad->id,
            'jefe_inmediato_id' => $this->titular->id,
        ])->saveQuietly();

        $this->trabajador->jefesInmediatosAdicionales()->attach($this->manual->id, [
            'asignado_por_id' => $this->titular->id,
        ]);

        $this->trabajador = $this->trabajador->fresh();
    }

    /** @return array<string, array{0: string}> */
    public static function origenes(): array
    {
        return [
            'por la unidad (titular)' => ['titular'],
            'por jefes_turno (728)' => ['deUnidad'],
            'asignado a mano' => ['manual'],
        ];
    }

    #[DataProvider('origenes')]
    public function test_los_tres_origenes_son_jefe_inmediato(string $propiedad): void
    {
        $this->assertTrue($this->{$propiedad}->esJefeInmediatoDe($this->trabajador));
        $this->assertFalse($this->ajeno->esJefeInmediatoDe($this->trabajador));
    }

    #[DataProvider('origenes')]
    public function test_los_tres_origenes_ven_y_editan_al_trabajador(string $propiedad): void
    {
        $policy = new UserPolicy;

        $this->assertTrue($policy->view($this->{$propiedad}, $this->trabajador));
        $this->assertTrue($policy->editar($this->{$propiedad}, $this->trabajador));

        $this->assertFalse($policy->view($this->ajeno, $this->trabajador));
        $this->assertFalse($policy->editar($this->ajeno, $this->trabajador));
    }

    #[DataProvider('origenes')]
    public function test_los_tres_origenes_pueden_gestionar_su_turno(string $propiedad): void
    {
        $this->assertTrue($this->{$propiedad}->puedeGestionarTurnoDe($this->trabajador));
        $this->assertFalse($this->ajeno->puedeGestionarTurnoDe($this->trabajador));
    }

    public function test_jefes_inmediatos_lista_a_todos_sin_distinguir_origen(): void
    {
        $ids = $this->trabajador->jefesInmediatos()->pluck('id')->sort()->values()->all();

        $this->assertSame(
            collect([$this->titular->id, $this->deUnidad->id, $this->manual->id])->sort()->values()->all(),
            $ids,
        );
    }
}
