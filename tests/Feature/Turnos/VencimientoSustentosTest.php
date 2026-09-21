<?php

namespace Tests\Feature\Turnos;

use App\Models\Papeleta;
use App\Models\Sustento;
use App\States\Papeleta\ReclasificadoAParticular;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class VencimientoSustentosTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function sustentoConPlazo(string $estado, string $plazo): Sustento
    {
        $papeleta = $this->papeletaDePrueba($this->usuarioDePrueba(), RetornoPendienteSustento::class);

        return Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => $plazo,
            'estado' => $estado,
            'archivo_path' => $estado === 'presentado' ? 'papeletas/sustentos/x.pdf' : null,
        ]);
    }

    public function test_un_sustento_pendiente_vencido_reclasifica_la_papeleta_a_particular(): void
    {
        $sustento = $this->sustentoConPlazo('pendiente', now()->subHour()->toDateTimeString());

        $this->artisan('papeletas:procesar-vencimiento-sustentos')->assertSuccessful();

        $this->assertSame('vencido', $sustento->fresh()->estado);
        $this->assertTrue(Papeleta::find($sustento->papeleta_id)->estado->equals(ReclasificadoAParticular::class));
    }

    public function test_un_sustento_dentro_de_plazo_no_se_toca(): void
    {
        $sustento = $this->sustentoConPlazo('pendiente', now()->addDay()->toDateTimeString());

        $this->artisan('papeletas:procesar-vencimiento-sustentos')->assertSuccessful();

        $this->assertSame('pendiente', $sustento->fresh()->estado);
        $this->assertTrue(Papeleta::find($sustento->papeleta_id)->estado->equals(RetornoPendienteSustento::class));
    }

    public function test_un_sustento_ya_presentado_no_se_marca_como_vencido(): void
    {
        $sustento = $this->sustentoConPlazo('presentado', now()->subHour()->toDateTimeString());

        $this->artisan('papeletas:procesar-vencimiento-sustentos')->assertSuccessful();

        $this->assertSame('presentado', $sustento->fresh()->estado, 'el archivo presentado a tiempo no se pisa');
        $this->assertTrue(Papeleta::find($sustento->papeleta_id)->estado->equals(RetornoPendienteSustento::class));
        $this->assertTrue((bool) Papeleta::find($sustento->papeleta_id)->requiere_visto_bueno, 'queda para revisión humana');
    }
}
