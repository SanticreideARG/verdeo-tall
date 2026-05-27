<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->foreignId('hoja_ruta_id')
                ->nullable()
                ->after('asignado_cocina_id')
                ->constrained('hojas_ruta')
                ->nullOnDelete()
                ->comment('Hoja de ruta a la que pertenece este pedido');

            $table->timestamp('transportista_confirma_at')
                ->nullable()
                ->after('hoja_ruta_id')
                ->comment('Cuando el transportista marca el pedido como entregado en el microsite');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropForeign(['hoja_ruta_id']);
            $table->dropColumn(['hoja_ruta_id', 'transportista_confirma_at']);
        });
    }
};
