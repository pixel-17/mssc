<?php

namespace Tests\Feature\Papeletas;

use App\Models\Papeleta;
use App\Models\Sustento;
use App\Models\User;
use App\States\Papeleta\RetornoPendienteSustento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Subida del sustento por el trabajador: el archivo nuevo nunca debe quedar
 * en el disco si la fila no llegó a referenciarlo.
 */
class SustentoSubidaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $trabajador;

    private Papeleta $papeleta;

    private Sustento $sustento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        Storage::fake('local');

        $this->trabajador = $this->usuarioDePrueba();
        $this->papeleta = $this->papeletaDePrueba($this->trabajador, RetornoPendienteSustento::class);
        $this->sustento = Sustento::create([
            'papeleta_id' => $this->papeleta->id,
            'fecha_limite' => now()->addDays(3),
            'estado' => 'pendiente',
        ]);
    }

    private function subir(?User $quien = null, ?Sustento $sustento = null)
    {
        return $this->actingAs($quien ?? $this->trabajador)->post(
            route('trabajador.papeletas.sustento.store', $sustento ?? $this->sustento),
            ['archivo' => UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf')],
        );
    }

    private function archivosGuardados(): array
    {
        return Storage::disk('local')->allFiles('papeletas/sustentos');
    }

    public function test_presentar_el_sustento_guarda_el_archivo_y_cambia_el_estado(): void
    {
        $this->subir()->assertRedirect(route('trabajador.papeletas.show', $this->papeleta->id));

        $sustento = $this->sustento->fresh();

        $this->assertSame('presentado', $sustento->estado);
        Storage::disk('local')->assertExists($sustento->archivo_path);
        $this->assertCount(1, $this->archivosGuardados());
    }

    public function test_un_sustento_ya_presentado_se_rechaza_sin_guardar_nada(): void
    {
        $this->sustento->update(['estado' => 'presentado', 'archivo_path' => 'papeletas/sustentos/original.pdf']);
        Storage::disk('local')->put('papeletas/sustentos/original.pdf', 'contenido original');

        $this->subir()->assertSessionHas('error');

        $this->assertSame(['papeletas/sustentos/original.pdf'], $this->archivosGuardados(), 'no debe aparecer un archivo nuevo');
        $this->assertSame('papeletas/sustentos/original.pdf', $this->sustento->fresh()->archivo_path);
    }

    public function test_volver_a_presentar_tras_una_observacion_apunta_al_archivo_nuevo_y_conserva_el_anterior(): void
    {
        Storage::disk('local')->put('papeletas/sustentos/observado.pdf', 'observado');
        $this->sustento->update(['estado' => 'pendiente', 'archivo_path' => 'papeletas/sustentos/observado.pdf']);

        $this->subir();

        $sustento = $this->sustento->fresh();

        $this->assertNotSame('papeletas/sustentos/observado.pdf', $sustento->archivo_path);
        Storage::disk('local')->assertExists($sustento->archivo_path);
        Storage::disk('local')->assertExists('papeletas/sustentos/observado.pdf'); // lo retira archivos:huerfanos tras el plazo de gracia
    }

    public function test_si_la_base_de_datos_falla_el_archivo_recien_subido_se_descarta(): void
    {
        Sustento::updating(fn () => throw new RuntimeException('fallo simulado de la base de datos'));

        $this->withoutExceptionHandling();

        try {
            $this->subir();
            $this->fail('Se esperaba la excepción simulada.');
        } catch (RuntimeException $e) {
            $this->assertSame('fallo simulado de la base de datos', $e->getMessage());
        }

        $this->assertSame([], $this->archivosGuardados());
        $this->assertSame('pendiente', $this->sustento->fresh()->estado);
    }

    public function test_otro_trabajador_no_puede_presentar_el_sustento_ajeno(): void
    {
        $this->subir($this->usuarioDePrueba())->assertForbidden();

        $this->assertSame([], $this->archivosGuardados());
    }
}
