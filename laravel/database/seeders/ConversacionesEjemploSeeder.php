<?php

namespace Database\Seeders;

use App\Models\Conversacion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ConversacionesEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = User::all();

        if ($usuarios->isEmpty()) {
            $this->command->warn('No hay usuarios. Creá al menos uno primero.');
            return;
        }

        $zonas  = ['bsas', 'valle_nqn', 'cordoba', 'mendoza'];
        $estados = ['abierta', 'abierta', 'abierta', 'esperando', 'cerrada'];

        $mensajesWa = [
            'Hola! Quería hacer un pedido para esta semana',
            'Cuándo llega el reparto por mi zona?',
            'Sigo esperando el pedido del jueves...',
            'Hola buen día, me podés confirmar el menú de hoy?',
            'Quiero agregar un ítem más a mi pedido si es posible',
            'Muchísimas gracias! Ya llegó todo perfecto 🙌',
            'Hola, soy nueva por acá, cómo hago para pedir?',
            'Me pueden dar info de precios para el mes que viene?',
        ];

        $mensajesMsn = [
            'Hola vi tu página en Facebook! Cómo puedo pedir?',
            'Me interesa el menú de la semana, me mandás info?',
            'Buen día! Vi el post de hoy y quiero hacer un pedido',
            'Tienen envío a Palermo? Pregunté por acá porque no encuentro el número',
            'Seguís con la promo de los jueves?',
            'Hola! Quería saber si hacen entrega en mi zona',
            'Me recomendaron por Facebook, quiero probar el servicio',
            'Veo que tienen muy buenas reseñas, quiero pedir!',
        ];

        $mensajesIg = [
            'Hola vi tu story! 🌿 Cómo pido?',
            'Me encantó el reel de los menús! Tienen para esta semana?',
            'Hola! Vi que postean todos los días, quiero sumarme',
            'Quiero pedir pero no sé cómo. Me ayudás?',
            'Super rico todo lo que muestran 😍 Quiero probar!',
            'Mandame info por DM please! Vi el post de hoy',
            'Chicos son re geniales los menús, cómo me anoto?',
            'Hola llegué por el repost de @amiga, me interesa!',
        ];

        $psidBase  = 1000000000000;  // PSIDs de Messenger (12 dígitos)
        $igidBase  = 17841400000000000;  // IGSIDs (17 dígitos)

        $creadas = 0;

        foreach ($usuarios as $idx => $usuario) {
            $zona = $usuario->zona ?? $zonas[$idx % 4];

            // Distribución: 2 WhatsApp + 1 Messenger + 1 Instagram por usuario
            $convs = [
                [
                    'canal'           => 'whatsapp',
                    'canal_id'        => null,
                    'telefono'        => $usuario->whatsapp ?? ('549' . str_pad($idx * 100 + 11, 10, '0', STR_PAD_LEFT)),
                    'nombre'          => $usuario->name . ' (WA 1)',
                    'ultimo_mensaje'  => $mensajesWa[($idx * 2) % count($mensajesWa)],
                    'estado'          => $estados[$idx % count($estados)],
                    'ultimo_mensaje_at' => Carbon::now()->subHours(rand(1, 72)),
                ],
                [
                    'canal'           => 'whatsapp',
                    'canal_id'        => null,
                    'telefono'        => $usuario->whatsapp ? $usuario->whatsapp . '2' : ('549' . str_pad($idx * 100 + 22, 10, '0', STR_PAD_LEFT)),
                    'nombre'          => $usuario->name . ' (WA 2)',
                    'ultimo_mensaje'  => $mensajesWa[($idx * 2 + 1) % count($mensajesWa)],
                    'estado'          => $estados[($idx + 1) % count($estados)],
                    'ultimo_mensaje_at' => Carbon::now()->subHours(rand(2, 96)),
                ],
                [
                    'canal'           => 'messenger',
                    'canal_id'        => (string) ($psidBase + $idx * 17 + 3),
                    'telefono'        => null,
                    'nombre'          => $usuario->name . ' (Messenger)',
                    'ultimo_mensaje'  => $mensajesMsn[$idx % count($mensajesMsn)],
                    'estado'          => $estados[($idx + 2) % count($estados)],
                    'ultimo_mensaje_at' => Carbon::now()->subHours(rand(1, 48)),
                ],
                [
                    'canal'           => 'instagram',
                    'canal_id'        => (string) ($igidBase + $idx * 31 + 7),
                    'telefono'        => null,
                    'nombre'          => $usuario->name . ' (Instagram)',
                    'ultimo_mensaje'  => $mensajesIg[$idx % count($mensajesIg)],
                    'estado'          => $estados[($idx + 3) % count($estados)],
                    'ultimo_mensaje_at' => Carbon::now()->subHours(rand(1, 24)),
                ],
            ];

            foreach ($convs as $data) {
                Conversacion::create(array_merge($data, [
                    'zona'       => $zona,
                    'created_at' => Carbon::now()->subDays(rand(1, 30)),
                ]));
                $creadas++;
            }
        }

        $this->command->info("Creadas {$creadas} conversaciones de ejemplo para {$usuarios->count()} usuarios.");
    }
}
