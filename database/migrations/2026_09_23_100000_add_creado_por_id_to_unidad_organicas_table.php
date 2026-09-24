<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad de autoservicio (Avance: Jefe de Área crea su propio
 * árbol vía CrearOficinaConJefeAction). Nullable porque las unidades
 * ya existentes (creadas por admin desde UnidadOrganicaForm antes de
 * este cambio) no tienen este dato retroactivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidad_organicas', function (Blueprint $table) {
            $table->foreignId('creado_por_id')->nullable()->after('jefe_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unidad_organicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('creado_por_id');
        });
    }
};
