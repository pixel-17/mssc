<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canal in-app persistente (campana + WebSocket): es "el registro de
     * verdad"; el web push (push_subscriptions) es solo entrega inmediata
     * adicional. Estructura estándar de DatabaseNotification; `notifiable` es
     * polimórfico porque trabajador, jefes y RRHH reciben avisos de la misma papeleta.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
