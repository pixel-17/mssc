<?php

namespace Tests\Feature\Papeletas;

use App\Models\Sustento;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class LimpiarArchivosHuerfanosTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        Storage::fake('local');
    }

    /** Crea un archivo con la antigüedad indicada (en días). */
    private function archivo(string $ruta, int $dias = 40): string
    {
        Storage::disk('local')->put($ruta, 'contenido');
        touch(Storage::disk('local')->path($ruta), now()->subDays($dias)->getTimestamp());

        return $ruta;
    }

    public function test_sin_borrar_solo_lista_y_no_toca_nada(): void
    {
        $huerfano = $this->archivo('papeletas/adjuntos-iniciales/suelto.pdf');

        $this->artisan('archivos:huerfanos')
            ->expectsOutputToContain($huerfano)
            ->assertSuccessful();

        Storage::disk('local')->assertExists($huerfano);
    }

    public function test_borra_solo_los_huerfanos_viejos_y_respeta_los_referenciados(): void
    {
        $trabajador = $this->usuarioDePrueba();

        $conAdjunto = $this->archivo('papeletas/adjuntos-iniciales/con-papeleta.pdf');
        $this->papeletaDePrueba($trabajador, atributos: ['adjunto_inicial_path' => $conAdjunto]);

        $conFoto = $this->archivo('papeletas/retornos/con-retorno.jpg');
        $otra = $this->papeletaDePrueba($this->usuarioDePrueba());
        DB::table('retornos')->insert([
            'papeleta_id' => $otra->id,
            'foto_path' => $conFoto,
            'hora_servidor' => now(),
            'marcado_manual' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $conSustento = $this->archivo('papeletas/sustentos/con-sustento.pdf');
        $tercera = $this->papeletaDePrueba($this->usuarioDePrueba(), RetornoPendienteSustento::class);
        Sustento::create(['papeleta_id' => $tercera->id, 'archivo_path' => $conSustento, 'fecha_limite' => now()->addDay(), 'estado' => 'presentado']);

        $huerfanoViejo = $this->archivo('papeletas/sustentos/huerfano-viejo.pdf', 40);
        $huerfanoReciente = $this->archivo('papeletas/sustentos/huerfano-reciente.pdf', 2);
        $fueraDeCarpetas = $this->archivo('otra-carpeta/no-me-toques.txt', 400);

        $this->artisan('archivos:huerfanos', ['--borrar' => true])->assertSuccessful();

        Storage::disk('local')->assertMissing($huerfanoViejo);
        Storage::disk('local')->assertExists([$conAdjunto, $conFoto, $conSustento, $huerfanoReciente, $fueraDeCarpetas]);
    }

    public function test_el_plazo_de_gracia_es_configurable(): void
    {
        $reciente = $this->archivo('papeletas/retornos/suelta.jpg', 2);

        $this->artisan('archivos:huerfanos', ['--borrar' => true, '--dias' => 1])->assertSuccessful();

        Storage::disk('local')->assertMissing($reciente);
    }
}
