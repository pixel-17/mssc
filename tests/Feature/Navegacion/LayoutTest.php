<?php

namespace Tests\Feature\Navegacion;

use App\Livewire\Navegacion\InsigniaBandeja;
use App\Models\UnidadOrganica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Los layouts ahora reciben su navegación de NavegacionComposer y arman el
 * <title> con TituloDePagina. Estas pruebas renderizan páginas reales para
 * cubrir composer + Blade + parciales de una sola vez.
 */
class LayoutTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function titulo(string $html): ?string
    {
        return preg_match('#<title>(.*?)</title>#su', $html, $m) ? html_entity_decode(trim($m[1])) : null;
    }

    public function test_la_pagina_de_login_tiene_titulo_propio_y_enlace_de_salto(): void
    {
        $html = $this->get('/login')->assertOk()->assertSee('Saltar al contenido')->getContent();

        $this->assertStringStartsWith('Iniciar sesión — ', $this->titulo($html));
    }

    public function test_el_admin_ve_el_sidebar_de_administracion_y_un_titulo_de_pagina(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);

        $respuesta = $this->actingAs($admin)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee('Cuentas de usuario')
            ->assertSee('Saltar al contenido')
            ->assertSee('id="contenido"', false);

        $this->assertStringContainsString(' — ', $this->titulo($respuesta->getContent()), 'el <title> lleva el nombre de la página');
    }

    public function test_el_jefe_ve_su_bandeja_con_el_contador_en_vivo(): void
    {
        $jefe = $this->usuarioDePrueba();
        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $jefe->id]);
        $jefe->update(['unidad_organica_id' => $unidad->id]);

        $this->actingAs($jefe)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Jefatura')
            ->assertSee('Papeletas de mi equipo')
            ->assertSeeLivewire(InsigniaBandeja::class);
    }

    public function test_rrhh_ve_la_bandeja_de_rrhh(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $this->actingAs($rrhh)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Recursos Humanos')
            ->assertSee('Papeletas de RR. HH.')
            ->assertSeeLivewire(InsigniaBandeja::class);
    }

    public function test_el_trabajador_sin_gente_a_cargo_usa_la_barra_inferior(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $this->actingAs($trabajador)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('bottom-nav', false)
            ->assertSee('Nueva')
            ->assertDontSee('Cuentas de usuario');
    }

    public function test_ya_no_se_carga_ninguna_fuente_externa(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('fonts.bunny.net');
    }
}
