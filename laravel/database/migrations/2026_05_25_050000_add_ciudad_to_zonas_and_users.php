<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            if (! Schema::hasColumn('zonas', 'ciudad')) {
                $table->string('ciudad', 80)->nullable()->after('slug')
                    ->comment('Ciudad a la que pertenece esta zona (agrupa zonas para la vista de cocina)');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ciudad')) {
                $table->string('ciudad', 80)->nullable()->after('zona')
                    ->comment('Ciudad asignada al usuario (filtra qué ve un rol cocina/transporte)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zonas', fn(Blueprint $t) => $t->dropColumn('ciudad'));
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('ciudad'));
    }
};
