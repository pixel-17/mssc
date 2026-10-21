<?php

namespace Tests\Feature\Papeletas;

use App\Livewire\Papeletas\JefeIndex;
use App\Models\Sustento;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\EnJustificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * El visto bueno de la justificación (Salud) lo da SOLO RRHH
 * (revisarSustento): ni el jefe inmediato, ni el jefe de área ni el
 * admin lo dan. Pero abrir el archivo (verSustento) sí lo puede hacer
 * cualquiera con visibilidad de esta papeleta en el reporte de
 * Sustentos — dueño, RRHH, admin, jefe inmediato y jefe de área,
 * misma frontera que ReporteSustentosService::query() — para que lo
 * que ya ven listado no les devuelva 403 al abrirlo.
 */
class SustentoSoloRrhhTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    public function test_solo_rrhh_da_el_visto_bueno_pero_jefe_inmediato_admin_y_jefe_de_area_pueden_abrir_el_archivo(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $jefe = $this->conJefeDePrueba($trabajador);
        $rrhh = $this->usuarioDePrueba([], ['rrhh']);
        $admin = $this->usuarioDePrueba([], ['admin']);
        $ajeno = $this->usuarioDePrueba();

        $papeleta = $this->papeletaDePrueba($trabajador->fresh(), EnJustificacion::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
        ]);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
            'archivo_path' => 'papeletas/sustentos/certificado.pdf',
            'presentado_at' => now(),
        ]);

        $this->assertTrue($papeleta->tieneComoJefeInmediatoA($jefe), 'el jefe sí es el jefe inmediato');

        // El visto bueno (aprobar/observar el sustento) sigue siendo exclusivo de RRHH.
        $this->assertFalse($jefe->can('revisarSustento', $sustento));
        $this->assertFalse($admin->can('revisarSustento', $sustento));
        $this->assertTrue($rrhh->can('revisarSustento', $sustento));

        // Abrir el archivo: dueño, RRHH, admin y jefe inmediato sí; un jefe ajeno no.
        $this->assertTrue($trabajador->can('verSustento', $sustento), 'el dueño ve su propio archivo');
        $this->assertTrue($rrhh->can('verSustento', $sustento));
        $this->assertTrue($admin->can('verSustento', $sustento), 'el admin ve el archivo, igual que en el reporte de Sustentos');
        $this->assertTrue($jefe->can('verSustento', $sustento), 'el jefe inmediato ve el archivo, igual que en el reporte de Sustentos');
        $this->assertFalse($ajeno->can('verSustento', $sustento), 'un jefe ajeno a la papeleta no abre el archivo');
    }

    public function test_el_jefe_de_area_tambien_puede_abrir_el_archivo_de_su_rama(): void
    {
        // Gerencia (jefe de área: $gerente)
        //  └── Oficina (jefe inmediato: $jefeOficina)
        //       └── trabajador -> jefe_inmediato = $jefeOficina, jefe_area = $gerente
        $gerente = User::factory()->create();
        $jefeOficina = User::factory()->create();

        $gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $gerente->id]);
        $oficina = UnidadOrganica::create([
            'nombre' => 'Oficina',
            'parent_id' => $gerencia->id,
            'jefe_id' => $jefeOficina->id,
        ]);

        $trabajador = $this->usuarioDePrueba(['unidad_organica_id' => $oficina->id]);

        $papeleta = $this->papeletaDePrueba($trabajador->fresh(), EnJustificacion::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
        ]);
        $sustento = Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
            'archivo_path' => 'papeletas/sustentos/certificado.pdf',
            'presentado_at' => now(),
        ]);

        $this->assertSame($gerente->id, $papeleta->jefe_area_id, 'el gerente quedó fotografiado como jefe de área');
        $this->assertFalse($gerente->can('revisarSustento', $sustento), 'el jefe de área tampoco da el visto bueno');
        $this->assertTrue($gerente->can('verSustento', $sustento), 'el jefe de área sí abre el archivo de su rama');
    }

    public function test_el_jefe_ya_no_tiene_bandeja_de_sustentos_por_revisar(): void
    {
        $trabajador = $this->usuarioDePrueba();
        $jefe = $this->conJefeDePrueba($trabajador);

        $papeleta = $this->papeletaDePrueba($trabajador->fresh(), EnJustificacion::class, [
            'motivo_id' => $this->motivoDe('SALUD')->id,
        ]);
        Sustento::create([
            'papeleta_id' => $papeleta->id,
            'fecha_limite' => now()->addDay(),
            'estado' => 'presentado',
            'archivo_path' => 'papeletas/sustentos/certificado.pdf',
            'presentado_at' => now(),
        ]);

        Livewire::actingAs($jefe)
            ->test(JefeIndex::class)
            ->assertDontSee('Sustentos por revisar');
    }
}
