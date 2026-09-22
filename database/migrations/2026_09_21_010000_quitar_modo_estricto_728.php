<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira el interruptor MODO_ESTRICTO_728: un trabajador de régimen
 * 728 (rotativo) nunca es bloqueado para crear una papeleta por no
 * tener un turno vigente cargado en `turnos` — ese registro sigue
 * existiendo solo como referencia informativa (ver CrearPapeletaAction).
 *
 * - configuraciones: MODO_ESTRICTO_728, MODO_ESTRICTO_728_MENSAJE.
 *
 * NO se toca la tabla `turnos` ni la columna `es_descanso`: siguen
 * vivas para el calendario y para el cálculo de fin de turno.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')->whereIn('clave', [
            'MODO_ESTRICTO_728',
            'MODO_ESTRICTO_728_MENSAJE',
        ])->delete();
    }

    public function down(): void
    {
        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'MODO_ESTRICTO_728'],
            ['valor' => '0', 'descripcion' => 'Interruptor global (solo régimen 728): en "1", bloquea la creación de papeleta si el trabajador 728 no tiene un turno vigente. Solo Admin lo cambia; Jefe de Área lo ve en modo lectura. Se maneja por temporadas.'],
        );

        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'MODO_ESTRICTO_728_MENSAJE'],
            ['valor' => 'No puedes crear una papeleta en este momento: no tienes un turno vigente asignado. Comunícate con tu jefe para que regularice tu turno.', 'descripcion' => 'Mensaje mostrado al trabajador 728 cuando MODO_ESTRICTO_728 está activo y no tiene turno vigente.'],
        );
    }
};
