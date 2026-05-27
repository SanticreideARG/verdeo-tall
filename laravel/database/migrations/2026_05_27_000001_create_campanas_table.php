<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campanas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 25)->unique();
            $table->string('nombre', 150);
            $table->text('mensaje');

            // Filtros
            $table->string('filtro_zona', 80)->nullable()->comment('null = todas las zonas');
            $table->string('filtro_estado', 30)->nullable()->comment('null = todos los estados');

            // Estado de la campaña
            $table->enum('estado', ['borrador', 'enviando', 'completada', 'cancelada'])
                  ->default('borrador');

            // Contadores (se incrementan a medida que los jobs procesan)
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('total_enviados')->default(0);
            $table->unsignedInteger('total_fallidos')->default(0);

            $table->foreignId('creado_por')->constrained('users');
            $table->timestamp('lanzada_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('creado_por');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campanas');
    }
};
