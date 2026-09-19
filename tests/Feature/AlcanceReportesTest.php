<?php

namespace Tests\Feature;

use App\Models\UnidadOrganica;
use App\Models\User;
use App\Services\HistorialTrabajadorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Qué trabajadores puede elegir/buscar un jefe en los reportes.
 *
 * Organigrama de prueba:
 *
 *   Gerencia (jefe: $gerente)
 *   ├── trabajador directo de la Gerencia      -> jefe inmediato = $gerente
 *   └── Oficina (jefe: $jefeOficina)
 *       └── trabajador de la Oficina           -> jefe inmediato = $jefeOficina,
 *                                                  jefe de área   = $gerente
 *
 * jefe_inmediato_id / jefe_area_id los calcula UserObserver al guardar
 * unidad_organica_id.
 */
class AlcanceReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $jefeOficina;

    private User $trabajadorGerencia;

    private User $trabajadorOficina;

    private User $ajeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gerente = User::factory()->create();
        $this->jefeOficina = User::factory()->create();

        $gerencia = UnidadOrganica::create(['nombre' => 'Gerencia', 'jefe_id' => $this->gerente->id]);
        $oficina = UnidadOrganica::create([
            'nombre' => 'Oficina',
            'parent_id' => $gerencia->id,
            'jefe_id' => $this->jefeOficina->id,
        ]);

        $this->trabajadorGerencia = User::factory()->create([
            'name' => 'Rosa',
            'apellido' => 'Quispe',
            'unidad_organica_id' => $gerencia->id,
        ]);
        $this->trabajadorOficina = User::factory()->create([
            'name' => 'Luis',
            'apellido' => 'Mamani',
            'dni' => '45678912',
            'unidad_organica_id' => $oficina->id,
        ]);

        $otroJefe = User::factory()->create();
        $otraUnidad = UnidadOrganica::create(['nombre' => 'Otra unidad', 'jefe_id' => $otroJefe->id]);
        $this->ajeno = User::factory()->create([
            'name' => 'Pedro',
            'apellido' => 'Ajeno',
            'dni' => '11112222',
            'unidad_organica_id' => $otraUnidad->id,
        ]);
    }

    public function test_jefe_de_area_ve_a_su_gente_y_a_la_de_las_unidades_hijas(): void
    {
        $ids = $this->gerente->trabajadoresParaReportes()->pluck('id');

        $this->assertTrue($ids->contains($this->trabajadorGerencia->id));
        $this->assertTrue($ids->contains($this->trabajadorOficina->id));
        $this->assertFalse($ids->contains($this->ajeno->id));
    }

    public function test_jefe_de_una_oficina_solo_ve_a_su_gente(): void
    {
        $ids = $this->jefeOficina->trabajadoresParaReportes()->pluck('id');

        $this->assertTrue($ids->contains($this->trabajadorOficina->id));
        $this->assertFalse($ids->contains($this->trabajadorGerencia->id));
        $this->assertFalse($ids->contains($this->ajeno->id));
    }

    public function test_buscador_de_ficha_encuentra_por_dni_y_por_nombre_dentro_del_alcance(): void
    {
        $servicio = app(HistorialTrabajadorService::class);

        $porDni = $servicio->trabajadoresVisibles($this->gerente, '45678912');
        $this->assertSame([$this->trabajadorOficina->id], $porDni->pluck('id')->all());

        $porNombre = $servicio->trabajadoresVisibles($this->gerente, 'mamani');
        $this->assertSame([$this->trabajadorOficina->id], $porNombre->pluck('id')->all());
    }

    public function test_buscador_de_ficha_no_devuelve_a_trabajadores_fuera_del_alcance(): void
    {
        $servicio = app(HistorialTrabajadorService::class);

        $this->assertTrue($servicio->trabajadoresVisibles($this->gerente, '11112222')->isEmpty());
        $this->assertTrue($servicio->trabajadoresVisibles($this->jefeOficina, 'quispe')->isEmpty());
    }

    public function test_puede_ver_ficha_solo_dentro_del_alcance(): void
    {
        $servicio = app(HistorialTrabajadorService::class);

        $this->assertTrue($servicio->puedeVer($this->gerente, $this->trabajadorOficina));
        $this->assertTrue($servicio->puedeVer($this->jefeOficina, $this->trabajadorOficina));
        $this->assertFalse($servicio->puedeVer($this->jefeOficina, $this->trabajadorGerencia));
        $this->assertFalse($servicio->puedeVer($this->gerente, $this->ajeno));
    }
}
