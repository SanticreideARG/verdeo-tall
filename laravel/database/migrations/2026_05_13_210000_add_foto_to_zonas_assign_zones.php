<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('whatsapp');
        });

        // Assign a random zone to colaboradores/responsables without one
        $slugs = DB::table('zonas')->pluck('slug')->toArray();
        if (! empty($slugs)) {
            DB::table('users')
                ->whereIn('role', ['colaborador', 'responsable_zona'])
                ->where(function ($q) { $q->whereNull('zona')->orWhere('zona', ''); })
                ->get()
                ->each(function ($user) use ($slugs) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['zona' => $slugs[array_rand($slugs)]]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('zonas', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
