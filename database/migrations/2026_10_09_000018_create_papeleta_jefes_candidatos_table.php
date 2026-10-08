<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Varios jefes deciden, el primero que actúa gana" (régimen 728).
     *
     * `papeletas.jefe_inmediato_id` sigue siendo UNA columna (reportes y
     * dashboards); esta tabla fotografía a TODOS los candidatos resueltos por
     * UnidadOrganica::resolverJefesInmediatos() al crear la papeleta.
     * Ver Papeleta::scopeDeJefeInmediato() y Papeleta::jefesCandidatos().
     */
    public function up(): void
    {
        Schema::create('papeleta_jefes_candidatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('papeleta_id')->constrained('papeletas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['papeleta_id', 'user_id'], 'papeleta_jefes_candidatos_papeleta_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeleta_jefes_candidatos');
    }
};
