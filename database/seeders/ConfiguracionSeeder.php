<?php

namespace Database\Seeders;

use App\Models\Configuracion;
use Illuminate\Database\Seeder;

/**
 * Todo número "parametrizable" del documento, sembrado como fila
 * editable en Filament (ConfiguracionResource) en vez de vivir
 * hardcodeado en las Actions.
 */
class ConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $configuraciones = [
            ['clave' => 'RELOJ_JEFE_MINUTOS', 'valor' => '5', 'descripcion' => 'Minutos que tiene el Jefe Inmediato para decidir antes de escalar o vencer.'],
            ['clave' => 'TOPE_OBSERVACIONES', 'valor' => '3', 'descripcion' => 'Tope de observaciones del Jefe antes del rechazo automático.'],
            ['clave' => 'TOPE_OBSERVACIONES_RRHH', 'valor' => '3', 'descripcion' => 'Tope de observaciones de RRHH antes del rechazo automático.'],
            ['clave' => 'BLOQUE_ALMUERZO_INICIO', 'valor' => '13:00', 'descripcion' => 'Inicio del bloque de almuerzo para el descuento de refrigerio (solo CAS).'],
            ['clave' => 'BLOQUE_ALMUERZO_FIN', 'valor' => '14:00', 'descripcion' => 'Fin del bloque de almuerzo para el descuento de refrigerio (solo CAS).'],
            ['clave' => 'SUSTENTO_HORAS_HABILES', 'valor' => '48', 'descripcion' => 'Horas hábiles para presentar sustento de Salud tras el retorno.'],
            ['clave' => 'SUBSANACION_EMERGENCIA_DIAS_HABILES', 'valor' => '15', 'descripcion' => 'Días hábiles para subsanar una Emergencia observada antes de reclasificar a Particular.'],
        ];

        foreach ($configuraciones as $configuracion) {
            Configuracion::updateOrCreate(['clave' => $configuracion['clave']], $configuracion);
        }
    }
}
