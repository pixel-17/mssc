<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Elimina SUSTENTO_HORAS_HABILES de configuraciones: el plazo de
 * justificación ahora vive exclusivamente en cada motivo
 * (plazo_justificacion_horas_habiles), que pasa a ser obligatorio
 * para los motivos que requieren sustento.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar la fila de configuración global.
        DB::table('configuraciones')->where('clave', 'SUSTENTO_HORAS_HABILES')->delete();

        // 2. Asegurarse de que todo motivo que requiere sustento
        //    tenga un plazo definido (usa 48 como valor por defecto
        //    para registros existentes que lo tenían en null).
        DB::table('motivos')
            ->where('requiere_sustento_en_retorno', true)
            ->whereNull('plazo_justificacion_horas_habiles')
            ->update(['plazo_justificacion_horas_habiles' => 48]);
    }

    public function down(): void
    {
        // Restaurar la fila eliminada.
        DB::table('configuraciones')->insertOrIgnore([
            'clave' => 'SUSTENTO_HORAS_HABILES',
            'valor' => '48',
            'descripcion' => 'Horas hábiles para presentar sustento de Salud tras el retorno.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
