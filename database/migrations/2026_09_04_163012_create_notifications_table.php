<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canal in-app persistente (WebSocket/panel) del sistema de notificaciones.
     * Es "el registro de verdad" — push (push_subscriptions) es solo entrega
     * inmediata adicional en paralelo, nunca la única fuente. Estructura
     * estándar de Illuminate\Notifications\DatabaseNotification: notifiable
     * es polimórfico porque además de trabajador, jefe inmediato, jefe de área
     * y RRHH reciben notificaciones sobre la misma papeleta.
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
