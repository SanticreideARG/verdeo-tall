<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hojas_ruta', function (Blueprint $table) {
            $table->id();

            $table->string('numero', 30)->unique()
                ->comment('HR-YYYYMMDD-NNN — generado automáticamente');

            $table->string('ciudad', 80);

            $table->string('token', 64)->unique()
                ->comment('Token público para el microsite del transportista');

            $table->timestamp('expires_at')
                ->comment('El link expira 24 hs después de la creación');

            $table->foreignId('creado_por')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('transportista_nombre', 100)->nullable();
            $table->string('transportista_telefono', 20)->nullable();

            $table->enum('estado', ['activa', 'en_reparto', 'completada', 'cancelada'])
                ->default('activa');

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['ciudad', 'estado']);
            $table->index('token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hojas_ruta');
    }
};
