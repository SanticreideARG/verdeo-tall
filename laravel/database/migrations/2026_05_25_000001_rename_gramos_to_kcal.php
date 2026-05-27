<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── productos: renombrar columnas de precio ──────────────────────────
        Schema::table('productos', function (Blueprint $table) {
            $table->renameColumn('precio_250g', 'precio_250kcal');
            $table->renameColumn('precio_400g', 'precio_400kcal');
        });

        // ── zonas: renombrar columnas de precio ──────────────────────────────
        Schema::table('zonas', function (Blueprint $table) {
            $table->renameColumn('precio_250g', 'precio_250kcal');
            $table->renameColumn('precio_400g', 'precio_400kcal');
        });

        // ── orden_items: actualizar valores existentes de tamano ─────────────
        DB::table('orden_items')->where('tamano', '250g')->update(['tamano' => '250kcal']);
        DB::table('orden_items')->where('tamano', '400g')->update(['tamano' => '400kcal']);
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->renameColumn('precio_250kcal', 'precio_250g');
            $table->renameColumn('precio_400kcal', 'precio_400g');
        });

        Schema::table('zonas', function (Blueprint $table) {
            $table->renameColumn('precio_250kcal', 'precio_250g');
            $table->renameColumn('precio_400kcal', 'precio_400g');
        });

        DB::table('orden_items')->where('tamano', '250kcal')->update(['tamano' => '250g']);
        DB::table('orden_items')->where('tamano', '400kcal')->update(['tamano' => '400g']);
    }
};
