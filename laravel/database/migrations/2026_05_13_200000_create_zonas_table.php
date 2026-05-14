<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zonas', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('alcance')->nullable();
            $table->text('caracteristica')->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('precio_400g')->nullable();
            $table->unsignedInteger('precio_250g')->nullable();
            $table->json('menus_semanales')->nullable();
            $table->boolean('activa')->default(true);
            $table->string('whatsapp')->nullable();
            $table->string('modelo_ia')->default('llama3.2');
            $table->timestamps();
        });

        // Seed existing zones
        $now = now();
        DB::table('zonas')->insert([
            ['slug' => 'bsas',      'nombre' => 'Buenos Aires',     'whatsapp' => '5491158393179', 'modelo_ia' => 'llama3.2', 'activa' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'valle_nqn', 'nombre' => 'Valle NQN / Roca', 'whatsapp' => '5492995493102', 'modelo_ia' => 'llama3.2', 'activa' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'cordoba',   'nombre' => 'Córdoba',          'whatsapp' => '5493513007925', 'modelo_ia' => 'llama3.2', 'activa' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'mendoza',   'nombre' => 'Mendoza',          'whatsapp' => '5492615117163', 'modelo_ia' => 'llama3.2', 'activa' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('zonas');
    }
};
