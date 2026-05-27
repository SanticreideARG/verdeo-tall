<?php

namespace Database\Seeders;

use App\Models\Conversacion;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestConversacionesMulticanalSeeder extends Seeder
{
    private Carbon $ahora;

    public function run(): void
    {
        $this->ahora = Carbon::now();

        foreach ($this->conversaciones() as $data) {
            $msgs   = $data['mensajes'];
            $ultimo = end($msgs);

            Conversacion::create([
                'zona'              => $data['zona'],
                'canal'             => $data['canal'],
                'canal_id'          => $data['canal_id'] ?? null,
                'telefono'          => $data['telefono'] ?? null,
                'nombre'            => $data['nombre'],
                'estado'            => $data['estado'],
                'ultimo_mensaje'    => $ultimo['texto'],
                'ultimo_mensaje_at' => Carbon::parse($ultimo['at']),
                'mensajes'          => $msgs,
            ]);
        }

        $this->command->info('✓ 24 conversaciones de prueba creadas (12 WA · 6 Messenger · 6 Instagram)');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function msg(string $from, string $texto, int $minutosAtras): array
    {
        return [
            'from'  => $from,
            'texto' => $texto,
            'at'    => $this->ahora->copy()->subMinutes($minutosAtras)->toDateTimeString(),
        ];
    }

    private function cli(string $t, int $m): array { return $this->msg('cliente', $t, $m); }
    private function ver(string $t, int $m): array { return $this->msg('verdeo', $t, $m); }

    // ── Conversaciones ───────────────────────────────────────────────────────

    private function conversaciones(): array
    {
        return [

            // ═══════════════════════════════════════════════════════════════
            // WHATSAPP — 12 conversaciones (nuevos, sin registrar)
            // ═══════════════════════════════════════════════════════════════

            [
                'canal' => 'whatsapp', 'zona' => 'bsas', 'telefono' => '5491162301001',
                'nombre' => 'Lucas Pérez', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! Vi que tienen pack keto, ¿me cuentan más?', 48),
                    $this->ver('¡Hola Lucas! Claro. El Pack Keto es sin harinas ni cereales, con proteínas magras, vegetales y grasas buenas. ¿Preferís 250 Kcal o 400 Kcal?', 46),
                    $this->cli('¿Cuál es la diferencia práctica entre los dos?', 44),
                    $this->ver('El 250 Kcal alcanza para el almuerzo de una persona. El 400 Kcal cubre almuerzo y cena. Ambos de lunes a viernes', 42),
                    $this->cli('¿Y cuánto sale cada uno?', 40),
                    $this->ver('Keto 250 Kcal → $65.000/sem · 400 Kcal → $80.000/sem. Entrega los miércoles en CABA y GBA', 38),
                    $this->cli('¿Hacen entrega en Flores?', 36),
                    $this->ver('¡Sí! Cubrimos toda CABA. La entrega es los miércoles por la mañana', 34),
                    $this->cli('Buenísimo! Me pongo de acuerdo con mi pareja y les escribo', 30),
                    $this->ver('¡Cuando quieran, acá estamos! 🌿', 28),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'bsas', 'telefono' => '5491162301002',
                'nombre' => 'Valentina Castro', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Buenas! ¿Cómo funciona lo del pedido?', 130),
                    $this->ver('¡Hola Valentina! Elegís el pack y el tamaño, te paso el CBU, hacés la transferencia y confirmamos para el miércoles. ¿Algún pack en mente?', 128),
                    $this->cli('¿El vegetariano tiene gluten?', 126),
                    $this->ver('Sí, usa harina integral orgánica. Si necesitás sin TACC te recomendamos el Pack Real o el Keto', 124),
                    $this->cli('El Real entonces. ¿El 250 Kcal alcanza para dos personas al mediodía?', 122),
                    $this->ver('Para dos personas recomendamos 2 packs de 250 Kcal o 1 pack de 400 Kcal por persona si comen bastante. ¿Cómo prefieren manejarlo?', 120),
                    $this->cli('Dos de 250 Kcal estaría bien. ¿Cuánto sería el total?', 60),
                    $this->ver('2 packs Real 250 Kcal → $140.000/sem (2 × $70.000) con entrega incluida en CABA. ¿En qué barrio están?', 30),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'bsas', 'telefono' => '5491162301003',
                'nombre' => 'Rodrigo Sánchez', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! ¿Tienen algo para bajar de peso sin pasar hambre?', 250),
                    $this->ver('¡Hola Rodrigo! El Pack Keto y el Pack Real son ideales para eso. Son completos, saciantes y sin ultraprocesados', 248),
                    $this->cli('¿El keto es muy estricto? Nunca lo hice', 246),
                    $this->ver('La versión que preparamos es flexible: sin harinas ni azúcar pero con mucha variedad. No es el keto clásico ultra restrictivo', 244),
                    $this->cli('Me convence más así. ¿Puedo empezar cualquier semana?', 242),
                    $this->ver('¡Sí! Solo necesitás avisarnos antes del martes al mediodía para entrar esa semana', 240),
                    $this->cli('¿Me mandás el menú de esta semana?', 180),
                    $this->ver('📋 *Pack Keto – Semana actual*\nLun: Pollo al limón con calabacín\nMar: Lomo al hierro con ensalada verde\nMié: Salmón con palta y pepino\nJue: Hamburguesa casera sin pan con vegetales asados\nVie: Milanesa de pollo a la plancha', 175),
                    $this->cli('Suena muy bien! ¿El precio del 400 Kcal?', 170),
                    $this->ver('$80.000/sem con entrega el miércoles. ¿En qué barrio de CABA estás?', 168),
                    $this->cli('Palermo, Armenio 1500', 165),
                    $this->ver('¡Cubierto! Avisanos si querés arrancar esta semana, Rodrigo', 162),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'bsas', 'telefono' => '5491162301004',
                'nombre' => 'Micaela Torres', 'estado' => 'cerrada',
                'mensajes' => [
                    $this->cli('Hola! ¿Tienen entrega en Mataderos?', 1450),
                    $this->ver('¡Hola Micaela! Sí, Mataderos está dentro de la cobertura en CABA', 1448),
                    $this->cli('¿Y cuánto tarda en llegar desde que hago el pedido?', 1446),
                    $this->ver('El ciclo es semanal: pedido hasta el martes al mediodía, entrega el miércoles por la mañana', 1444),
                    $this->cli('¿Puedo pagar en el momento de la entrega?', 1442),
                    $this->ver('Sí, aceptamos efectivo o MODO al momento de la entrega. También por transferencia antes del martes', 1440),
                    $this->cli('Bueno. Esta semana ya es tarde, ¿me anoto para la próxima?', 1438),
                    $this->ver('¡Por supuesto! Avisanos el lunes o martes que viene. ¿Ya tenés en mente algún pack?', 1436),
                    $this->cli('El keto 400 Kcal. Muchas gracias!', 1434),
                    $this->ver('¡Anotado Micaela! ¡Hasta el lunes! 🌿', 1432),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'valle_nqn', 'telefono' => '5492994301001',
                'nombre' => 'Fernando Gómez', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! ¿Cubrís Cipolletti?', 95),
                    $this->ver('¡Hola Fernando! Cipolletti está incluido en nuestra zona del Valle. ¿Qué pack te interesa?', 93),
                    $this->cli('El anti-age me llamó la atención. ¿Qué incluye exactamente?', 91),
                    $this->ver('Ingredientes antiinflamatorios y antioxidantes: salmón, cúrcuma, brócoli, frutos rojos, semillas de chía y lino. Sin gluten ni azúcar agregada', 89),
                    $this->cli('¿Lo pueden armar sin mariscos? Soy alérgico', 87),
                    $this->ver('¡Sin problema! Reemplazamos el salmón por pollo o carne magra. ¿Alguna otra alergia?', 85),
                    $this->cli('No, solo eso. ¿El precio del 250 Kcal?', 83),
                    $this->ver('Anti-Age 250 Kcal → $65.000/sem con entrega en Cipolletti los miércoles. Anotamos la aclaración sin mariscos', 80),
                    $this->cli('Perfecto! Esta semana no llego pero la próxima les escribo', 76),
                    $this->ver('¡Te esperamos Fernando! ¡Acá estamos! 🌿', 74),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'valle_nqn', 'telefono' => '5492994301002',
                'nombre' => 'Natalia Díaz', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Buenas tardes! Consulta sobre el Pack Real. ¿Es apto celíaco? Mi hija lo necesita', 200),
                    $this->ver('¡Hola Natalia! El Pack Real no lleva harinas. Pero trabajamos en una cocina que también manipula gluten, te recomendaríamos consultarlo si la celiaquía es muy severa', 198),
                    $this->cli('Entiendo. ¿Hay algún pack completamente libre de gluten garantizado?', 196),
                    $this->ver('El Pack Keto es el más seguro: sin harinas de ningún tipo. Para garantía de contaminación cruzada nula, podríamos coordinar una preparación separada', 194),
                    $this->cli('¿Eso tiene costo adicional?', 192),
                    $this->ver('Consultamos con cocina y te avisamos. ¿Me dejás un mail para enviarte la respuesta formal?', 190),
                    $this->cli('natalia.diaz@correo.com. Gracias!', 150),
                    $this->ver('¡Perfecto! Te respondemos en las próximas horas, Natalia', 100),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'valle_nqn', 'telefono' => '5492994301003',
                'nombre' => 'Cristian López', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! Soy vegetariano hace 10 años. ¿El pack vegetariano es variado o siempre lo mismo?', 370),
                    $this->ver('¡Hola Cristian! Muy variado. Rotamos cada semana: guisos, strudels de verdura, pizzas integrales, woks de tofu, tortillas... siempre diferente', 368),
                    $this->cli('¿Incluyen legumbres? Son importantes para la proteína', 366),
                    $this->ver('Sí, lentejas, garbanzos y porotos aparecen seguido. Combinados con queso y huevo en muchas preparaciones', 364),
                    $this->cli('Perfecto. ¿Cuál es el precio del 400 Kcal?', 362),
                    $this->ver('$75.000/sem con entrega los miércoles en Neuquén capital y alrededores', 360),
                    $this->cli('Buenísimo! ¿Me podés mandar el menú de esta semana para mostrarle a mi mujer?', 355),
                    $this->ver('📋 *Menú Vegetariano – Semana actual*\nLun: Tortilla española con ensalada mixta\nMar: Guiso de lentejas con batata\nMié: Strudel de verduras con queso\nJue: Pizza integral muzzarella y rúcula\nVie: Wok de tofu con vegetales salteados', 353),
                    $this->cli('Todo se ve muy bien! Los escribimos el lunes. ¿En qué barrio de Neuquén están?', 345),
                    $this->ver('Nosotros entregamos, ¿en qué barrio de Neuquén están ustedes?', 344),
                    $this->cli('Belgrano, a unas cuadras del río', 342),
                    $this->ver('¡Cubierto! ¡Los esperamos el lunes, Cristian! 🌿', 340),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'cordoba', 'telefono' => '5493514301001',
                'nombre' => 'Rocío Martínez', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Buenas! Quisiera pedir para mí y mi mamá. ¿Pueden ser dos packs diferentes?', 510),
                    $this->ver('¡Hola Rocío! Sí, cada pedido es independiente. ¿Qué pack elegiría cada una?', 508),
                    $this->cli('Yo el anti-age 250 Kcal y ella el real 250 Kcal, tiene problemas de presión', 506),
                    $this->ver('¡Perfecto! Y para la presión, el Pack Real ya es bajo en sodio. ¿Querés que preparemos el de tu mamá sin sal añadida?', 504),
                    $this->cli('Sí, eso sería genial. ¿Hacen entrega en Barrio Jardín?', 502),
                    $this->ver('¡Sí! Barrio Jardín está dentro de la cobertura en Córdoba capital', 500),
                    $this->cli('¿Cuánto sería el total de los dos?', 498),
                    $this->ver('Anti-Age 250 Kcal ($65.000) + Real 250 Kcal ($70.000) = $135.000 con entrega incluida', 496),
                    $this->cli('Buenísimo! Esta semana no llego pero la próxima sí. ¿Les escribo el lunes?', 490),
                    $this->ver('¡Sí! Antes del martes al mediodía y entramos perfecto. ¡Saludos a las dos, Rocío! 😊', 488),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'cordoba', 'telefono' => '5493514301002',
                'nombre' => 'Agustín Herrera', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Hola! ¿Tienen descuento si pago en efectivo?', 720),
                    $this->ver('¡Hola Agustín! Por el momento los precios son iguales en todos los métodos de pago', 718),
                    $this->cli('¿Y si contrato por un mes de una vez?', 716),
                    $this->ver('Trabajamos semana a semana por ahora, aunque es algo que estamos evaluando. ¿Te parece si te avisamos cuando lo implementemos?', 714),
                    $this->cli('Sí, bueno. ¿El Pack Intuitivo qué tan abundante es el 400 Kcal?', 712),
                    $this->ver('El 400 Kcal cubre almuerzo y cena de una persona holgadamente. ¿Tenés alguna restricción alimentaria?', 710),
                    $this->cli('Ninguna, como de todo. ¿A qué hora llega el miércoles?', 360),
                    $this->ver('Entre las 9 y las 13hs. Te avisamos media hora antes de llegar. ¿En qué barrio de Córdoba estás?', 355),
                    $this->cli('Nueva Córdoba, Chacabuco 850', 340),
                    $this->ver('¡Anotado! ¿Confirmamos para esta semana o la siguiente?', 300),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'cordoba', 'telefono' => '5493514301003',
                'nombre' => 'Soledad Ramírez', 'estado' => 'cerrada',
                'mensajes' => [
                    $this->cli('Hola! ¿Qué diferencia hay entre el Pack Real y el Intuitivo?', 2890),
                    $this->ver('¡Hola Soledad! El Real evita ultraprocesados con foco en carnes y vegetales de estación. El Intuitivo es más variado y consciente, sin restricciones de grupos de alimentos. Ambos sin sal añadida', 2888),
                    $this->cli('¿En términos de calorías son parecidos?', 2886),
                    $this->ver('No manejamos conteos calóricos exactos, pero ambos son equilibrados y saciantes. El Real tiene un poco más de proteína animal', 2884),
                    $this->cli('Entiendo. Me quedo con el Intuitivo 250 Kcal. ¿A qué hora llega el miércoles?', 2882),
                    $this->ver('Entre las 9 y las 13hs dependiendo del sector de Córdoba. Te avisamos 30 minutos antes de llegar', 2880),
                    $this->cli('Esta semana no llego. La próxima les escribo seguro. ¡Gracias!', 2878),
                    $this->ver('¡Sin problema Sole! Acá estamos cuando quieras 🌿', 2876),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'mendoza', 'telefono' => '5492614301001',
                'nombre' => 'Pablo Suárez', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! ¿Cubrís Las Heras, Mendoza?', 185),
                    $this->ver('¡Hola Pablo! Sí, Las Heras está incluido en la zona Mendoza', 183),
                    $this->cli('¿El Pack Real qué lleva exactamente?', 181),
                    $this->ver('Carnes frescas (vacuno, pollo, cerdo), vegetales de estación, legumbres y condimentos naturales. Sin conservantes ni aditivos de ningún tipo', 179),
                    $this->cli('¿Hay preparaciones fritas?', 177),
                    $this->ver('Muy poco. Mayormente horneado, a la plancha o al vapor. Algunas cosas se saltean pero sin fritura abundante', 175),
                    $this->cli('Perfecto, es lo que busco. ¿El 400 Kcal para almuerzo y cena?', 173),
                    $this->ver('¡Exacto! El 400 Kcal cubre almuerzo y cena de una persona. ¿Querés ver el menú de esta semana?', 171),
                    $this->cli('Sí manda!', 169),
                    $this->ver('📋 *Pack Real – Semana actual*\nLun: Milanesa de vaca casera con puré de zapallo\nMar: Pollo al romero con vegetales asados\nMié: Tapa de asado con ensalada de estación\nJue: Guiso de lentejas con carne\nVie: Ojo de bife a la plancha con brócoli', 167),
                    $this->cli('Todo suena riquísimo. ¿Confirmo para esta semana?', 163),
                    $this->ver('¡Sí! Avisanos antes del martes al mediodía con el comprobante. CBU: 0070009920000000345678, Alias: VERDEO.MENDOZA', 161),
                ],
            ],

            [
                'canal' => 'whatsapp', 'zona' => 'mendoza', 'telefono' => '5492614301002',
                'nombre' => 'Emilia Vargas', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Buenas noches! Quisiera info del pack keto', 990),
                    $this->ver('¡Hola Emilia! El Pack Keto es sin harinas ni cereales: proteínas, grasas buenas (palta, aceite de oliva, frutos secos) y vegetales de bajo índice glucémico', 988),
                    $this->cli('¿Incluye colaciones o solo almuerzo y cena?', 986),
                    $this->ver('Solo almuerzo y cena de lunes a viernes. El 400 Kcal es bastante abundante si lo dividís bien', 984),
                    $this->cli('¿El precio del 250 Kcal?', 982),
                    $this->ver('$65.000/sem. ¿Estás en Mendoza capital o alrededores?', 980),
                    $this->cli('San Martín, ¿llegan ahí?', 978),
                    $this->ver('San Martín (Mendoza) queda fuera de nuestra zona por ahora. Solo llegamos a capital y Godoy Cruz. Lo lamentamos, Emilia, pero lo tenemos en carpeta para ampliar', 810),
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // MESSENGER — 6 conversaciones
            // ═══════════════════════════════════════════════════════════════

            [
                'canal' => 'messenger', 'zona' => 'bsas', 'canal_id' => '7234501230001',
                'nombre' => 'Ignacio Moreno', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! Vi el perfil de Verdeo en Facebook y me interesó. ¿Hacen entrega en zona sur de GBA?', 90),
                    $this->ver('¡Hola Ignacio! Por ahora cubrimos CABA y GBA norte y oeste. La zona sur está en expansión. ¿En qué ciudad exactamente?', 88),
                    $this->cli('Quilmes, cerca del centro', 86),
                    $this->ver('Quilmes todavía no llegamos, pero lo anotamos para cuando ampliemos. ¿Te avisamos cuando esté disponible?', 84),
                    $this->cli('Sí, eso estaría buenísimo. ¿Y qué packs tienen?', 82),
                    $this->ver('Keto, Anti-Age, Vegetariano, Real e Intuitivo. Todos semanales con entrega los miércoles. ¿Alguno te llama la atención?', 80),
                    $this->cli('El Real parece bueno. ¿Qué incluye?', 78),
                    $this->ver('Carnes frescas, vegetales de estación y preparaciones 100% caseras. Sin ultraprocesados ni conservantes. Cuando llegues a la zona, ese sería ideal para arrancar', 76),
                    $this->cli('Perfecto! Quedamos en contacto entonces', 72),
                    $this->ver('¡Anotado! Te avisamos cuando Quilmes esté disponible. ¡Gracias por el interés, Ignacio!', 70),
                ],
            ],

            [
                'canal' => 'messenger', 'zona' => 'bsas', 'canal_id' => '7234501230002',
                'nombre' => 'Camila Ruiz', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Hola! Vi una publicación del pack vegetariano y me interesa mucho', 440),
                    $this->ver('¡Hola Cami! ¡Qué bueno! El Pack Vegetariano es sin carnes, con queso y huevos, muy variado semana a semana. ¿Tenés alguna restricción?', 438),
                    $this->cli('Soy vegana. ¿Lo pueden hacer 100% vegano?', 436),
                    $this->ver('Por ahora no tenemos un pack 100% vegano certificado. Podemos quitar el queso, pero los huevos aparecen en varias preparaciones', 434),
                    $this->cli('¿Qué tan limitado quedaría el menú sin huevo ni queso?', 432),
                    $this->ver('Bastante limitado, la verdad. Te recomendamos el Pack Intuitivo, que es más flexible y podemos adaptarlo mejor sin proteína animal', 430),
                    $this->cli('¿Cuánto sale el Intuitivo 250 Kcal?', 310),
                    $this->ver('$60.000/sem con entrega en CABA. ¿En qué barrio estás?', 200),
                ],
            ],

            [
                'canal' => 'messenger', 'zona' => 'valle_nqn', 'canal_id' => '7234501230003',
                'nombre' => 'Sebastián Flores', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Buenas! Hago crossfit 5 veces por semana. ¿Tienen algo con más proteína?', 60),
                    $this->ver('¡Hola Sebastián! El Pack Real y el Pack Keto son los más altos en proteína. ¿Cuánto pesás aproximadamente y en qué zona del Valle estás?', 58),
                    $this->cli('90kg, en Neuquén capital barrio Belgrano', 56),
                    $this->ver('Para ese nivel de actividad, el 400 Kcal del Pack Real o Keto sería lo mínimo. Muchos deportistas de ese nivel complementan con una fuente extra de proteína en la cena', 54),
                    $this->cli('¿Pueden ajustar las porciones de proteína?', 52),
                    $this->ver('Podemos anotarlo y que cocina priorice proteína. No garantizamos cantidades exactas pero sí orientamos el armado', 50),
                    $this->cli('Bien. ¿Me mandás el menú del keto esta semana?', 45),
                    $this->ver('¡Claro! Lo mandamos por acá. Igual te recomendamos también consultarlo por WhatsApp para la confirmación del pedido, es más ágil', 43),
                    $this->cli('Dale, ¿cuál es el número?', 40),
                    $this->ver('5492994000000 (Verdeo Valle NQN). ¡Gracias Seba!', 38),
                ],
            ],

            [
                'canal' => 'messenger', 'zona' => 'cordoba', 'canal_id' => '7234501230004',
                'nombre' => 'Marisol García', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola Verdeo! Vi su página y me llama mucho la atención. ¿Tienen prueba de una semana?', 165),
                    $this->ver('¡Hola Marisol! Trabajamos semana a semana así que básicamente siempre es una "prueba". No hay compromiso de continuidad 😊', 163),
                    $this->cli('¡Qué bueno eso! ¿Cuál es el más popular?', 161),
                    $this->ver('El Pack Keto y el Pack Real son los más pedidos. El Keto para quienes buscan bajar peso o definir, el Real para alimentación limpia en general', 159),
                    $this->cli('El Real me interesa. ¿Hacen entrega en Córdoba centro?', 157),
                    $this->ver('¡Sí! Cubrimos toda Córdoba capital y GBA de Córdoba', 155),
                    $this->cli('¿El pago es antes o el día de entrega?', 153),
                    $this->ver('La transferencia la pedimos antes del martes al mediodía. Pero también aceptamos efectivo o MODO el día de la entrega', 151),
                    $this->cli('Perfecto! Arrancaría la semana que viene. ¿Les escribo el lunes por acá mismo?', 145),
                    $this->ver('¡Sí! Por acá o por WhatsApp, como prefieras. ¡Gracias Marisol!', 143),
                ],
            ],

            [
                'canal' => 'messenger', 'zona' => 'cordoba', 'canal_id' => '7234501230005',
                'nombre' => 'Esteban Quiroga', 'estado' => 'cerrada',
                'mensajes' => [
                    $this->cli('Hola! ¿Pueden hacer delivery a una empresa? Somos varios compañeros interesados', 2110),
                    $this->ver('¡Hola Esteban! ¡Qué buena consulta! Para grupos podemos coordinar un envío conjunto con descuento. ¿Cuántos serían aproximadamente?', 2108),
                    $this->cli('Entre 5 y 8 personas, todos en el mismo edificio en Córdoba centro', 2106),
                    $this->ver('¡Ideal para entrega conjunta! Cada uno elegiría su pack independientemente. ¿Te parece si armamos una planilla para que circulen?', 2104),
                    $this->cli('Sí, eso estaría bueno. ¿Tienen descuento por volumen?', 2102),
                    $this->ver('Para más de 5 packs coordinamos un 5% de descuento. ¿Me pasás el mail para enviarte la propuesta formal?', 2100),
                    $this->cli('esteban.quiroga@empresa.com. Muchas gracias!', 2098),
                    $this->ver('¡Perfecto! Te enviamos la propuesta en las próximas horas. ¡Gracias por el interés, Esteban!', 2096),
                ],
            ],

            [
                'canal' => 'messenger', 'zona' => 'mendoza', 'canal_id' => '7234501230006',
                'nombre' => 'Beatriz Molina', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Hola! Tengo diabetes tipo 2. ¿Alguno de sus packs es apto?', 550),
                    $this->ver('¡Hola Beatriz! El Pack Keto y el Pack Real son los más adecuados: sin azúcar agregada, sin harinas refinadas y bajo índice glucémico. Siempre recomendamos consultarlo con tu médico', 548),
                    $this->cli('Mi médico ya lo aprobó. ¿Tienen listado de ingredientes de cada menú?', 546),
                    $this->ver('Sí, mandamos el detalle del menú de la semana antes de cada entrega para que puedas revisar. ¿Tenés algún alimento que debas evitar además del azúcar?', 544),
                    $this->cli('Frutas con mucho azúcar y carbohidratos simples en general', 542),
                    $this->ver('El Pack Keto es el más adecuado entonces. Prácticamente sin carbohidratos. ¿El 250 Kcal o el 400 Kcal?', 540),
                    $this->cli('¿Cuánto sale el 250 Kcal?', 410),
                    $this->ver('$65.000/sem con entrega en Mendoza capital. ¿En qué barrio estás?', 310),
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // INSTAGRAM — 6 conversaciones
            // ═══════════════════════════════════════════════════════════════

            [
                'canal' => 'instagram', 'zona' => 'bsas', 'canal_id' => '17891234560001',
                'nombre' => 'Florencia Acosta', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! Vi su reel de esta semana y el menú vegetariano se veía riquísimo. ¿Cambia cada semana?', 75),
                    $this->ver('¡Hola Flor! Sí, cada semana es diferente. Rotamos las preparaciones para que no te aburras: legumbres, tofu, tortillas, pizzas integrales, strudels...', 73),
                    $this->cli('¿Me mandan el menú de esta semana?', 71),
                    $this->ver('¡Claro! 📋 *Vegetariano – Semana actual*\nLun: Tortilla española\nMar: Guiso de lentejas con batata\nMié: Pizza integral muzzarella\nJue: Wok de tofu salteado\nVie: Strudel de verduras con queso', 69),
                    $this->cli('Todo se ve muy bien! ¿Hacen entrega en San Telmo?', 67),
                    $this->ver('¡Sí! San Telmo es CABA, cubierto 100%', 65),
                    $this->cli('¿Cuánto sale el 250 Kcal?', 63),
                    $this->ver('$60.000/sem con entrega el miércoles. ¿Arrancamos esta semana o la próxima?', 60),
                    $this->cli('La próxima. ¿Les escribo el lunes por acá?', 55),
                    $this->ver('¡Sí! Por acá o por WhatsApp. ¡Te esperamos Flor! 🌿', 53),
                ],
            ],

            [
                'canal' => 'instagram', 'zona' => 'bsas', 'canal_id' => '17891234560002',
                'nombre' => 'Tomás Ibáñez', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Hola! Me apareció en el feed y me interesó el Pack Real. ¿Es para dieta o lo puede comer cualquiera?', 325),
                    $this->ver('¡Hola Tomás! Lo puede comer cualquiera. La idea es comer bien sin complicaciones, sin ultraprocesados. Es buena base para cualquier alimentación', 323),
                    $this->cli('¿Qué tan llenador es el 250 Kcal para el almuerzo?', 321),
                    $this->ver('Bastante llenador. Calculado para un almuerzo completo. Si comés mucho o sos activo, el 400 Kcal da más margen', 319),
                    $this->cli('¿Puedo empezar con el 250 Kcal y pasarme al 400 Kcal si necesito más?', 317),
                    $this->ver('¡Claro! Cada semana es independiente, podés cambiar cuando quieras', 315),
                    $this->cli('¿Hacen entrega en Boedo?', 313),
                    $this->ver('¡Sí! Boedo está cubierto. ¿Lo confirmamos para esta semana?', 210),
                ],
            ],

            [
                'canal' => 'instagram', 'zona' => 'valle_nqn', 'canal_id' => '17891234560003',
                'nombre' => 'Abril Romero', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! ¿Llegan a Neuquén? Vi su cuenta y me flasheó todo', 145),
                    $this->ver('¡Hola Abril! ¡Qué bueno! Sí, cubrimos Neuquén capital y alrededores. ¿Cuál pack te gustó más?', 143),
                    $this->cli('El keto. Hace meses que quiero arrancar y nunca encuentro el momento', 141),
                    $this->ver('¡Este es el momento entonces! El Pack Keto viene todo listo, no necesitás calcular nada. Solo calentar y comer 😊', 139),
                    $this->cli('Eso me convence. ¿Qué día hay que hacer el pedido?', 137),
                    $this->ver('Hasta el martes al mediodía para el miércoles de esa semana. Si querés entrar esta semana ¡todavía estás a tiempo!', 135),
                    $this->cli('¿Cuánto sale el 250 Kcal?', 133),
                    $this->ver('$65.000/sem con entrega en Neuquén. ¿La dirección?', 131),
                    $this->cli('Te mando por WhatsApp mejor. ¿El número está en el bio?', 129),
                    $this->ver('¡Sí! Por WA o por acá como prefieras. ¡Te esperamos Abril! 🌿', 127),
                ],
            ],

            [
                'canal' => 'instagram', 'zona' => 'cordoba', 'canal_id' => '17891234560004',
                'nombre' => 'Matías Cabrera', 'estado' => 'abierta',
                'mensajes' => [
                    $this->cli('Hola! Vi su historia del pack anti-age. ¿Los ingredientes son frescos o congelados?', 100),
                    $this->ver('¡Hola Matías! Todo fresco. Preparamos cada semana con ingredientes de temporada, sin conservantes ni congelados', 98),
                    $this->cli('¿Lo pueden armar sin picante? Es para mi mamá que tiene gastritis', 96),
                    $this->ver('¡Por supuesto! Nuestros packs son suaves de base, sin picante. Y si hay algún ingrediente que necesiten evitar, lo anotamos', 94),
                    $this->cli('¿Tienen entrega en Villa Cabrera, Córdoba?', 92),
                    $this->ver('¡Sí! Villa Cabrera está dentro de la zona en Córdoba', 90),
                    $this->cli('¿Cuánto sale el 250 Kcal anti-age?', 88),
                    $this->ver('$65.000/sem. ¿Querés que le mandemos el menú de esta semana para que vea?', 86),
                    $this->cli('Sí! Manda por acá', 84),
                    $this->ver('📋 *Anti-Age – Semana actual*\nLun: Salmón al horno con batata\nMar: Pollo al cúrcuma con brócoli\nMié: Lentejas con espinaca\nJue: Quínoa con rúcula y nueces\nVie: Merluza al vapor con vegetales', 82),
                    $this->cli('Perfecto! Se lo muestro y el lunes te confirmo', 78),
                    $this->ver('¡Dale! ¡Acá estamos, Matías! 🌿', 76),
                ],
            ],

            [
                'canal' => 'instagram', 'zona' => 'mendoza', 'canal_id' => '17891234560005',
                'nombre' => 'Aldana Vega', 'estado' => 'esperando',
                'mensajes' => [
                    $this->cli('Hola! Empecé a seguirlos y cada semana la comida se ve mejor. ¿El intuitivo tiene muchos carbohidratos?', 265),
                    $this->ver('¡Hola Aldana! El Intuitivo incluye carbohidratos complejos (arroz, batata, legumbres) pero en cantidades equilibradas. No es bajo en hidratos, pero sí de calidad', 263),
                    $this->cli('¿Y tiene mucho sodio? Intento reducirlo', 261),
                    $this->ver('Nuestros packs ya son bajos en sodio. Si querés, preparamos el tuyo directamente sin sal añadida, es una opción que ofrecemos sin costo extra', 259),
                    $this->cli('Qué bueno! ¿Llegan a Guaymallén?', 257),
                    $this->ver('Sí, Guaymallén está dentro de la zona en Mendoza', 255),
                    $this->cli('¿Cuánto sale el intuitivo 400 Kcal?', 185),
                    $this->ver('$75.000/sem. ¿Arrancamos esta semana o la próxima?', 105),
                ],
            ],

            [
                'canal' => 'instagram', 'zona' => 'mendoza', 'canal_id' => '17891234560006',
                'nombre' => 'Joaquín Sosa', 'estado' => 'cerrada',
                'mensajes' => [
                    $this->cli('Hola! ¿El Pack Real incluye salsas o aderezos?', 1570),
                    $this->ver('¡Hola Joaquín! Incluye condimentos naturales en las preparaciones (ajo, hierbas, aceite de oliva) pero no salsas industriales. Todo viene listo para calentar', 1568),
                    $this->cli('¿Puedo pedir sin cerdo? No lo como por costumbre', 1566),
                    $this->ver('¡Sin problema! Lo anotamos y evitamos el cerdo en tu pack. ¿Alguna otra restricción?', 1564),
                    $this->cli('No, el resto bien. ¿Hacen entrega en Ciudad de Mendoza?', 1562),
                    $this->ver('Sí, cubrimos Mendoza capital. ¿Arrancamos el próximo miércoles?', 1560),
                    $this->cli('Lo pienso un poco más y les aviso. Gracias por la info!', 1558),
                    $this->ver('¡Cuando quieras! Acá estamos, Joaquín 🌿', 1556),
                ],
            ],

        ];
    }
}
