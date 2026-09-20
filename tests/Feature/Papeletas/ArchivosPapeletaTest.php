<?php

namespace Tests\Feature\Papeletas;

use App\Models\Papeleta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * GET /papeletas/{papeleta}/archivo/{tipo}: quién puede abrir el adjunto
 * inicial, la foto del retorno y el adjunto de subsanación.
 */
class ArchivosPapeletaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private const ADJUNTO = 'papeletas/adjuntos-iniciales/prueba.pdf';

    private User $jefe;

    private User $trabajador;

    private Papeleta $papeleta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        Storage::fake('local');
        Storage::disk('local')->put(self::ADJUNTO, '%PDF-1.4 contenido de prueba');

        $this->jefe = $this->usuarioDePrueba();
        $this->trabajador = $this->usuarioDePrueba(['jefe_inmediato_id' => $this->jefe->id]);

        $this->papeleta = $this->papeletaDePrueba($this->trabajador, atributos: [
            'adjunto_inicial_path' => self::ADJUNTO,
        ]);
    }

    private function url(string $tipo = 'adjunto-inicial', ?Papeleta $papeleta = null): string
    {
        return route('papeletas.archivo', ['papeleta' => $papeleta ?? $this->papeleta, 'tipo' => $tipo]);
    }

    public function test_el_trabajador_dueno_abre_su_adjunto_con_nosniff(): void
    {
        $this->actingAs($this->trabajador)
            ->get($this->url())
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_su_jefe_inmediato_y_rrhh_tambien_pueden_abrirlo(): void
    {
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);

        $this->actingAs($this->jefe)->get($this->url())->assertOk();
        $this->actingAs($rrhh)->get($this->url())->assertOk();
    }

    public function test_otro_trabajador_y_el_admin_no_pueden_abrirlo(): void
    {
        $ajeno = $this->usuarioDePrueba();
        $admin = $this->usuarioDePrueba([], ['admin']);

        $this->actingAs($ajeno)->get($this->url())->assertForbidden();
        $this->actingAs($admin)->get($this->url())->assertForbidden();
    }

    public function test_un_tipo_desconocido_o_un_archivo_inexistente_dan_404(): void
    {
        $this->actingAs($this->trabajador);

        $this->get(route('papeletas.archivo', ['papeleta' => $this->papeleta, 'tipo' => 'cualquier-cosa']))
            ->assertNotFound();

        // Sin foto de retorno registrada.
        $this->get($this->url('retorno-foto'))->assertNotFound();

        // La ruta está en la BD pero el archivo ya no está en el disco.
        Storage::disk('local')->delete(self::ADJUNTO);
        $this->get($this->url())->assertNotFound();
    }

    public function test_la_foto_del_retorno_se_sirve_con_la_misma_autorizacion(): void
    {
        Storage::disk('local')->put('papeletas/retornos/foto.jpg', 'jpg-de-prueba');

        DB::table('retornos')->insert([
            'papeleta_id' => $this->papeleta->id,
            'foto_path' => 'papeletas/retornos/foto.jpg',
            'hora_servidor' => now(),
            'marcado_manual' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->jefe)->get($this->url('retorno-foto'))->assertOk();
        $this->actingAs($this->usuarioDePrueba())->get($this->url('retorno-foto'))->assertForbidden();
    }

    public function test_el_detalle_del_trabajador_enlaza_sus_archivos(): void
    {
        $this->actingAs($this->trabajador)
            ->get(route('trabajador.papeletas.show', $this->papeleta))
            ->assertOk()
            ->assertSee($this->url(), false)
            ->assertSee('Adjunto inicial');
    }
}
