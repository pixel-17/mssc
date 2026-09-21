<?php

namespace Tests\Feature\Configuraciones;

use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las claves que el código lee con Configuracion::valorDe('X') y las que el
 * seeder siembra (y por tanto el admin puede ver y editar) tienen que ser las
 * mismas. Un desajuste no da error: la pantalla de configuración deja de tener
 * efecto en silencio (ocurrió con RELOJ_JEFE_MINUTOS vs SLA_JEFE_MINUTOS).
 */
class ConfiguracionSinDesajusteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function clavesLeidasPorElCodigo(): array
    {
        $claves = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }

            preg_match_all("/Configuracion::valorDe\(\s*'([A-Z0-9_]+)'/", file_get_contents($archivo->getPathname()), $coincidencias);

            array_push($claves, ...$coincidencias[1]);
        }

        return array_values(array_unique($claves));
    }

    public function test_toda_clave_que_lee_el_codigo_esta_sembrada(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $sembradas = Configuracion::pluck('clave')->all();

        $this->assertSame([], array_values(array_diff($this->clavesLeidasPorElCodigo(), $sembradas)), 'claves leídas por el código pero no sembradas');
    }

    public function test_toda_clave_sembrada_la_lee_el_codigo(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $sembradas = Configuracion::pluck('clave')->all();

        $this->assertSame([], array_values(array_diff($sembradas, $this->clavesLeidasPorElCodigo())), 'claves sembradas que nadie lee: editarlas no hace nada');
    }
}
