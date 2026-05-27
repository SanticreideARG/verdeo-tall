<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_cocina', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()
                ->comment('COC-YYYYMMDD-NNN — generado automáticamente');
            $table->string('ciudad', 80);
            $table->foreignId('creado_por')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete()
                ->comment('Colaborador de cocina responsable del batch completo');
            $table->enum('estado', ['activa', 'completada'])->default('activa');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['ciudad', 'estado']);
            $table->index('creado_por');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_cocina');
    }
};
