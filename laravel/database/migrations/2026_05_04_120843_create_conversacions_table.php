<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id();
            $table->string('zona');
            $table->string('telefono', 20);
            $table->string('nombre')->nullable();
            $table->enum('estado', ['abierta', 'cerrada', 'esperando'])->default('abierta');
            $table->text('ultimo_mensaje')->nullable();
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();

            $table->index(['zona', 'estado']);
            $table->index('ultimo_mensaje_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversaciones');
    }
};
