<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('tipo', ['texto', 'ubicacion', 'imagen'])->default('texto');
            $table->text('contenido')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('direccion', 400)->nullable();
            $table->string('archivo')->nullable();
            $table->timestamp('leido_at')->nullable();
            $table->timestamps();

            $table->index(['from_user_id', 'to_user_id']);
            $table->index(['to_user_id', 'leido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_mensajes');
    }
};
