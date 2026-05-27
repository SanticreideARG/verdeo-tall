<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->foreignId('orden_cocina_id')
                ->nullable()
                ->after('zona')
                ->constrained('ordenes_cocina')
                ->nullOnDelete()
                ->comment('Batch de cocina al que pertenece este pedido');

            $table->foreignId('asignado_cocina_id')
                ->nullable()
                ->after('orden_cocina_id')
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Colaborador de cocina asignado individualmente (override del batch)');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropForeign(['orden_cocina_id']);
            $table->dropForeign(['asignado_cocina_id']);
            $table->dropColumn(['orden_cocina_id', 'asignado_cocina_id']);
        });
    }
};
