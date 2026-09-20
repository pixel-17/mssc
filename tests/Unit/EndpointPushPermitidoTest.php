<?php

namespace Tests\Unit;

use App\Rules\EndpointPushPermitido;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EndpointPushPermitidoTest extends TestCase
{
    /** @return array<string, array{0: mixed}> */
    public static function permitidos(): array
    {
        return [
            'chrome/edge/android (FCM)' => ['https://fcm.googleapis.com/fcm/send/abc123'],
            'firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'],
            'firefox (subdominio)' => ['https://eu.push.services.mozilla.com/wpush/v2/abc'],
            'safari' => ['https://web.push.apple.com/QGxyz'],
            'edge windows' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'],
            'con puerto 443 explícito' => ['https://fcm.googleapis.com:443/fcm/send/abc'],
        ];
    }

    /** @return array<string, array{0: mixed}> */
    public static function rechazados(): array
    {
        return [
            'metadatos de la nube' => ['http://169.254.169.254/latest/meta-data/'],
            'localhost' => ['https://localhost/hook'],
            'ip privada' => ['https://10.0.0.5/push'],
            'host cualquiera' => ['https://evil.example.com/push'],
            'http en vez de https' => ['http://fcm.googleapis.com/fcm/send/abc'],
            'host permitido como prefijo' => ['https://fcm.googleapis.com.evil.com/x'],
            'host permitido en credenciales' => ['https://fcm.googleapis.com@evil.com/x'],
            'sufijo sin punto' => ['https://evilnotify.windows.com/x'],
            'puerto raro' => ['https://fcm.googleapis.com:8443/x'],
            'sin esquema' => ['fcm.googleapis.com/fcm/send/abc'],
            'vacío' => [''],
            'no es texto' => [['https://fcm.googleapis.com/x']],
        ];
    }

    #[DataProvider('permitidos')]
    public function test_acepta_los_servicios_de_push_conocidos(mixed $endpoint): void
    {
        $this->assertTrue((new EndpointPushPermitido)->esPermitido($endpoint));
    }

    #[DataProvider('rechazados')]
    public function test_rechaza_todo_lo_demas(mixed $endpoint): void
    {
        $this->assertFalse((new EndpointPushPermitido)->esPermitido($endpoint));
    }

    public function test_como_regla_de_validacion_falla_con_un_mensaje(): void
    {
        $mensaje = null;

        (new EndpointPushPermitido)->validate('endpoint', 'http://169.254.169.254/', function (string $texto) use (&$mensaje) {
            $mensaje = $texto;
        });

        $this->assertNotNull($mensaje);
    }
}
