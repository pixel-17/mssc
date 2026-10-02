<?php

namespace Tests\Feature\Usuarios;

use App\Models\UnidadOrganica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/** Etiqueta visual del tipo de usuario (User::tipoUsuario()). */
class TipoUsuarioTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_distingue_admin_rrhh_jefe_de_area_jefe_inmediato_y_trabajador(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $jefeArea = $this->usuarioDePrueba();
        $jefeInmediato = $this->usuarioDePrueba();
        $trabajador = $this->usuarioDePrueba();

        $raiz = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefeArea->id]);
        UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $raiz->id, 'jefe_id' => $jefeInmediato->id]);

        $this->assertSame('admin', $admin->tipoUsuario());
        $this->assertSame('rrhh', $rrhh->tipoUsuario());
        $this->assertSame('jefe_area', $jefeArea->fresh()->tipoUsuario());
        $this->assertSame('jefe_inmediato', $jefeInmediato->fresh()->tipoUsuario());
        $this->assertSame('trabajador', $trabajador->tipoUsuario());
        $this->assertSame('Jefe de área', $jefeArea->fresh()->etiquetaTipoUsuario());
    }
}
