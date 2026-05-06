<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio', 'unidad', 'categoria']);
            $table->string('tipo', 30)->default('keto')->after('nombre');
            $table->unsignedTinyInteger('orden')->default(0)->after('activo');
        });

        Schema::create('platos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->string('nombre', 200);
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['producto_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platos');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'orden']);
            $table->decimal('precio', 10, 2)->default(0)->after('descripcion');
            $table->string('unidad', 30)->default('kg')->after('precio');
            $table->string('categoria', 60)->nullable()->after('unidad');
        });
    }
};
