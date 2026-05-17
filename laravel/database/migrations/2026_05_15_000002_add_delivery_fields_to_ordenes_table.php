<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->string('ingresa_por', 20)->nullable()->after('notas');
            $table->string('direccion')->nullable()->after('ingresa_por');
            $table->decimal('latitud', 10, 7)->nullable()->after('direccion');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes', function (Blueprint $table) {
            $table->dropColumn(['ingresa_por', 'direccion', 'latitud', 'longitud']);
        });
    }
};
