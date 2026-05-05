<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Conversacion;

class ConversacionSeeder extends Seeder
{
    public function run(): void
    {
        // 40 historic conversations spread across zones
        Conversacion::factory()->count(40)->create();

        // Ensure some are created today so dashboard stats show non-zero
        Conversacion::factory()->count(8)->create([
            'created_at'        => now(),
            'ultimo_mensaje_at' => now(),
            'estado'            => 'abierta',
        ]);
    }
}
