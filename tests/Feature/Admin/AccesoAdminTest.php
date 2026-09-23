<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Las pantallas del catálogo de administración (Sedes, Motivos, Turnos,
 * Feriados, Unidades orgánicas, Configuraciones, Usuarios) cuelgan todas
 * del middleware `role:admin`: invitado -> login, cualquier otro rol -> 403,
 * admin -> 200. Se recorren de una sola vez porque comparten la misma regla.
 */
class AccesoAdminTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    /** @var array<int, string> */
    private const RUTAS = [
        'catalogos.index',
        'sedes.index',
        'sedes.crear',
        'motivos.index',
        'motivos.crear',
        'turnos.index',
        'turnos.crear',
        'feriados.index',
        'feriados.crear',
        'unidades-organicas.index',
        'unidades-organicas.crear',
        'configuraciones.index',
        'usuarios-admin.index',
        'usuarios-admin.crear',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_un_invitado_es_enviado_al_login_en_todas_las_pantallas_de_admin(): void
    {
        foreach (self::RUTAS as $ruta) {
            $this->get(route($ruta))->assertRedirect(route('login'));
        }
    }

    public function test_trabajador_y_rrhh_reciben_403_en_todas_las_pantallas_de_admin(): void
    {
        foreach (['trabajador', 'rrhh'] as $rol) {
            $usuario = $this->usuarioDePrueba([], [$rol]);

            foreach (self::RUTAS as $ruta) {
                $this->actingAs($usuario)->get(route($ruta))->assertForbidden();
            }
        }
    }

    public function test_el_admin_abre_todas_las_pantallas_de_admin(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        foreach (self::RUTAS as $ruta) {
            $this->actingAs($admin)->get(route($ruta))->assertOk();
        }
    }

    public function test_el_indice_de_catalogos_enlaza_a_los_siete_catalogos(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->actingAs($admin)->get(route('catalogos.index'))
            ->assertOk()
            ->assertSee('Sedes')
            ->assertSee('Motivos')
            ->assertSee('Turnos')
            ->assertSee('Feriados')
            ->assertSee('Unidades orgánicas')
            ->assertSee('Configuraciones')
            ->assertSee('Usuarios');
    }
}
