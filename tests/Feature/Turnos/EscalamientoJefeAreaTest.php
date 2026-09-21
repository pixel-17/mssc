<?php

namespace Tests\Feature\Turnos;

use App\Models\Configuracion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioPapeletas;
use Tests\TestCase;

/**
 * Escalamiento al Jefe de Área cuando el jefe inmediato no responde en el reloj.
 * Lunes 2026-09-21: día laborable, dentro del horario ordinario 276 (07:45-16:15).
 */
class EscalamientoJefeAreaTest extends TestCase
{
    use CreaEscenarioPapeletas;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararBase();
    }

    private function papeletaDe(User $jefeArea)
    {
        $this->travelTo('2026-09-21 09:40:00');

        return $this->papeletaDePrueba($this->usuarioDePrueba(), atributos: ['jefe_area_id' => $jefeArea->id]);
    }

    public function test_escala_cuando_vence_el_reloj_y_el_jefe_de_area_esta_en_horario(): void
    {
        $papeleta = $this->papeletaDe($this->usuarioDePrueba(['regimen' => '276']));

        $this->travelTo('2026-09-21 10:00:00'); // 20 min después, reloj por defecto de 5
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();

        $this->assertNotNull($papeleta->fresh()->escalado_jefe_area_at);
    }

    public function test_respeta_el_reloj_configurado_por_el_admin(): void
    {
        // La clave sembrada y editable en la pantalla de configuración es RELOJ_JEFE_MINUTOS;
        // el comando leía SLA_JEFE_MINUTOS (que nadie puede editar) y siempre usaba 5.
        Configuracion::create(['clave' => 'RELOJ_JEFE_MINUTOS', 'valor' => '30', 'descripcion' => 'Reloj del jefe']);

        $papeleta = $this->papeletaDe($this->usuarioDePrueba(['regimen' => '276']));

        $this->travelTo('2026-09-21 10:00:00'); // 20 min: todavía dentro de los 30 configurados
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();
        $this->assertNull($papeleta->fresh()->escalado_jefe_area_at);

        $this->travelTo('2026-09-21 10:15:00'); // 35 min
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();
        $this->assertNotNull($papeleta->fresh()->escalado_jefe_area_at);
    }

    public function test_no_escala_a_un_jefe_de_area_desactivado(): void
    {
        $papeleta = $this->papeletaDe($this->usuarioDePrueba(['regimen' => '276', 'activo' => false]));

        $this->travelTo('2026-09-21 10:00:00');
        $this->artisan('papeletas:procesar-vencimientos')->assertSuccessful();

        $this->assertNull($papeleta->fresh()->escalado_jefe_area_at);
    }
}
