<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversaciones', function (Blueprint $table) {
            $table->enum('canal', ['whatsapp', 'messenger', 'instagram'])
                  ->default('whatsapp')
                  ->after('zona');
            // null para WA (usa telefono como identificador), PSID para Messenger, IGSID para Instagram
            $table->string('canal_id', 120)->nullable()->after('canal');

            $table->index('canal');
        });
    }

    public function down(): void
    {
        Schema::table('conversaciones', function (Blueprint $table) {
            $table->dropIndex(['canal']);
            $table->dropColumn(['canal', 'canal_id']);
        });
    }
};
