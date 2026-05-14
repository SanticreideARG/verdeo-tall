<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces', function (Blueprint $table) {
            $table->string('categoria', 60)->nullable()->after('descripcion');
            $table->unsignedInteger('clicks')->default(0)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('enlaces', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'clicks']);
        });
    }
};
