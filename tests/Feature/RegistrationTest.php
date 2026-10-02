<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El registro público está deshabilitado a propósito (ver config/fortify.php):
 * los usuarios los da de alta un administrador o un jefe.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_registro_publico_no_existe(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertStatus(404);
    }
}
