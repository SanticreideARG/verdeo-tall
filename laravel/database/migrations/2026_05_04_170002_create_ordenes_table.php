<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('estado', ['pendiente', 'aprobada', 'lista_para_entrega', 'entregada', 'cancelada'])
                  ->default('pendiente');
            $table->string('zona', 30);
            $table->text('notas')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['estado', 'zona']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes');
    }
};
