<?php

namespace Database\Seeders;

use App\Models\Conversacion;
use App\Models\Orden;
use App\Models\OrdenItem;
use App\Models\Producto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ConversacionesRealistasSeeder extends Seeder
{
    private Carbon $hoy;

    public function run(): void
    {
        $this->hoy = Carbon::parse('2026-05-15');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        OrdenItem::truncate();
        DB::table('ordenes')->truncate();
        DB::table('conversaciones')->truncate();
        User::where('role', 'cliente')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Update producto prices
        $precios = [
            'keto'        => [65000, 80000],
            'anti_age'    => [65000, 80000],
            'vegetariano' => [60000, 75000],
            'real'        => [70000, 85000],
            'intuitivo'   => [60000, 75000],
        ];
        foreach ($precios as $tipo => [$p250, $p400]) {
            Producto::where('tipo', $tipo)->update(['precio_250g' => $p250, 'precio_400g' => $p400]);
        }

        foreach ($this->contactos() as $c) {
            $this->procesar($c);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function h(int $diasAtras, string $hora): string
    {
        return $this->hoy->copy()->subDays($diasAtras)->setTimeFromTimeString($hora)->toDateTimeString();
    }

    private function m(int $diasAtras, string $hora, string $from, string $texto): array
    {
        return ['from' => $from, 'texto' => $texto, 'at' => $this->h($diasAtras, $hora)];
    }

    private function cli(int $d, string $h, string $t): array { return $this->m($d, $h, 'cliente', $t); }
    private function ver(int $d, string $h, string $t): array { return $this->m($d, $h, 'verdeo', $t); }

    private function procesar(array $c): void
    {
        $msgs     = $c['mensajes'];
        $ultimo   = end($msgs);
        $estado   = $c['estado_conv'] ?? 'abierta';

        Conversacion::create([
            'zona'              => $c['zona'],
            'telefono'          => $c['telefono'],
            'nombre'            => $c['nombre'],
            'estado'            => $estado,
            'ultimo_mensaje'    => $ultimo['texto'],
            'ultimo_mensaje_at' => Carbon::parse($ultimo['at']),
            'mensajes'          => $msgs,
        ]);

        if (in_array($c['tipo'], ['pendiente', 'entregado'])) {
            $user = $this->crearUsuario($c);
            foreach ($c['ordenes'] as $od) {
                $this->crearOrden($user, $c['zona'], $od);
            }
        }
    }

    private function crearUsuario(array $c): User
    {
        [$nombre, $apellido] = $this->separarNombre($c['nombre']);
        $slug  = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', "{$nombre}.{$apellido}"));
        $email = "{$slug}@example.com";

        return User::create([
            'name'           => $nombre,
            'apellido'       => $apellido,
            'email'          => $email,
            'password'       => Hash::make('verdeo2026'),
            'role'           => 'cliente',
            'whatsapp'       => $c['telefono'],
            'zona'           => $c['zona'],
            'numero_cliente' => User::nextNumeroCliente(),
        ]);
    }

    private function crearOrden(User $user, string $zona, array $od): void
    {
        $orden = Orden::create([
            'numero'     => Orden::generarNumero(),
            'user_id'    => $user->id,
            'estado'     => $od['estado'],
            'zona'       => $zona,
            'notas'      => $od['notas'] ?? null,
            'total'      => 0,
            'created_at' => $od['fecha'],
            'updated_at' => $od['fecha'],
        ]);

        $total = 0;
        foreach ($od['items'] as $it) {
            $producto = Producto::where('tipo', $it['tipo'])->firstOrFail();
            $precio   = $it['tamano'] === '400g' ? $producto->precio_400g : $producto->precio_250g;
            $subtotal = $precio * $it['cantidad'];

            OrdenItem::create([
                'orden_id'        => $orden->id,
                'producto_id'     => $producto->id,
                'tamano'          => $it['tamano'],
                'forma_pago'      => $od['forma_pago'] ?? 'transferencia',
                'cantidad'        => $it['cantidad'],
                'precio_unitario' => $precio,
                'subtotal'        => $subtotal,
            ]);
            $total += $subtotal;
        }

        $orden->update(['total' => $total]);
    }

    private function separarNombre(string $nombre): array
    {
        $partes = explode(' ', trim($nombre), 2);
        return [$partes[0], $partes[1] ?? ''];
    }

    // ─────────────────────────────────────────────────────────────
    // Datos de los 24 contactos
    // ─────────────────────────────────────────────────────────────

    private function contactos(): array
    {
        return [

            // ══════════════════════════════════════════
            // CONSULTA (5) — sin pedidos
            // ══════════════════════════════════════════

            [
                'nombre' => 'Florencia Gómez', 'zona' => 'bsas', 'telefono' => '5491156789012',
                'tipo' => 'consulta', 'estado_conv' => 'abierta',
                'mensajes' => [
                    $this->cli(9, '10:15', 'Hola! Buen día, vi el Instagram de Verdeo y me llamó mucho la atención'),
                    $this->ver(9, '10:17', '¡Hola! Bienvenida a Verdeo. ¿Te cuento sobre nuestros planes semanales?'),
                    $this->cli(9, '10:18', 'Sí! Quisiera saber qué incluye el pack keto'),
                    $this->ver(9, '10:19', 'El Pack Keto es un menú semanal sin harinas ni cereales: proteínas magras, vegetales y grasas buenas. 250g ($65.000/sem) o 400g ($80.000/sem) con entrega los miércoles'),
                    $this->cli(9, '10:21', '¿Y cuántas comidas son por semana?'),
                    $this->ver(9, '10:22', 'Son 5 días, lunes a viernes. Cada pack trae todo para el almuerzo y la cena'),
                    $this->cli(9, '10:24', '¿Hacen entrega en Flores?'),
                    $this->ver(9, '10:25', 'Sí! Cubrimos toda CABA y GBA. La entrega es los miércoles por la mañana'),
                    $this->cli(9, '10:27', 'Lo voy a consultar con mi familia y les aviso, muchas gracias!'),
                    $this->ver(9, '10:28', 'Dale, cuando quieras. ¡Acá estamos para lo que necesites!'),
                ],
            ],

            [
                'nombre' => 'Martina Morales', 'zona' => 'valle_nqn', 'telefono' => '5492984234567',
                'tipo' => 'consulta', 'estado_conv' => 'abierta',
                'mensajes' => [
                    $this->cli(14, '16:42', 'Hola buenas tardes! ¿Hacen entrega en Centenario?'),
                    $this->ver(14, '16:44', 'Hola Martina! En este momento cubrimos Neuquén capital y Roca. Centenario queda un poco fuera por ahora, pero estamos ampliando'),
                    $this->cli(14, '16:45', 'Ah que pena... me interesa mucho el tema de la comida saludable'),
                    $this->ver(14, '16:46', '¿Te gustaría que te avisemos cuando sumemos Centenario? Va a ser en pocas semanas'),
                    $this->cli(14, '16:47', 'Sí please! Eso estaría buenísimo'),
                    $this->ver(14, '16:48', 'Perfecto, te anotamos. ¡Gracias por el interés, Martina!'),
                    $this->cli(14, '16:49', 'Gracias a ustedes. ¡Suerte con la expansión!'),
                ],
            ],

            [
                'nombre' => 'Paula Domínguez', 'zona' => 'bsas', 'telefono' => '5491189012345',
                'tipo' => 'consulta', 'estado_conv' => 'abierta',
                'mensajes' => [
                    $this->cli(18, '12:03', 'Hola! El pack vegetariano tiene productos de origen animal?'),
                    $this->ver(18, '12:05', '¡Hola Paula! Incluye huevos y queso, sin carnes. Si necesitás algo 100% vegano, podemos adaptarlo'),
                    $this->cli(18, '12:06', 'Ah perfecto, como ovo-lacto vegetariana está bien'),
                    $this->ver(18, '12:07', '¡Genial! Te mando el menú de esta semana para que veas. ¿Te parece?'),
                    $this->cli(18, '12:08', 'Sí manda!'),
                    $this->ver(18, '12:10', '📋 *Menú Vegetariano – Semana 20*\nLun: Tortilla española y ensalada\nMar: Guiso de lentejas con batata\nMié: Strudel de verduras con queso\nJue: Pizza integral con rúcula\nVie: Wok de tofu con vegetales'),
                    $this->cli(18, '12:16', 'Todo se ve muy rico! El lunes me comunico para hacer el primer pedido'),
                    $this->ver(18, '12:17', '¡Te esperamos! Cualquier duda por acá. ¡Que tengas linda semana!'),
                ],
            ],

            [
                'nombre' => 'Gabriela Rivas', 'zona' => 'mendoza', 'telefono' => '5492614567890',
                'tipo' => 'consulta', 'estado_conv' => 'abierta',
                'mensajes' => [
                    $this->cli(7, '09:30', 'Buenos días! Me interesa el pack anti-age. ¿Tienen algo para la inflamación?'),
                    $this->ver(7, '09:32', '¡Buenos días Gabriela! El Pack Anti-Age está formulado con ingredientes antiinflamatorios y antioxidantes. Cúrcuma, salmón, brócoli, frutos rojos y más'),
                    $this->cli(7, '09:33', 'Suena bien. ¿Tiene algo que no se pueda consumir con medicación habitual?'),
                    $this->ver(7, '09:35', 'Usamos solo ingredientes naturales sin suplementos. Te recomendaría consultarlo con tu médico si tomás algo específico, por las dudas'),
                    $this->cli(7, '09:36', 'Tiene sentido. ¿Y cuánto cuesta?'),
                    $this->ver(7, '09:37', 'El 250g a $65.000 y el 400g a $80.000 semanales, con entrega incluida en Mendoza capital'),
                    $this->cli(7, '09:39', 'Muy bien! Lo pienso y te mando mensaje esta semana'),
                    $this->ver(7, '09:40', 'Cuando quieras, ¡acá estamos! ¡Buen día, Gabriela!'),
                ],
            ],

            [
                'nombre' => 'Cecilia Cabrera', 'zona' => 'bsas', 'telefono' => '5491112345678',
                'tipo' => 'consulta', 'estado_conv' => 'abierta',
                'mensajes' => [
                    $this->cli(20, '20:14', 'Hola! ¿Hacen planes mensuales o solo por semana?'),
                    $this->ver(20, '20:16', '¡Hola Cecilia! Son semanales, pero muchos clientes repiten cada semana sin problema. ¿Qué pack te llama la atención?'),
                    $this->cli(20, '20:17', 'El keto. ¿Cómo funciona el pago?'),
                    $this->ver(20, '20:18', 'Podés pagar por transferencia antes de la entrega o en efectivo al momento. La semana se abona antes del martes'),
                    $this->cli(20, '20:20', 'Y si quiero saltar una semana, ¿hay cargo?'),
                    $this->ver(20, '20:21', '¡Ningún problema! Avisás antes del lunes y listo, sin costo extra'),
                    $this->cli(20, '20:23', 'Buenísimo. Lo hablo con mi marido y les escribo. Gracias!'),
                    $this->ver(20, '20:24', '¡Cuando quieran! ¡Saludos, Cecilia!'),
                ],
            ],

            // ══════════════════════════════════════════
            // PENDIENTE (10) — pedidos en curso
            // ══════════════════════════════════════════

            [
                'nombre' => 'Juan Pérez', 'zona' => 'bsas', 'telefono' => '5491158234567',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'aprobada',
                    'fecha'  => $this->h(3, '11:36'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'keto', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(3, '10:15', 'Hola! Quisiera hacer un pedido del pack keto'),
                    $this->ver(3, '10:16', '¡Hola Juan! Con gusto. ¿El pack de 250g o 400g?'),
                    $this->cli(3, '10:17', '400g para mí solo, suelo comer bastante jaja'),
                    $this->ver(3, '10:18', 'Perfecto, el 400g es ideal. Son $80.000 semanales. ¿En qué barrio de CABA estás?'),
                    $this->cli(3, '10:19', 'Caballito, Rivadavia 4200'),
                    $this->ver(3, '10:20', '¡Cubrimos Caballito! La entrega es los miércoles por la mañana. ¿Arrancamos esta semana?'),
                    $this->cli(3, '10:21', 'Dale sí. ¿Cómo pago?'),
                    $this->ver(3, '10:22', 'Transferencia a: CBU 0070006020000000123456, Alias: VERDEO.BSAS, titular Verdeo SA. Antes del martes a mediodía'),
                    $this->cli(3, '11:35', 'Listo! Ya transferí los 80k'),
                    $this->ver(3, '11:36', '¡Recibido Juan! Pedido confirmado. El miércoles te avisamos el horario exacto. ¡Gracias!'),
                    $this->cli(3, '11:37', 'Perfecto, gracias! Me da curiosidad el menú'),
                    $this->ver(3, '11:38', 'Te lo mandamos mañana con el detalle. ¡Ya vas a ver que está muy bueno!'),
                ],
            ],

            [
                'nombre' => 'Nicolás Bustos', 'zona' => 'valle_nqn', 'telefono' => '5492994234567',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'pendiente',
                    'fecha'  => $this->h(2, '18:16'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'real', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(2, '17:30', 'Buenas! ¿Qué tienen disponible para esta semana?'),
                    $this->ver(2, '17:31', '¡Hola Nico! Tenemos todos los packs: Keto, Anti-Age, Vegetariano, Real e Intuitivo. ¿Tenés alguna preferencia?'),
                    $this->cli(2, '17:32', 'El Real me interesa, el de sin ultraprocesados. ¿Qué incluye?'),
                    $this->ver(2, '17:33', 'El Pack Real es carnes frescas, vegetales de estación y preparaciones 100% caseras. Sin nada de lo que viene en caja. ¿En qué cantidad?'),
                    $this->cli(2, '17:34', '400g. ¿Cuánto sale?'),
                    $this->ver(2, '17:35', '$85.000 la semana con entrega los miércoles en Neuquén capital'),
                    $this->cli(2, '17:36', 'Bárbaro. ¿Me anotas? ¿Te paso la dirección?'),
                    $this->ver(2, '17:37', '¡Sí! Mandame la dirección y avisame cuando hagas la transferencia'),
                    $this->cli(2, '17:38', 'Calle San Martín 1250, piso 3, departamento A'),
                    $this->ver(2, '17:39', 'Anotado! El CBU: 0070004020000000456789, Alias: VERDEO.NQN'),
                    $this->cli(2, '18:15', 'Ya mandé la transferencia! Quedo pendiente de confirmación'),
                    $this->ver(2, '18:16', '¡Perfecto Nico! Estamos verificando, en un momento te confirmamos'),
                ],
            ],

            [
                'nombre' => 'Santiago Duarte', 'zona' => 'cordoba', 'telefono' => '5493514234567',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'lista_para_entrega',
                    'fecha'  => $this->h(4, '14:21'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'vegetariano', 'tamano' => '250g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(4, '14:00', 'Hola! Soy vegetariano y busco opciones de comida sana y variada'),
                    $this->ver(4, '14:02', '¡Hola Santiago! Perfecto, tenemos el Pack Vegetariano sin carnes, con queso y huevos, muy variado'),
                    $this->cli(4, '14:03', '¿Podría ver el menú de esta semana?'),
                    $this->ver(4, '14:04', 'Claro! 📋 *Menú Vegetariano – Semana 20*\nLun: Tortilla española con ensalada mixta\nMar: Guiso de lentejas con batata\nMié: Strudel de verduras con queso\nJue: Pizza integral muzzarella y rúcula\nVie: Wok de tofu con vegetales salteados'),
                    $this->cli(4, '14:09', 'Todo se ve muy bien! Me anoto con el de 250g'),
                    $this->ver(4, '14:10', '¡Genial! Son $60.000. ¿En qué barrio de Córdoba?'),
                    $this->cli(4, '14:11', 'Nueva Córdoba, Bv. Chacabuco 1200, piso 4 C'),
                    $this->ver(4, '14:12', '¡Cubrimos esa zona! Entrega el miércoles. CBU: 0070005120000000789012'),
                    $this->cli(4, '14:22', '[Comprobante de transferencia - $60.000]'),
                    $this->ver(4, '14:23', '¡Recibido! Tu pack está siendo preparado. Mañana te confirmamos el horario de entrega, Santi'),
                    $this->cli(4, '14:24', 'Perfecto! Gracias, se ve muy rico todo'),
                ],
            ],

            [
                'nombre' => 'Diego Villalba', 'zona' => 'mendoza', 'telefono' => '5492614234567',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'pendiente',
                    'fecha'  => $this->h(1, '09:46'),
                    'forma_pago' => 'en_destino',
                    'items'  => [['tipo' => 'keto', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(1, '09:00', 'Buen día! ¿Tienen pack keto disponible para esta semana todavía?'),
                    $this->ver(1, '09:02', '¡Buen día Diego! Sí, todavía tenemos lugares. ¿Para cuántas personas?'),
                    $this->cli(1, '09:03', 'Para mí solo. El 400g'),
                    $this->ver(1, '09:04', '$80.000 la semana con entrega los miércoles. ¿En qué zona de Mendoza estás?'),
                    $this->cli(1, '09:05', 'Godoy Cruz, calle Rivadavia 520'),
                    $this->ver(1, '09:06', '¡Perfecto! Cubrimos Godoy Cruz. ¿Pagás con transferencia o en el momento de la entrega?'),
                    $this->cli(1, '09:07', 'Mejor en el momento si se puede'),
                    $this->ver(1, '09:08', '¡Ningún problema! Anotado para el miércoles, Diego'),
                    $this->cli(1, '09:45', 'Ah y tengo una consulta, ¿puedo pedir sin sal?'),
                    $this->ver(1, '09:46', 'Por supuesto! Anotamos la aclaración en tu pedido. ¡Hasta el miércoles!'),
                ],
            ],

            [
                'nombre' => 'Tomás Paredes', 'zona' => 'bsas', 'telefono' => '5491167893412',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'aprobada',
                    'fecha'  => $this->h(5, '12:06'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'anti_age', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(5, '11:20', 'Hola! Mi nutricionista me recomendó comer más antiinflamatorio. ¿Tienen algo así?'),
                    $this->ver(5, '11:22', '¡Hola Tomás! Sí, tenemos el Pack Anti-Age que es exactamente eso. ¿Te mando la descripción?'),
                    $this->cli(5, '11:23', 'Sí, por favor'),
                    $this->ver(5, '11:24', '🌿 *Pack Anti-Age*: ingredientes antiinflamatorios y antioxidantes: salmón, cúrcuma, brócoli, semillas y frutos rojos. Sin gluten, sin azúcar agregada'),
                    $this->cli(5, '11:26', '¿El 400g alcanza para almuerzo y cena?'),
                    $this->ver(5, '11:27', 'Sí! El 400g está calculado para almuerzo y cena de una persona'),
                    $this->cli(5, '11:28', 'Me anoto. ¿Cuánto?'),
                    $this->ver(5, '11:29', '$80.000 la semana. ¿Estás en CABA o GBA?'),
                    $this->cli(5, '11:30', 'Villa del Parque, Artigas 1800'),
                    $this->ver(5, '11:31', '¡Cubierto! Te paso el CBU: 0070006020000000123456'),
                    $this->cli(5, '12:05', 'Listo, ya hice la transferencia de los $80.000'),
                    $this->ver(5, '12:06', '¡Confirmado Tomás! Pedido anotado para el miércoles. ¡Vas a notar la diferencia!'),
                    $this->cli(5, '12:07', 'Espero que sí! Gracias'),
                ],
            ],

            [
                'nombre' => 'María González', 'zona' => 'bsas', 'telefono' => '5491123456789',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'aprobada',
                    'fecha'  => $this->h(6, '20:16'),
                    'forma_pago' => 'transferencia',
                    'items'  => [
                        ['tipo' => 'real', 'tamano' => '400g', 'cantidad' => 1],
                        ['tipo' => 'real', 'tamano' => '400g', 'cantidad' => 1],
                    ],
                ]],
                'mensajes' => [
                    $this->cli(6, '19:30', 'Hola! ¿Puedo pedir dos packs iguales para dos personas?'),
                    $this->ver(6, '19:32', '¡Hola María! Por supuesto. ¿Cuál pack les interesa?'),
                    $this->cli(6, '19:33', 'El Real para los dos. ¿Hacen entrega en Palermo?'),
                    $this->ver(6, '19:34', 'Sí, cubrimos Palermo. 2 packs 400g son $170.000 la semana con entrega incluida'),
                    $this->cli(6, '19:36', '¿Cada cuánto se entrega?'),
                    $this->ver(6, '19:37', 'Cada miércoles. Muchos clientes repiten automáticamente semana a semana'),
                    $this->cli(6, '19:38', 'Bien, empezamos. ¿La dirección va acá?'),
                    $this->ver(6, '19:39', 'Sí! Mandame la dirección y el comprobante cuando puedas'),
                    $this->cli(6, '19:40', 'Av. Santa Fe 3200, piso 5 B'),
                    $this->cli(6, '20:15', 'Hice la transfe de $170.000. ¡Quedamos!'),
                    $this->ver(6, '20:16', '¡Perfecto! Todo confirmado. ¡Hasta el miércoles, María!'),
                    $this->cli(6, '20:17', 'Gracias! Que ricos que van a quedar'),
                ],
            ],

            [
                'nombre' => 'Lucía Fernández', 'zona' => 'valle_nqn', 'telefono' => '5492994345678',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'pendiente',
                    'fecha'  => $this->h(3, '16:31'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'keto', 'tamano' => '250g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(3, '15:45', 'Buenas tardes! Hace tiempo que quiero empezar keto pero no sé por dónde arrancar'),
                    $this->ver(3, '15:47', '¡Hola Lucía! El Pack Keto es ideal para arrancar, viene todo calculado para que no tengas que pensar en macros ni preparación'),
                    $this->cli(3, '15:48', 'Eso es lo que necesito jaja. ¿El 250g alcanza para el mediodía?'),
                    $this->ver(3, '15:49', 'Sí, el 250g es perfecto para una persona al almuerzo. Si querés también cena, el 400g cubre ambas'),
                    $this->cli(3, '15:50', 'Arranco con el 250g a probar. ¿Cuánto?'),
                    $this->ver(3, '15:51', '$65.000 la semana con entrega en Neuquén capital'),
                    $this->cli(3, '15:52', 'Dale! ¿Cómo hago?'),
                    $this->ver(3, '15:53', '¡Genial! Te mando el CBU: 0070004020000000456789. ¿La dirección?'),
                    $this->cli(3, '15:54', 'Los Sauces etapa 2, casa 45, Plottier'),
                    $this->ver(3, '15:55', 'Anotado! Avisame cuando hagas la transfe'),
                    $this->cli(3, '16:30', 'Transferí! Pero no me llegó confirmación todavía'),
                    $this->ver(3, '16:31', 'A veces tarda unos minutos. Confirmado de nuestra parte, Lucía! ¡El miércoles llega!'),
                ],
            ],

            [
                'nombre' => 'Valentina Rodríguez', 'zona' => 'cordoba', 'telefono' => '5493514345678',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'pendiente',
                    'fecha'  => $this->h(2, '09:02'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'vegetariano', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(2, '08:30', 'Hola! Quiero hacer un pedido antes de que cierren la semana'),
                    $this->ver(2, '08:32', '¡Hola Valentina! Por supuesto, todavía estamos a tiempo. ¿Qué pack querés?'),
                    $this->cli(2, '08:33', 'Vegetariano 400g, para el almuerzo y la cena'),
                    $this->ver(2, '08:34', '¡Genial! $75.000 con entrega el miércoles en Córdoba capital. ¿El barrio?'),
                    $this->cli(2, '08:35', 'Centro, Colón 1450, piso 2, Dpto C'),
                    $this->ver(2, '08:36', 'Anotado! CBU: 0070005120000000789012 para la transferencia'),
                    $this->cli(2, '08:58', 'Listo, transferí!'),
                    $this->ver(2, '09:02', '¡Verificando el pago, Valentina! En un momento confirmamos ✔️'),
                ],
            ],

            [
                'nombre' => 'Camila López', 'zona' => 'mendoza', 'telefono' => '5492614345678',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'aprobada',
                    'fecha'  => $this->h(7, '13:22'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'real', 'tamano' => '250g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(7, '13:00', 'Hola! Vi que tienen el Pack Real sin ultraprocesados. ¿El 250g está bien para una persona al mediodía?'),
                    $this->ver(7, '13:02', '¡Hola Cami! Sí, el 250g es ideal para el almuerzo. Si querés cubrirte también la cena, el 400g da para ambas'),
                    $this->cli(7, '13:03', 'Arranco a probar con el 250g. ¿Cuánto?'),
                    $this->ver(7, '13:04', '$70.000 la semana con entrega en Mendoza. ¿Estás en capital?'),
                    $this->cli(7, '13:05', 'Maipú, calle Ozamis 1300'),
                    $this->ver(7, '13:06', '¡Cubrimos Maipú! Entrega los miércoles por la mañana. CBU: 0070009920000000345678'),
                    $this->cli(7, '13:20', 'Les paso el comprobante 👇'),
                    $this->cli(7, '13:21', '[Comprobante transferencia $70.000]'),
                    $this->ver(7, '13:22', '¡Recibido! Pedido confirmado para el miércoles. ¡Gracias Cami, ya vas a ver qué rico!'),
                    $this->cli(7, '13:23', 'Gracias a ustedes! Que rico que suena todo en el menú'),
                ],
            ],

            [
                'nombre' => 'Julieta Martínez', 'zona' => 'bsas', 'telefono' => '5491145678901',
                'tipo' => 'pendiente', 'estado_conv' => 'abierta',
                'ordenes' => [[
                    'estado' => 'lista_para_entrega',
                    'fecha'  => $this->h(3, '10:06'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'keto', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(4, '17:00', 'Hola! Consulta: el pack keto 400g, ¿qué incluye exactamente?'),
                    $this->ver(4, '17:02', '¡Hola Juli! Incluye proteínas (pollo, carne, pescado), vegetales sin almidón y grasas buenas (palta, aceite de oliva, frutos secos). Sin harinas ni azúcar'),
                    $this->cli(4, '17:03', 'Perfecto para mi dieta. ¿Cuánto vale?'),
                    $this->ver(4, '17:04', '$80.000 la semana. ¿En qué barrio de CABA?'),
                    $this->cli(4, '17:05', 'Belgrano, Arribeños 2200 piso 1'),
                    $this->ver(4, '17:06', 'Anotado! Te paso el CBU: 0070006020000000123456. Avisame cuando hagas la transfe'),
                    $this->cli(3, '09:30', 'Buen día! Hice el pago ayer a la noche, ¿lo vieron?'),
                    $this->ver(3, '09:31', '¡Buen día Juli! Sí, lo recibimos. Disculpá la demora en confirmar'),
                    $this->cli(3, '10:05', 'Buenísimo! ¿El miércoles es la entrega?'),
                    $this->ver(3, '10:06', '¡Exacto! Tu pack ya está en preparación. Mañana te avisamos el horario exacto 🌿'),
                    $this->cli(3, '10:07', '¡Genial! Muchas gracias, con muchas ganas de probarlo'),
                ],
            ],

            // ══════════════════════════════════════════
            // ENTREGADO una vez (4)
            // ══════════════════════════════════════════

            [
                'nombre' => 'Pilar Ortiz', 'zona' => 'cordoba', 'telefono' => '5493514678901',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [[
                    'estado' => 'entregada',
                    'fecha'  => $this->h(21, '14:00'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'anti_age', 'tamano' => '250g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(23, '11:30', 'Hola! Quisiera probar el pack anti-age. ¿Cómo funciona?'),
                    $this->ver(23, '11:32', '¡Hola Pilar! Son menús semanales con ingredientes antiinflamatorios y antioxidantes, frescos y sin conservantes. ¿Preferís 250g o 400g?'),
                    $this->cli(23, '11:33', '250g para probar primero'),
                    $this->ver(23, '11:34', '$65.000 con entrega el miércoles en Córdoba capital. ¿Te anotamos?'),
                    $this->cli(23, '11:35', 'Sí! Córdoba centro, Hipólito Yrigoyen 650'),
                    $this->ver(23, '11:36', 'Anotado! CBU: 0070005120000000789012'),
                    $this->cli(23, '12:10', 'Listo, transferí!'),
                    $this->ver(23, '12:11', '¡Confirmado Pilar! El miércoles llega tu pack'),
                    $this->ver(21, '09:30', '¡Buen día! Tu pedido está en camino, llega en la mañana 🚗'),
                    $this->cli(21, '11:45', 'Llegó todo perfecto! Acabo de calentar el almuerzo y qué rico está todo'),
                    $this->ver(21, '11:46', '¡Qué alegría! ¿Te gustó el menú?'),
                    $this->cli(21, '11:47', 'Sí! El salmón con cúrcuma sorprendió mucho. La semana que viene pido el 400g'),
                    $this->ver(21, '11:48', '¡Bárbaro Pilar! Avisanos cuando quieras repetir. ¡Muchas gracias!'),
                ],
            ],

            [
                'nombre' => 'Victoria Sosa', 'zona' => 'mendoza', 'telefono' => '5492614678901',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [[
                    'estado' => 'entregada',
                    'fecha'  => $this->h(28, '10:00'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'intuitivo', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(30, '16:00', 'Hola! ¿Qué es el pack intuitivo? No lo entiendo bien'),
                    $this->ver(30, '16:02', '¡Hola Victoria! El Pack Intuitivo es alimentación consciente sin restricciones, variada y saludable. Sin sal, todo fresco. Ideal si no querés seguir un plan rígido'),
                    $this->cli(30, '16:03', 'Ah! Es justo lo que busco. ¿Tiene gluten?'),
                    $this->ver(30, '16:04', 'Tiene ingredientes con gluten pero podemos adaptarlo si tenés intolerancia'),
                    $this->cli(30, '16:05', 'No, estoy bien. Me anoto con el 400g'),
                    $this->ver(30, '16:06', '$75.000, entrega miércoles en Mendoza. CBU: 0070009920000000345678'),
                    $this->cli(30, '17:20', 'Transferido!'),
                    $this->ver(30, '17:21', '¡Recibido Victoria! Te esperamos el miércoles'),
                    $this->ver(28, '10:15', '¡Tu pedido llegó! ¿Todo bien?'),
                    $this->cli(28, '12:30', 'Sí! Todo bien, muy rico y abundante. El wok de quinoa estaba buenísimo'),
                    $this->ver(28, '12:31', '¡Genial! Nos alegramos mucho. ¿Repetimos la semana que viene?'),
                    $this->cli(28, '12:32', 'Sí, ya les aviso. ¡Gracias!'),
                ],
            ],

            [
                'nombre' => 'Andrea Aguirre', 'zona' => 'valle_nqn', 'telefono' => '5492994567890',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [[
                    'estado' => 'entregada',
                    'fecha'  => $this->h(35, '09:00'),
                    'forma_pago' => 'en_destino',
                    'items'  => [['tipo' => 'vegetariano', 'tamano' => '250g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(37, '13:00', 'Hola! Alguien me pasó el contacto. ¿Hacen entrega en Roca?'),
                    $this->ver(37, '13:02', '¡Hola Andrea! Sí, cubrimos Roca. ¿Qué pack te interesa?'),
                    $this->cli(37, '13:03', 'El vegetariano. Soy vegetariana hace 5 años así que ya estoy acostumbrada'),
                    $this->ver(37, '13:04', '¡Genial! Nuestro Pack Vegetariano es muy variado, te va a encantar. ¿250g o 400g?'),
                    $this->cli(37, '13:05', '250g para empezar'),
                    $this->ver(37, '13:06', '$60.000 con entrega en Roca. ¿Preferís pagar en el momento o por transferencia?'),
                    $this->cli(37, '13:07', 'En el momento me viene mejor'),
                    $this->ver(37, '13:08', '¡Sin problema! ¿La dirección?'),
                    $this->cli(37, '13:09', 'Av. Roca 1850, Roca'),
                    $this->ver(37, '13:10', 'Anotado! El miércoles llega. ¡Gracias Andrea!'),
                    $this->ver(35, '10:00', '¡Buen día! Tu pack está en camino, llega a la mañana'),
                    $this->cli(35, '11:30', 'Llegó! Muy rico todo, el guiso de lentejas estaba espectacular'),
                    $this->ver(35, '11:31', '¡Nos alegramos mucho! ¿Repetimos?'),
                    $this->cli(35, '11:32', 'Sin dudas! Próxima semana les escribo'),
                ],
            ],

            [
                'nombre' => 'Laura Navarro', 'zona' => 'cordoba', 'telefono' => '5493514567890',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [[
                    'estado' => 'entregada',
                    'fecha'  => $this->h(42, '15:00'),
                    'forma_pago' => 'transferencia',
                    'items'  => [['tipo' => 'real', 'tamano' => '400g', 'cantidad' => 1]],
                ]],
                'mensajes' => [
                    $this->cli(44, '10:00', 'Hola! Vi una reseña en Instagram de Verdeo. ¿Tienen el Pack Real disponible?'),
                    $this->ver(44, '10:02', '¡Hola Laura! Sí, el Pack Real está disponible. Carnes frescas y vegetales de estación, todo casero'),
                    $this->cli(44, '10:03', '¿Es apto para celíacos?'),
                    $this->ver(44, '10:04', 'Sí! El Pack Real no tiene harinas ni gluten. Te lo podemos asegurar'),
                    $this->cli(44, '10:05', 'Perfecto, lo que necesitaba. Me anoto con el 400g'),
                    $this->ver(44, '10:06', '$85.000 con entrega en Córdoba. CBU: 0070005120000000789012'),
                    $this->cli(44, '11:30', 'Ya transferí, quedo en espera!'),
                    $this->ver(44, '11:31', '¡Confirmado! El miércoles llega, Laura'),
                    $this->ver(42, '09:45', '¡Buenos días! Hoy lleva tu pedido nuestro delivery. ¿Estás en casa entre las 10 y las 12?'),
                    $this->cli(42, '09:46', 'Sí, estaré. Muchas gracias por avisar'),
                    $this->cli(42, '11:50', 'Llegó todo! Muy rico, y muy bien empacado. Gracias por la onda'),
                    $this->ver(42, '11:51', '¡A ustedes Laura! ¿Repetimos la semana que viene?'),
                    $this->cli(42, '11:52', 'Sí definitivamente. Les escribo el lunes'),
                ],
            ],

            // ══════════════════════════════════════════
            // ENTREGADO múltiple (5)
            // ══════════════════════════════════════════

            [
                'nombre' => 'Carla Romero', 'zona' => 'valle_nqn', 'telefono' => '5492994456789',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(45, '14:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'keto', 'tamano' => '400g', 'cantidad' => 1]],
                    ],
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(17, '14:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'keto', 'tamano' => '400g', 'cantidad' => 1]],
                        'notas'  => 'Segunda semana, cliente fiel',
                    ],
                ],
                'mensajes' => [
                    $this->cli(47, '09:15', 'Hola! Una amiga me recomendó Verdeo. ¿Tienen pack keto?'),
                    $this->ver(47, '09:17', '¡Hola Carla! Sí, el Pack Keto es uno de los más populares. ¿Te cuento?'),
                    $this->cli(47, '09:18', 'Sí! Y el precio también'),
                    $this->ver(47, '09:19', '250g ($65.000) o 400g ($80.000) semanal, con entrega en Neuquén los miércoles'),
                    $this->cli(47, '09:20', 'El 400g. Empiezo esta semana'),
                    $this->ver(47, '09:21', 'CBU: 0070004020000000456789. ¿Dirección?'),
                    $this->cli(47, '09:30', 'Belgrano 1150 NQN. Transfe hecha!'),
                    $this->ver(47, '09:31', '¡Confirmado! El miércoles llega tu primer pack'),
                    $this->ver(45, '10:30', '¡Tu pedido está en camino! Llega en la mañana 🌿'),
                    $this->cli(45, '12:00', 'Llegó! Todo buenísimo, el pack de salmón con palta estuvo increíble'),
                    $this->ver(45, '12:01', '¡Qué alegría! ¿Repetimos la semana que viene?'),
                    $this->cli(45, '12:02', 'Sin dudas! Ya les escribo el lunes'),
                    $this->cli(19, '08:00', 'Buen día! Quiero hacer el pedido de esta semana igual que el anterior'),
                    $this->ver(19, '08:02', '¡Hola Carla! Anotado, mismo pack. Ya sabés el CBU. Avisame la transfe'),
                    $this->cli(19, '09:10', 'Transferido!'),
                    $this->ver(19, '09:11', '¡Confirmado! El miércoles llega. ¡Gracias por volver!'),
                    $this->ver(17, '10:15', '¡Ya está en camino tu pedido!'),
                    $this->cli(17, '12:30', 'Llegó! De nuevo perfecto. Sigo la semana que viene seguro'),
                    $this->ver(17, '12:31', '¡Nos hace muy felices eso Carla! ¡Hasta la próxima!'),
                ],
            ],

            [
                'nombre' => 'Agustina Torres', 'zona' => 'cordoba', 'telefono' => '5493514456789',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(50, '11:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'vegetariano', 'tamano' => '250g', 'cantidad' => 1]],
                    ],
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(22, '11:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'vegetariano', 'tamano' => '400g', 'cantidad' => 1]],
                        'notas'  => 'Subió a 400g desde el segundo pedido',
                    ],
                ],
                'mensajes' => [
                    $this->cli(52, '14:00', 'Hola! ¿El pack vegetariano tiene opciones sin lactosa?'),
                    $this->ver(52, '14:02', '¡Hola Agustina! Podemos adaptar el menú sin lactosa, avisanos y lo preparamos así'),
                    $this->cli(52, '14:03', 'Perfecto! Empiezo con el 250g esta semana'),
                    $this->ver(52, '14:04', '$60.000 con entrega en Córdoba. ¿Dirección?'),
                    $this->cli(52, '14:05', 'General Paz 1400, Nueva Córdoba. Hago la transfe ahora'),
                    $this->ver(52, '14:30', 'Recibido! El miércoles llega tu primer pack sin lactosa'),
                    $this->ver(50, '09:00', '¡Buen día! Tu pedido va en camino 🌿'),
                    $this->cli(50, '11:30', 'Llegó! Delicioso todo. Me sorprendió la pizza integral'),
                    $this->ver(50, '11:31', '¡Qué bien! ¿Repetimos?'),
                    $this->cli(50, '11:32', 'Sí! Y la semana que viene subí al 400g, quiero el almuerzo y la cena'),
                    $this->ver(50, '11:33', '¡Genial! Tomamos nota para la próxima'),
                    $this->cli(24, '09:00', 'Buen día! Pedido de esta semana, 400g vegetariano sin lactosa'),
                    $this->ver(24, '09:02', '¡Hola Agus! Anotado. CBU de siempre. Avisame la transfe'),
                    $this->cli(24, '10:15', 'Listo!'),
                    $this->ver(24, '10:16', '¡Confirmado!'),
                    $this->ver(22, '10:00', '¡En camino tu pedido!'),
                    $this->cli(22, '12:00', 'Llegó todo! El wok de tofu de esta semana estaba muy bueno'),
                    $this->ver(22, '12:01', '¡Nos alegra muchísimo! ¡Hasta la próxima Agus!'),
                ],
            ],

            [
                'nombre' => 'Daniela Herrera', 'zona' => 'mendoza', 'telefono' => '5492614456789',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(70, '10:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'anti_age', 'tamano' => '250g', 'cantidad' => 1]],
                    ],
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(49, '10:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'anti_age', 'tamano' => '400g', 'cantidad' => 1]],
                        'notas'  => 'Segundo pedido, subió de tamaño',
                    ],
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(28, '10:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [
                            ['tipo' => 'anti_age', 'tamano' => '400g', 'cantidad' => 1],
                            ['tipo' => 'keto',     'tamano' => '400g', 'cantidad' => 1],
                        ],
                        'notas'  => 'Tercer pedido, suma pack keto para su marido',
                    ],
                ],
                'mensajes' => [
                    $this->cli(72, '11:00', 'Hola! Me interesa el pack anti-age para mejorar mi piel y energía'),
                    $this->ver(72, '11:02', '¡Hola Daniela! El Pack Anti-Age es ideal para eso. Cúrcuma, salmón, frutos rojos, semillas de lino... todo antiinflamatorio y antioxidante'),
                    $this->cli(72, '11:03', 'Empiezo con el 250g para probar'),
                    $this->ver(72, '11:04', '$65.000 con entrega en Mendoza capital. ¿Dirección?'),
                    $this->cli(72, '11:15', 'Belgrano 1800 Godoy Cruz. Hago la transfe ahora'),
                    $this->ver(72, '11:40', 'Recibido! El miércoles llega tu pack'),
                    $this->ver(70, '10:30', '¡Tu pedido en camino!'),
                    $this->cli(70, '12:00', 'Llegó! Muy bueno, aunque el 250g me quedó poco para la cena también'),
                    $this->ver(70, '12:01', '¡El 400g da perfecto para almuerzo y cena! ¿Probamos la semana que viene?'),
                    $this->cli(70, '12:02', 'Sí, hago el cambio. Gracias!'),
                    $this->cli(51, '08:30', 'Buen día! Esta semana quiero el 400g anti-age'),
                    $this->ver(51, '08:32', '¡Genial Daniela! Mismo CBU. Avisame la transfe'),
                    $this->cli(51, '09:00', 'Listo!'),
                    $this->ver(49, '10:00', '¡Entregado! ¿Todo bien?'),
                    $this->cli(49, '11:30', 'Sí! Excelente. Mi marido también quiere probar, ¿pueden ser dos packs distintos?'),
                    $this->ver(49, '11:31', '¡Por supuesto! ¿Qué pack elegiría él?'),
                    $this->cli(49, '11:32', 'El keto creo. El próximo miércoles hacemos los dos'),
                    $this->ver(49, '11:33', 'Anotado! La semana que viene: 1 anti-age + 1 keto'),
                    $this->cli(30, '09:00', 'Buen día! El pedido de esta semana: anti-age 400g y keto 400g para él'),
                    $this->ver(30, '09:02', '¡Hola! Anotado. Total: $160.000. CBU de siempre'),
                    $this->cli(30, '10:00', 'Transferido!'),
                    $this->ver(28, '11:00', '¡Entregado! Dos packs listos 🌿'),
                    $this->cli(28, '12:30', 'Todo perfecto! A él le encantó el keto, "¿todos los miércoles así?" jaja'),
                    $this->ver(28, '12:31', 'Jajaja ¡bienvenido al club! ¡Hasta la próxima Daniela!'),
                ],
            ],

            [
                'nombre' => 'Sofía Castro', 'zona' => 'bsas', 'telefono' => '5491178901234',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(55, '09:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'real', 'tamano' => '400g', 'cantidad' => 1]],
                    ],
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(34, '09:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [['tipo' => 'real', 'tamano' => '400g', 'cantidad' => 1]],
                        'notas'  => 'Segundo pedido fiel',
                    ],
                ],
                'mensajes' => [
                    $this->cli(57, '10:00', 'Hola! Me interesa el Pack Real, sin ultraprocesados. ¿Tienen entrega en Recoleta?'),
                    $this->ver(57, '10:02', '¡Hola Sofía! Sí, cubrimos Recoleta. El Pack Real es todo fresco y casero, sin nada de lo que viene en caja'),
                    $this->cli(57, '10:03', '400g, para almuerzo y cena. ¿Cuánto?'),
                    $this->ver(57, '10:04', '$85.000. ¿La dirección?'),
                    $this->cli(57, '10:05', 'Av. del Libertador 1420, piso 3'),
                    $this->ver(57, '10:15', 'Recibido pago! El miércoles llega'),
                    $this->ver(55, '09:15', '¡Tu pedido en camino, Sofía!'),
                    $this->cli(55, '11:00', 'Llegó! La milanesa casera de ayer al horno... nunca comí algo tan rico en años'),
                    $this->ver(55, '11:01', '¡Jajaja! ¡Que alegría! ¿Repetimos?'),
                    $this->cli(55, '11:02', 'Obvio! La semana que viene les escribo'),
                    $this->cli(36, '08:30', 'Buenas! Mismo pedido, semana que viene'),
                    $this->ver(36, '08:32', '¡Hola Sofía! Anotado. CBU de siempre. ¡Gracias por volver!'),
                    $this->cli(36, '09:00', 'Transferido!'),
                    $this->ver(34, '10:00', '¡Entregado!'),
                    $this->cli(34, '11:30', 'Todo perfecto como siempre. Verdeo ya es parte de mi semana'),
                    $this->ver(34, '11:31', '¡Eso nos hace muy felices! ¡Hasta la próxima!'),
                ],
            ],

            [
                'nombre' => 'Mariana Méndez', 'zona' => 'bsas', 'telefono' => '5491190123456',
                'tipo' => 'entregado', 'estado_conv' => 'cerrada',
                'ordenes' => [
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(60, '10:00'),
                        'forma_pago' => 'en_destino',
                        'items'  => [['tipo' => 'intuitivo', 'tamano' => '400g', 'cantidad' => 1]],
                    ],
                    [
                        'estado' => 'entregada',
                        'fecha'  => $this->h(39, '10:00'),
                        'forma_pago' => 'transferencia',
                        'items'  => [
                            ['tipo' => 'intuitivo', 'tamano' => '400g', 'cantidad' => 1],
                            ['tipo' => 'anti_age',  'tamano' => '250g', 'cantidad' => 1],
                        ],
                        'notas'  => 'Segundo pedido, agrega anti-age 250g para probar',
                    ],
                ],
                'mensajes' => [
                    $this->cli(62, '15:30', 'Hola! Estoy buscando algo saludable pero sin dietas estrictas. ¿Tienen algo así?'),
                    $this->ver(62, '15:32', '¡Hola Mariana! El Pack Intuitivo es exactamente eso: alimentación consciente y variada sin restricciones. Sin sal añadida, todo fresco'),
                    $this->cli(62, '15:33', 'Suena perfecto! ¿Tiene buen volumen el 400g?'),
                    $this->ver(62, '15:34', 'Sí, cubre almuerzo y cena cómodamente. ¿En qué barrio estás?'),
                    $this->cli(62, '15:35', 'Almagro, Av. Corrientes 3800'),
                    $this->ver(62, '15:36', '¡Cubierto! $75.000, podés pagar en el momento si preferís'),
                    $this->cli(62, '15:37', 'Sí, en el momento mejor para empezar'),
                    $this->ver(62, '15:38', '¡Anotado! El miércoles llega'),
                    $this->ver(60, '09:30', '¡En camino tu primer pack Intuitivo!'),
                    $this->cli(60, '11:00', 'Llegó! Me encantó la variedad. El bowl de quinoa con vegetales asados estaba increíble'),
                    $this->ver(60, '11:01', '¡Qué bueno! ¿Repetimos?'),
                    $this->cli(60, '11:02', 'Sí! Y me comentaron del anti-age, ¿puedo pedir los dos juntos?'),
                    $this->ver(60, '11:03', '¡Claro! La próxima semana mandamos intuitivo 400g + anti-age 250g'),
                    $this->cli(41, '08:00', 'Buen día! El pedido de esta semana, los dos packs'),
                    $this->ver(41, '08:02', '¡Hola Mariana! Total: $140.000 ($75k + $65k). CBU: 0070006020000000123456'),
                    $this->cli(41, '09:30', 'Transferido!'),
                    $this->ver(39, '10:00', '¡Entregado! Dos packs listos'),
                    $this->cli(39, '12:00', 'Perfecto todo! El anti-age también me gustó mucho. Sigo alternando los dos'),
                    $this->ver(39, '12:01', '¡Bárbaro! Acá estamos para cuando quieras. ¡Gracias Mariana!'),
                ],
            ],

        ];
    }
}
