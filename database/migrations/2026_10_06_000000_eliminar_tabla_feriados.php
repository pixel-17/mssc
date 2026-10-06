<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los feriados ya no se consideran en el sistema: se elimina la tabla.
     */
    public function up(): void
    {
        Schema::dropIfExists('feriados');
    }

    public function down(): void
    {
        // Sin reversa: el catálogo de feriados fue retirado.
    }
};
