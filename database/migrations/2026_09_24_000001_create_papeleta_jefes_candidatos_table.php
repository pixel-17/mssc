<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto 3 del flujo "varios jefes deciden, el primero que actúa gana"
 * para régimen 728.
 *
 * `papeletas.jefe_inmediato_id` sigue siendo una sola columna
 * (compatibilidad con reportes/dashboards existentes): al crear la
 * papeleta se sigue fotografiando ahí a UNO de los candidatos
 * resueltos por UnidadOrganica::resolverJefesInmediatos($turno).
 *
 * Esta tabla fotografía a TODOS los candidatos de esa resolución,
 * para que cualquiera de ellos pueda decidir la papeleta ("el primero
 * que actúa, gana"), igual que ya pasa con jefes_inmediatos_adicionales.
 * Ver Papeleta::scopeDeJefeInmediato() y Papeleta::jefesCandidatos().
 *
 * Para papeletas de régimen 276 (o cualquier caso con un solo
 * candidato) esta tabla tiene, como mucho, una fila — el mismo id que
 * jefe_inmediato_id. No es obligatorio tener fila alguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papeleta_jefes_candidatos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('papeleta_id')
                ->constrained('papeletas')->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['papeleta_id', 'user_id'], 'papeleta_jefes_candidatos_papeleta_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papeleta_jefes_candidatos');
    }
};
