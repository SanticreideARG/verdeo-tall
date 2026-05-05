<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE users SET role = 'colaborador' WHERE role = 'operator'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','responsable_zona','colaborador','cliente') NOT NULL DEFAULT 'colaborador'");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET role = 'admin' WHERE role NOT IN ('admin', 'operator')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','operator') NOT NULL DEFAULT 'operator'");
    }
};
