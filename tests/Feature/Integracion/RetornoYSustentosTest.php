<?php

namespace Tests\Feature\Integracion;

use App\Models\Retorno;
use App\Models\Sustento;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\AutorizadaYCorriendo;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\EnJustificacion;
use App\States\Papeleta\Finalizada;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

class RetornoYSustentosTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $rrhh;

    private User $admin;

    private User $jefe;

    private User $trabajador;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
        $this->seed(ConfiguracionSeeder::class);
        $this->travelTo(Carbon::parse('2026-09-21 10:00:00'));
        Storage::fake('local');

        $this->rrhh = $this->usuarioDePrueba(['regimen' => '276'], ['rrhh']);
        $this->admin = $this->usuarioDePrueba(['regimen' => '276'], ['admin']);
        $this->jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $this->trabajador = $this->usuarioDePrueba(['regimen' => '728']);
        $this->ajeno = $this->usuarioDePrueba(['regimen' => '728']);

        $unidad = UnidadOrganica::create(['nombre' => 'Oficina', 'jefe_id' => $this->jefe->id]);
        $this->jefe->update(['unidad_organica_id' => $unidad->id]);
        $this->trabajador->update(['unidad_organica_id' => $unidad->id]);

        $this->jefe = $this->jefe->fresh();
        $this->trabajador = $this->trabajador->fresh();
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    /**
     * JPEG válido de 1x1 px generado a mano: así la prueba no depende de
     * la extensión GD (que UploadedFile::fake()->image() sí necesita).
     */
    private function fotoDePrueba(): UploadedFile
    {
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////'
            .'wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
        );

        return UploadedFile::fake()->createWithContent('retorno.jpg', $jpeg);
    }

    private function datosRetorno(array $extra = []): array
    {
        return [
            'foto' => $this->fotoDePrueba(),
            'latitud' => -13.5170000,
            'longitud' => -71.9780000,
            ...$extra,
        ];
    }

    public function test_el_trabajador_marca_su_retorno_con_foto_y_gps_y_la_papeleta_deja_de_estar_en_curso(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno())
            ->assertRedirect(route('trabajador.papeletas.show', $papeleta))
            ->assertSessionHas('success');

        $papeleta->refresh();
        $this->assertFalse($papeleta->estado->equals(AutorizadaYCorriendo::class));
        $this->assertTrue($papeleta->estado->equals(Finalizada::class) || $papeleta->estado->equals(Cerrada::class));

        $retorno = Retorno::where('papeleta_id', $papeleta->id)->firstOrFail();
        $this->assertFalse((bool) $retorno->marcado_manual);
        $this->assertNotNull($retorno->foto_path);
        Storage::disk('local')->assertExists($retorno->foto_path);
    }

    public function test_el_retorno_valida_foto_y_coordenadas(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), [])
            ->assertSessionHasErrors(['foto', 'latitud', 'longitud']);

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno(['latitud' => 120]))
            ->assertSessionHasErrors('latitud');

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno([
                'foto' => UploadedFile::fake()->create('documento.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('foto');

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
        $this->assertSame(0, Retorno::count());
    }

    public function test_solo_el_dueno_puede_marcar_el_retorno_y_no_se_puede_marcar_dos_veces(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        foreach ([$this->ajeno, $this->jefe, $this->rrhh, $this->admin] as $usuario) {
            $this->actingAs($usuario)
                ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno())
                ->assertForbidden();
        }
        $this->assertSame(0, Retorno::count());

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno())
            ->assertSessionHas('success');

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno())
            ->assertSessionHas('error');

        $this->assertSame(1, Retorno::count());
    }

    public function test_un_retorno_rechazado_no_deja_la_foto_huerfana_en_disco(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno())
            ->assertSessionHas('success');

        $antes = count(Storage::disk('local')->allFiles('papeletas/retornos'));

        $this->actingAs($this->trabajador)
            ->post(route('trabajador.papeletas.retorno.store', $papeleta), $this->datosRetorno())
            ->assertSessionHas('error');

        $this->assertCount($antes, Storage::disk('local')->allFiles('papeletas/retornos'));
    }

    public function test_el_jefe_inmediato_registra_retorno_manual_con_justificacion(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.retorno-manual', $papeleta), ['justificacion' => 'corta'])
            ->assertSessionHasErrors('justificacion');

        $this->actingAs($this->jefe)
            ->post(route('jefe.papeletas.retorno-manual', $papeleta), ['justificacion' => 'Falla de conectividad en la sede.'])
            ->assertSessionHas('success');

        $this->assertFalse($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));

        $retorno = Retorno::where('papeleta_id', $papeleta->id)->firstOrFail();
        $this->assertTrue((bool) $retorno->marcado_manual);
        $this->assertSame($this->jefe->id, $retorno->marcado_manual_por_id);
        $this->assertNull($retorno->foto_path);
    }

    public function test_retorno_manual_solo_lo_hace_el_jefe_inmediato_del_trabajador(): void
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, AutorizadaYCorriendo::class);
        $datos = ['justificacion' => 'Falla de conectividad en la sede.'];

        foreach ([$this->ajeno, $this->trabajador, $this->rrhh, $this->admin] as $usuario) {
            $this->actingAs($usuario)
                ->post(route('jefe.papeletas.retorno-manual', $papeleta), $datos)
                ->assertForbidden();
        }

        $this->assertTrue($papeleta->fresh()->estado->equals(AutorizadaYCorriendo::class));
        $this->assertSame(0, Retorno::count());
    }

    private function sustentoPresentado(?string $contenido = 'contenido-del-sustento'): Sustento
    {
        $papeleta = $this->papeletaDePrueba($this->trabajador, EnJustificacion::class);
        $ruta = null;

        if ($contenido !== null) {
            $ruta = 'papeletas/sustentos/prueba.pdf';
            Storage::disk('local')->put($ruta, $contenido);
        }

        return Sustento::create([
            'papeleta_id' => $papeleta->id,
            'archivo_path' => $ruta,
            'fecha_limite' => now()->addDays(2),
            'estado' => $contenido !== null ? 'presentado' : 'pendiente',
            'presentado_at' => $contenido !== null ? now() : null,
        ]);
    }

    public function test_el_archivo_del_sustento_solo_lo_ven_los_autorizados(): void
    {
        $sustento = $this->sustentoPresentado();

        foreach ([$this->trabajador, $this->rrhh, $this->admin, $this->jefe] as $usuario) {
            $this->actingAs($usuario)
                ->get(route('sustentos.archivo', $sustento))
                ->assertOk();
        }

        $this->actingAs($this->ajeno)->get(route('sustentos.archivo', $sustento))->assertForbidden();
    }

    public function test_un_invitado_no_puede_ver_el_archivo_de_un_sustento(): void
    {
        $sustento = $this->sustentoPresentado();

        $this->get(route('sustentos.archivo', $sustento))->assertRedirect(route('login'));
    }

    public function test_el_sustento_sin_archivo_responde_404(): void
    {
        $sustento = $this->sustentoPresentado(null);

        $this->actingAs($this->trabajador)
            ->get(route('sustentos.archivo', $sustento))
            ->assertNotFound();
    }

    public function test_rrhh_aprueba_el_sustento_y_la_papeleta_se_cierra(): void
    {
        $sustento = $this->sustentoPresentado();

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.sustentos.revisar', $sustento), ['resultado' => 'aprobado'])
            ->assertSessionHas('success');

        $this->assertSame('aprobado', $sustento->fresh()->estado);
        $this->assertTrue($sustento->papeleta->fresh()->estado->equals(Cerrada::class));
    }

    public function test_observar_o_enviar_a_particular_exige_comentario(): void
    {
        $sustento = $this->sustentoPresentado();

        foreach (['observado', 'particular'] as $resultado) {
            $this->actingAs($this->rrhh)
                ->post(route('rrhh.sustentos.revisar', $sustento), ['resultado' => $resultado])
                ->assertSessionHasErrors('comentario');
        }

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.sustentos.revisar', $sustento), ['resultado' => 'invalido'])
            ->assertSessionHasErrors('resultado');

        $this->assertTrue($sustento->papeleta->fresh()->estado->equals(EnJustificacion::class));
    }

    public function test_rrhh_observa_el_sustento_y_este_vuelve_a_pendiente_para_que_el_trabajador_lo_reenvie(): void
    {
        $sustento = $this->sustentoPresentado();

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.sustentos.revisar', $sustento), [
                'resultado' => 'observado',
                'comentario' => 'El documento no es legible.',
            ])
            ->assertSessionHas('success');

        $this->assertSame('pendiente', $sustento->fresh()->estado);
        $this->assertTrue($sustento->papeleta->fresh()->estado->equals(EnJustificacion::class));
    }

    public function test_solo_rrhh_ajeno_puede_revisar_sustentos(): void
    {
        $sustento = $this->sustentoPresentado();

        foreach ([$this->trabajador, $this->jefe, $this->ajeno, $this->admin] as $usuario) {
            $this->actingAs($usuario)
                ->post(route('rrhh.sustentos.revisar', $sustento), ['resultado' => 'aprobado'])
                ->assertForbidden();
        }

        $propio = $this->papeletaDePrueba($this->rrhh, EnJustificacion::class);
        $sustentoPropio = Sustento::create([
            'papeleta_id' => $propio->id,
            'archivo_path' => 'papeletas/sustentos/x.pdf',
            'fecha_limite' => now()->addDays(2),
            'estado' => 'presentado',
            'presentado_at' => now(),
        ]);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.sustentos.revisar', $sustentoPropio), ['resultado' => 'aprobado'])
            ->assertForbidden();

        $this->assertSame('presentado', $sustento->fresh()->estado);
    }

    public function test_no_se_puede_revisar_un_sustento_que_aun_no_fue_presentado(): void
    {
        $sustento = $this->sustentoPresentado(null);

        $this->actingAs($this->rrhh)
            ->post(route('rrhh.sustentos.revisar', $sustento), ['resultado' => 'aprobado'])
            ->assertSessionHas('error');

        $this->assertSame('pendiente', $sustento->fresh()->estado);
    }
}
