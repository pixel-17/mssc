<?php

namespace Tests\Feature\Usuarios;

use App\Actions\Usuario\VincularJefeInmediatoAction;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un jefe puede agregar a su equipo (como jefe inmediato adicional) a un
 * trabajador de otra área, pero NO a un jefe de área ni a un administrador.
 */
class VinculoJefeTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $jefe;

    private User $trabajadorAjeno;

    private User $jefeDeArea;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->jefe = $this->usuarioDePrueba();
        $this->trabajadorAjeno = $this->usuarioDePrueba();
        $this->jefeDeArea = $this->usuarioDePrueba();
        $this->admin = $this->usuarioDePrueba([], ['admin']);

        UnidadOrganica::create(['nombre' => 'Oficina propia', 'jefe_id' => $this->jefe->id]);
        UnidadOrganica::create(['nombre' => 'Gerencia ajena', 'jefe_id' => $this->jefeDeArea->id]);
    }

    private function accion(): VincularJefeInmediatoAction
    {
        return app(VincularJefeInmediatoAction::class);
    }

    public function test_puede_agregar_a_un_trabajador_comun_de_otra_area(): void
    {
        $this->accion()->vincular($this->jefe, $this->trabajadorAjeno, confirmado: true);

        $this->assertTrue($this->jefe->esJefeInmediatoDe($this->trabajadorAjeno->fresh()));
    }

    public function test_no_puede_agregar_a_un_jefe_de_area(): void
    {
        $this->assertThrows(
            fn () => $this->accion()->vincular($this->jefe, $this->jefeDeArea, confirmado: true),
            UsuarioException::class,
            'No puedes agregar a tu equipo a un jefe de área',
        );

        $this->assertFalse($this->jefe->esJefeInmediatoDe($this->jefeDeArea->fresh()));
    }

    public function test_no_puede_agregar_a_un_administrador(): void
    {
        $this->assertThrows(
            fn () => $this->accion()->vincular($this->jefe, $this->admin, confirmado: true),
            UsuarioException::class,
            'ni a un administrador',
        );

        $this->assertFalse($this->jefe->esJefeInmediatoDe($this->admin->fresh()));
    }

    public function test_no_puede_agregarse_a_si_mismo_ni_agregar_a_un_desactivado(): void
    {
        $this->assertThrows(
            fn () => $this->accion()->vincular($this->jefe, $this->jefe, confirmado: true),
            UsuarioException::class,
            'No puedes agregarte a ti mismo',
        );

        $inactivo = $this->usuarioDePrueba(['activo' => false]);

        $this->assertThrows(
            fn () => $this->accion()->vincular($this->jefe, $inactivo, confirmado: true),
            UsuarioException::class,
            'usuario desactivado',
        );
    }

    public function test_la_busqueda_por_dni_trata_a_los_no_vinculables_como_no_encontrados(): void
    {
        $accion = $this->accion();

        $this->assertNotNull($accion->buscarPorDni($this->trabajadorAjeno->dni));
        $this->assertNull($accion->buscarPorDni($this->jefeDeArea->dni), 'no debe revelar quién es jefe de área');
        $this->assertNull($accion->buscarPorDni($this->admin->dni), 'no debe revelar quién es admin');
        $this->assertNull($accion->buscarPorDni('00000000'));
    }

    public function test_por_http_el_dni_de_un_jefe_de_area_da_el_mismo_mensaje_que_uno_inexistente(): void
    {
        $this->actingAs($this->jefe)
            ->post(route('usuarios.buscar'), ['dni' => $this->jefeDeArea->dni])
            ->assertSessionHas('error', 'No se encontró ningún usuario con ese DNI.');

        $this->actingAs($this->jefe)
            ->post(route('usuarios.buscar'), ['dni' => $this->trabajadorAjeno->dni])
            ->assertOk()
            ->assertSee($this->trabajadorAjeno->name);
    }

    public function test_por_http_vincular_a_un_admin_se_rechaza_aunque_se_fuerce_la_peticion(): void
    {
        $this->actingAs($this->jefe)
            ->post(route('usuarios.vincular', $this->admin), ['confirmado' => 1])
            ->assertSessionHas('error');

        $this->assertFalse($this->jefe->esJefeInmediatoDe($this->admin->fresh()));
    }
}
