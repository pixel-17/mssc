<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->foreignId('en_revision_por_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('en_revision_desde')
                ->nullable()
                ->after('en_revision_por_id');
        });
    }

    public function down(): void
    {
        Schema::table('papeletas', function (Blueprint $table) {
            $table->dropForeign(['en_revision_por_id']);
            $table->dropColumn(['en_revision_por_id', 'en_revision_desde']);
        });
    }
};
