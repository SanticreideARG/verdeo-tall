<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'      => 'Admin Verdeo',
                'email'     => 'admin@verdeo.com.ar',
                'password'  => Hash::make('verdeo2026'),
                'role'      => 'admin',
                'whatsapp'  => '+5491100000001',
                'direccion' => 'Av. Corrientes 1234, CABA',
                'zona'      => 'CABA',
            ],
            [
                'name'      => 'Responsable Norte',
                'email'     => 'zona.norte@verdeo.com.ar',
                'password'  => Hash::make('verdeo2026'),
                'role'      => 'responsable_zona',
                'whatsapp'  => '+5491100000002',
                'direccion' => 'Av. Maipú 500, Vicente López',
                'zona'      => 'Norte GBA',
            ],
            [
                'name'      => 'Colaborador Test',
                'email'     => 'colaborador@verdeo.com.ar',
                'password'  => Hash::make('verdeo2026'),
                'role'      => 'colaborador',
                'whatsapp'  => '+5491100000003',
                'direccion' => 'Calle Falsa 123, CABA',
                'zona'      => 'CABA',
            ],
            [
                'name'      => 'Cliente Ejemplo',
                'email'     => 'cliente@verdeo.com.ar',
                'password'  => Hash::make('verdeo2026'),
                'role'      => 'cliente',
                'whatsapp'  => '+5491100000004',
                'direccion' => 'Rivadavia 4567, CABA',
                'zona'      => 'CABA',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }
    }
}
