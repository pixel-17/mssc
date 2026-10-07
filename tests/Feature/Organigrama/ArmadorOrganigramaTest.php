<?php

namespace Tests\Feature\Organigrama;

use App\Actions\Organigrama\ReglasOrganigrama;
use App\Exceptions\UsuarioException;
use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\Organigrama\ArmadorOrganigrama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Lado de lectura del organigrama (ArmadorOrganigrama) y reglas compartidas
 * (ReglasOrganigrama).
 */
class ArmadorOrganigramaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function crearRama(UnidadOrganica $padre, string $nombre): UnidadOrganica
    {
        $jefe = $this->usuarioDePrueba(['name' => 'Jefe '.$nombre], ['trabajador']);
        $unidad = UnidadOrganica::create(['nombre' => $nombre, 'parent_id' => $padre->id, 'jefe_id' => $jefe->id]);

        foreach (range(1, 3) as $i) {
            $this->usuarioDePrueba(['name' => $nombre.' T'.$i, 'unidad_organica_id' => $unidad->id]);
        }

        return $unidad;
    }

    private function consultasAlArmar(User $admin): int
    {
        // Spatie carga los roles del usuario una sola vez y los deja en el
        // modelo: sin precargarlos, la PRIMERA medición cuenta una consulta
        // que la segunda ya no hace y parece un N+1 que no es.
        $admin->loadMissing('roles');

        DB::flushQueryLog();
        DB::enableQueryLog();

        (new ArmadorOrganigrama)->armar($admin);

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    public function test_el_numero_de_consultas_no_crece_con_la_cantidad_de_unidades(): void
    {
        $admin = $this->usuarioDePrueba([], ['admin']);
        $jefeArea = $this->usuarioDePrueba(['name' => 'Jefe area'], ['trabajador']);
        $raiz = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefeArea->id]);

        foreach (['A', 'B', 'C'] as $n) {
            $this->crearRama($raiz, 'Oficina '.$n);
        }
        $conTres = $this->consultasAlArmar($admin);

        foreach (['D', 'E', 'F', 'G', 'H', 'I'] as $n) {
            $this->crearRama($raiz, 'Oficina '.$n);
        }
        $conNueve = $this->consultasAlArmar($admin);

        $this->assertSame($conTres, $conNueve, 'armar() hace consultas por unidad (N+1).');
    }

    public function test_el_regimen_incompatible_con_la_unidad_destino_se_rechaza(): void
    {
        $raiz = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->usuarioDePrueba()->id]);
        $destino = $this->crearRama($raiz, 'Oficina A');
        $regimenDestino = $destino->regimen();

        $ajeno = $this->usuarioDePrueba(['regimen' => $regimenDestino === '728' ? '276' : '728']);

        $this->expectException(UsuarioException::class);
        $this->expectExceptionMessage('no coincide con el de la unidad de destino');

        app(ReglasOrganigrama::class)->exigirRegimenCompatible($ajeno, $destino);
    }

    public function test_el_area_de_un_jefe_es_la_unidad_mas_alta_que_encabeza_con_subunidades(): void
    {
        $jefe = $this->usuarioDePrueba([], ['trabajador']);
        $raiz = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $jefe->id]);
        $hija = UnidadOrganica::create(['nombre' => 'Oficina', 'parent_id' => $raiz->id, 'jefe_id' => $this->usuarioDePrueba()->id]);

        $reglas = app(ReglasOrganigrama::class);

        $this->assertSame($raiz->id, $reglas->areaDe($jefe, $hija));
        $this->assertTrue($reglas->esJefeDeAreaDe($jefe, $hija->id));
        $this->assertFalse($reglas->esJefeDeAreaDe($this->usuarioDePrueba(), $hija->id));
    }
}
