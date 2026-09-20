<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class CabecerasDeSeguridadTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_las_paginas_publicas_llevan_las_cabeceras(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('Permissions-Policy');
    }

    public function test_permite_camara_y_gps_solo_desde_el_propio_origen(): void
    {
        $politica = $this->get('/login')->headers->get('Permissions-Policy');

        $this->assertStringContainsString('camera=(self)', $politica);
        $this->assertStringContainsString('geolocation=(self)', $politica);
        $this->assertStringContainsString('microphone=()', $politica);
    }

    public function test_las_paginas_con_sesion_tambien_las_llevan(): void
    {
        $this->actingAs($this->usuarioDePrueba())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_solo_en_produccion_y_sobre_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }
}
