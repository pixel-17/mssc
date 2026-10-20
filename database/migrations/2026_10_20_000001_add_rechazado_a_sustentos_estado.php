<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RRHH necesita una tercera decisión al revisar un sustento presentado,
 * distinta de 'aprobado' (cierra la papeleta sin descuento) y
 * 'observado' (reabre para que el trabajador vuelva a presentar):
 * 'rechazado' — el sustento no es válido y no amerita una nueva
 * oportunidad, la papeleta se reclasifica a Particular con descuento
 * (mismo camino que ReclasificarAParticularAction usa para los
 * vencimientos automáticos, pero disparado por un humano).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // sqlite no tiene ENUM real: la columna ya acepta cualquier string.
            return;
        }

        DB::statement("ALTER TABLE sustentos MODIFY estado ENUM('pendiente', 'presentado', 'vencido', 'aprobado', 'observado', 'rechazado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE sustentos MODIFY estado ENUM('pendiente', 'presentado', 'vencido', 'aprobado', 'observado') NOT NULL DEFAULT 'pendiente'");
    }
};
