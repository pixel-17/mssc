<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function registrar(string $endpoint)
    {
        return $this->actingAs($this->usuarioDePrueba())->postJson(route('push-subscriptions.store'), [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'clave-publica-de-prueba', 'auth' => 'secreto-de-prueba'],
        ]);
    }

    public function test_registra_una_suscripcion_de_un_servicio_de_push_real(): void
    {
        $this->registrar('https://fcm.googleapis.com/fcm/send/abc123')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123']);
    }

    public function test_rechaza_endpoints_internos_o_desconocidos_y_no_guarda_nada(): void
    {
        foreach (['http://169.254.169.254/latest/meta-data/', 'https://intranet.municipio.local/hook', 'https://evil.example.com/x'] as $endpoint) {
            $this->registrar($endpoint)->assertUnprocessable()->assertJsonValidationErrors('endpoint');
        }

        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
