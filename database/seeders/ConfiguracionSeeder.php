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
            ['clave' => 'HORARIO_ORDINARIO_HORA_INICIO', 'valor' => '07:45', 'descripcion' => 'Hora de inicio del horario único global para régimen 276 (ordinario). Reemplaza la fila diaria de turnos. También define desde cuándo RRHH cuenta como "en horario".'],
            ['clave' => 'HORARIO_ORDINARIO_HORA_FIN', 'valor' => '16:15', 'descripcion' => 'Hora de fin del horario único global para régimen 276 (ordinario). También define hasta cuándo RRHH cuenta como "en horario" (los feriados de /admin/feriados y los usuarios RRHH inactivos no cuentan).'],
            ['clave' => 'HORARIO_ORDINARIO_DIAS_LABORABLES', 'valor' => '1,2,3,4,5', 'descripcion' => 'Días laborables (ISO: 1=Lunes ... 7=Domingo) del horario ordinario global, separados por coma. También rige los días en que RRHH está "en horario".'],
            ['clave' => 'TURNO_MANANA_HORA_INICIO', 'valor' => '06:00', 'descripcion' => 'Hora de inicio del turno Mañana (régimen 728, ciclo 6x1).'],
            ['clave' => 'TURNO_MANANA_HORA_FIN', 'valor' => '14:00', 'descripcion' => 'Hora de fin del turno Mañana (régimen 728, ciclo 6x1).'],
            ['clave' => 'TURNO_TARDE_HORA_INICIO', 'valor' => '14:00', 'descripcion' => 'Hora de inicio del turno Tarde (régimen 728, ciclo 6x1).'],
            ['clave' => 'TURNO_TARDE_HORA_FIN', 'valor' => '22:00', 'descripcion' => 'Hora de fin del turno Tarde (régimen 728, ciclo 6x1).'],
            ['clave' => 'TURNO_NOCHE_HORA_INICIO', 'valor' => '22:00', 'descripcion' => 'Hora de inicio del turno Noche (régimen 728, ciclo 6x1; cruza medianoche).'],
            ['clave' => 'TURNO_NOCHE_HORA_FIN', 'valor' => '06:00', 'descripcion' => 'Hora de fin del turno Noche (régimen 728, ciclo 6x1; cruza medianoche).'],
            ['clave' => 'TURNO_DIA_HORA_INICIO', 'valor' => '07:45', 'descripcion' => 'Hora de inicio del turno Día (único turno válido para régimen 276, ciclo 6x1).'],
            ['clave' => 'TURNO_DIA_HORA_FIN', 'valor' => '16:15', 'descripcion' => 'Hora de fin del turno Día (único turno válido para régimen 276, ciclo 6x1).'],
            ['clave' => 'MODO_ESTRICTO_728', 'valor' => '0', 'descripcion' => 'Interruptor global (solo régimen 728): en "1", bloquea la creación de papeleta si el trabajador 728 no tiene un turno vigente. Solo Admin lo cambia; Jefe de Área lo ve en modo lectura. Se maneja por temporadas.'],
            ['clave' => 'MODO_ESTRICTO_728_MENSAJE', 'valor' => 'No puedes crear una papeleta en este momento: no tienes un turno vigente asignado. Comunícate con tu jefe para que regularice tu turno.', 'descripcion' => 'Mensaje mostrado al trabajador 728 cuando MODO_ESTRICTO_728 está activo y no tiene turno vigente.'],
        ];

        foreach ($configuraciones as $configuracion) {
            Configuracion::updateOrCreate(['clave' => $configuracion['clave']], $configuracion);
        }
    }
}
