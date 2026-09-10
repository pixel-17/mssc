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
            ['clave' => 'BLOQUE_ALMUERZO_INICIO', 'valor' => '13:00', 'descripcion' => 'Inicio del bloque de almuerzo para el descuento de refrigerio (solo 276).'],
            ['clave' => 'BLOQUE_ALMUERZO_FIN', 'valor' => '14:00', 'descripcion' => 'Fin del bloque de almuerzo para el descuento de refrigerio (solo 276).'],
            ['clave' => 'SUSTENTO_HORAS_HABILES', 'valor' => '48', 'descripcion' => 'Horas hábiles para presentar sustento de Salud tras el retorno.'],
            ['clave' => 'SUBSANACION_EMERGENCIA_DIAS_HABILES', 'valor' => '15', 'descripcion' => 'Días hábiles para subsanar una Emergencia observada antes de reclasificar a Particular.'],
            ['clave' => 'HORARIO_ORDINARIO_HORA_INICIO', 'valor' => '07:45', 'descripcion' => 'Hora de inicio del horario único global para régimen 276 (ordinario). Reemplaza la fila diaria de turnos.'],
            ['clave' => 'HORARIO_ORDINARIO_HORA_FIN', 'valor' => '16:15', 'descripcion' => 'Hora de fin del horario único global para régimen 276 (ordinario).'],
            ['clave' => 'HORARIO_ORDINARIO_DIAS_LABORABLES', 'valor' => '1,2,3,4,5', 'descripcion' => 'Días laborables (ISO: 1=Lunes ... 7=Domingo) del horario ordinario global, separados por coma.'],
        ];

        foreach ($configuraciones as $configuracion) {
            Configuracion::updateOrCreate(['clave' => $configuracion['clave']], $configuracion);
        }
    }
}
