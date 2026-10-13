<?php

namespace Tests\Feature\Organigrama;

use App\Actions\Usuario\CambiarEstadoUsuarioAction;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\States\Papeleta\Cerrada;
use App\States\Papeleta\PendienteJefe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Un solo jefe por unidad (sin importar régimen), baja con reemplazo y
 * jefatura vacante. Prueba la capa de modelos y acciones, no la pantalla.
 */
class JefeUnicoYReemplazoTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();

        $this->admin = $this->usuarioDePrueba([], ['admin']);
    }

    private function unidadConJefe(User $jefe, string $nombre = 'Oficina'): UnidadOrganica
    {
        return UnidadOrganica::create(['nombre' => $nombre, 'jefe_id' => $jefe->id]);
    }

    private function trabajadorEn(UnidadOrganica $unidad, string $regimen = '728'): User
    {
        $trabajador = $this->usuarioDePrueba(['regimen' => $regimen]);
        $trabajador->update(['unidad_organica_id' => $unidad->id]);

        return $trabajador->fresh();
    }

    private function desactivar(User $jefe, array $reemplazos = []): void
    {
        app(CambiarEstadoUsuarioAction::class)->desactivar($this->admin, $jefe, $reemplazos);
    }

    public function test_el_jefe_de_la_unidad_es_el_unico_jefe_inmediato_sin_importar_el_regimen(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '728']);
        $unidad = $this->unidadConJefe($jefe);

        $de728 = $this->trabajadorEn($unidad, '728');
        $de276 = $this->trabajadorEn($unidad, '276');

        $this->assertSame($jefe->id, $de728->jefe_inmediato_id);
        $this->assertSame($jefe->id, $de276->jefe_inmediato_id);
    }

    public function test_un_jefe_276_puede_tener_trabajadores_728(): void
    {
        $jefe = $this->usuarioDePrueba(['regimen' => '276']);
        $unidad = $this->unidadConJefe($jefe);

        $trabajador = $this->trabajadorEn($unidad, '728');

        $this->assertSame($jefe->id, $trabajador->jefe_inmediato_id);
    }

    public function test_no_se_puede_desactivar_a_un_jefe_con_gente_sin_decidir_su_jefatura(): void
    {
        $jefe = $this->usuarioDePrueba();
        $unidad = $this->unidadConJefe($jefe);
        $this->trabajadorEn($unidad);

        $this->expectException(UsuarioException::class);

        $this->desactivar($jefe);
    }

    public function test_reemplazo_existente_pasa_la_jefatura_y_las_papeletas_abiertas(): void
    {
        $jefe = $this->usuarioDePrueba();
        $nuevo = $this->usuarioDePrueba();
        $unidad = $this->unidadConJefe($jefe);
        $trabajador = $this->trabajadorEn($unidad);

        $abierta = $this->papeletaDePrueba($trabajador, PendienteJefe::class, [
            'jefe_inmediato_id' => $jefe->id,
        ]);

        $this->desactivar($jefe, [$unidad->id => ['modo' => 'existente', 'usuario_id' => $nuevo->id]]);

        $this->assertFalse($jefe->fresh()->activo);
        $this->assertSame($nuevo->id, $unidad->fresh()->jefe_id);
        $this->assertSame($nuevo->id, $trabajador->fresh()->jefe_inmediato_id);
        $this->assertSame($nuevo->id, $abierta->fresh()->jefe_inmediato_id);
    }

    public function test_las_papeletas_cerradas_no_cambian_de_jefe(): void
    {
        $jefe = $this->usuarioDePrueba();
        $nuevo = $this->usuarioDePrueba();
        $unidad = $this->unidadConJefe($jefe);
        $trabajador = $this->trabajadorEn($unidad);

        $cerrada = $this->papeletaDePrueba($trabajador, Cerrada::class, [
            'jefe_inmediato_id' => $jefe->id,
        ]);

        $this->desactivar($jefe, [$unidad->id => ['modo' => 'existente', 'usuario_id' => $nuevo->id]]);

        $this->assertSame($jefe->id, $cerrada->fresh()->jefe_inmediato_id);
    }

    public function test_reemplazo_vacante_deja_la_unidad_y_a_su_gente_sin_jefe(): void
    {
        $jefe = $this->usuarioDePrueba();
        $unidad = $this->unidadConJefe($jefe);
        $trabajador = $this->trabajadorEn($unidad);

        $this->desactivar($jefe, [$unidad->id => ['modo' => 'vacante']]);

        $this->assertNull($unidad->fresh()->jefe_id);
        $this->assertNull($trabajador->fresh()->jefe_inmediato_id);
    }

    public function test_reemplazo_nuevo_crea_al_jefe_y_lo_asigna_a_la_unidad(): void
    {
        $jefe = $this->usuarioDePrueba();
        $unidad = $this->unidadConJefe($jefe);
        $this->trabajadorEn($unidad);

        $this->desactivar($jefe, [$unidad->id => [
            'modo' => 'nuevo',
            'nuevo' => [
                'name' => 'Ana',
                'apellido' => 'Pérez',
                'dni' => '71234567',
                'email' => 'ana.nueva@example.test',
                'regimen' => '276',
            ],
        ]]);

        $nuevo = User::where('dni', '71234567')->first();

        $this->assertNotNull($nuevo);
        $this->assertSame($nuevo->id, $unidad->fresh()->jefe_id);
    }

    public function test_jefatura_vacante_de_area_solo_deja_sin_jefe_a_su_gente_directa(): void
    {
        $jefeArea = $this->usuarioDePrueba();
        $inmediato = $this->usuarioDePrueba();

        $padre = $this->unidadConJefe($jefeArea, 'Área');
        $hijo = UnidadOrganica::create(['nombre' => 'Sub-área', 'parent_id' => $padre->id, 'jefe_id' => $inmediato->id]);

        $deArea = $this->trabajadorEn($padre);
        $deInmediato = $this->trabajadorEn($hijo);

        $this->desactivar($jefeArea, [$padre->id => ['modo' => 'vacante']]);

        $this->assertNull($deArea->fresh()->jefe_inmediato_id);
        $this->assertSame($inmediato->id, $deInmediato->fresh()->jefe_inmediato_id);
    }
}
