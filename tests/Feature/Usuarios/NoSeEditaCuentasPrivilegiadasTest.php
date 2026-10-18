<?php

namespace Tests\Feature\Usuarios;

use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Quien edita a un usuario puede cambiarle el correo y, con "olvidé mi
 * contraseña", tomar su cuenta. Por eso solo un admin puede editar a un
 * usuario con rol admin o rrhh: un jefe (de área o inmediato) no, aunque
 * esa persona esté en su área o sea su subordinada.
 */
class NoSeEditaCuentasPrivilegiadasTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefeArea;

    private User $jefeInmediato;

    private User $rrhh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefeArea = $this->usuarioDePrueba();
        $this->jefeInmediato = $this->usuarioDePrueba();

        $gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->jefeArea->id]);
        $oficina = UnidadOrganica::create([
            'nombre' => 'Oficina de Recursos Humanos',
            'parent_id' => $gerencia->id,
            'jefe_id' => $this->jefeInmediato->id,
        ]);

        $this->rrhh = $this->usuarioDePrueba(
            ['regimen' => '276', 'unidad_organica_id' => $oficina->id, 'jefe_inmediato_id' => $this->jefeInmediato->id],
            ['trabajador', 'rrhh'],
        );
    }

    public function test_el_jefe_de_area_no_puede_editar_a_un_usuario_de_rrhh_de_su_area(): void
    {
        $this->assertFalse($this->jefeArea->fresh()->can('editar', $this->rrhh));
    }

    public function test_el_jefe_inmediato_no_puede_editar_a_un_usuario_de_rrhh_subordinado(): void
    {
        $this->assertFalse($this->jefeInmediato->fresh()->can('editar', $this->rrhh));
    }

    public function test_nadie_salvo_admin_edita_a_otro_admin(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->assertFalse($this->jefeArea->fresh()->can('editar', $admin));
    }

    public function test_el_admin_si_puede_editar_a_un_usuario_de_rrhh(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->assertTrue($admin->can('editar', $this->rrhh));
    }

    public function test_el_jefe_de_area_sigue_editando_a_un_trabajador_comun_de_su_area(): void
    {
        $trabajador = $this->usuarioDePrueba([
            'regimen' => '276',
            'unidad_organica_id' => UnidadOrganica::where('nombre', 'Oficina de Recursos Humanos')->value('id'),
        ]);

        $this->assertTrue($this->jefeArea->fresh()->can('editar', $trabajador));
    }
}
