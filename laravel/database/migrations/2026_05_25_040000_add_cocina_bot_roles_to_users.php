<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role
             ENUM('admin','responsable_zona','colaborador','cocina','cliente','bot')
             NOT NULL DEFAULT 'colaborador'"
        );
    }

    public function down(): void
    {
        // Migrate any 'cocina'/'bot' users back to 'colaborador' before shrinking enum
        DB::statement("UPDATE users SET role = 'colaborador' WHERE role IN ('cocina','bot')");
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role
             ENUM('admin','responsable_zona','colaborador','cliente')
             NOT NULL DEFAULT 'colaborador'"
        );
    }
};
