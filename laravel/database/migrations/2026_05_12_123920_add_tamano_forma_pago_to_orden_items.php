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
        Schema::table('orden_items', function (Blueprint $table) {
            $table->string('tamano', 10)->default('250g')->after('producto_id');
            $table->string('forma_pago', 20)->default('no_definido')->after('tamano');
        });
    }

    public function down(): void
    {
        Schema::table('orden_items', function (Blueprint $table) {
            $table->dropColumn(['tamano', 'forma_pago']);
        });
    }
};
