<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los abandonos ya no esperan un plazo ni un visto bueno: el job cierra la
 * papeleta de una vez (Cerrada) y conserva la causa `abandono_no_marcado`.
 * Las que quedaron en `finalizado_sin_retorno` por abandono bajo la regla
 * anterior se pasan a `cerrada` para que se vean igual que las nuevas.
 * Las de comisión de servicio en campo no se tocan.
 *
 * No reversible: tras el cambio no hay forma de saber cuáles estaban en
 * regularización.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('papeletas')
            ->where('estado', 'finalizado_sin_retorno')
            ->where('causa_finalizacion_sin_retorno', 'abandono_no_marcado')
            ->update([
                'estado' => 'cerrada',
                'requiere_visto_bueno' => false,
                'regularizacion_fecha_limite' => null,
            ]);
    }

    public function down(): void
    {
        // Sin reversa: ver el comentario de la clase.
    }
};
