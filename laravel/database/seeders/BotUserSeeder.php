<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * BotUserSeeder — crea el usuario sistema del Agente IA.
 *
 * El bot nunca inicia sesión: la contraseña es aleatoria de 64 chars y
 * no se expone en ningún lugar. Todas sus acciones quedan en actividad_logs
 * usando ActividadLog::registrar(..., actorId: AiRouter::botUserId()).
 *
 * Seguro de correr múltiples veces (updateOrCreate).
 */
class BotUserSeeder extends Seeder
{
    public function run(): void
    {
        $bot = User::updateOrCreate(
            ['email' => 'bot@verdeo.com.ar'],
            [
                'name'     => 'Agente',
                'apellido' => 'IA',
                'role'     => 'bot',
                'password' => Hash::make(Str::random(64)),
            ]
        );

        $this->command->info("Bot user OK — ID: {$bot->id}, email: {$bot->email}");
    }
}
