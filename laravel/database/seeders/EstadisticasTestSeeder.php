<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Producto;
use Carbon\Carbon;

class EstadisticasTestSeeder extends Seeder
{
    // Precio base por producto (id => [250kcal, 400kcal])
    private array $precios = [
        1 => [65_000, 82_000],   // Keto
        2 => [68_000, 85_000],   // Anti-Age
        3 => [60_000, 75_000],   // Vegetariano
        4 => [62_000, 78_000],   // Real
        5 => [58_000, 72_000],   // Intuitivo
    ];

    // Peso acumulativo por zona (bsas es dominante)
    private array $zonas = [
        'bsas'      => 50,
        'valle_nqn' => 25,
        'cordoba'   => 15,
        'mendoza'   => 10,
    ];

    public function run(): void
    {
        $clientes   = User::where('role', 'cliente')->get();
        $productos  = Producto::all();

        if ($clientes->isEmpty() || $productos->isEmpty()) {
            $this->command->error('Necesitás clientes y productos en la BD antes de correr este seeder.');
            return;
        }

        // Conteo de órdenes previas por user para generar el número correlativo
        $ordenCountPorUser = $clientes->mapWithKeys(
            fn($u) => [$u->id => DB::table('ordenes')->where('user_id', $u->id)->count()]
        )->toArray();

        $inserted = 0;

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $fecha = Carbon::now()->subDays($daysAgo)->startOfDay();

            // Lunes a viernes: 4–6 órdenes · Sáb/Dom: 1–3
            $cantidad = $fecha->isWeekend() ? rand(1, 3) : rand(4, 6);

            // Semanas más recientes tienden a tener más volumen (tendencia creciente)
            if ($daysAgo <= 7) {
                $cantidad = (int) ceil($cantidad * 1.3);
            }

            for ($i = 0; $i < $cantidad; $i++) {
                $cliente = $clientes->random();
                $zona    = $this->randomZona();
                $prod    = $productos->random();
                $tamano  = rand(0, 2) ? '250kcal' : '400kcal';   // 66% 250kcal, 33% 400kcal
                $idx     = $tamano === '250kcal' ? 0 : 1;
                $precio  = ($this->precios[$prod->id][$idx] ?? 65_000) + rand(-2000, 2000);
                $estado  = $this->randomEstado($daysAgo);
                $hora    = $fecha->copy()->setTime(rand(9, 17), rand(0, 59), rand(0, 59));

                $ordenCountPorUser[$cliente->id]++;
                $numero = sprintf('%06d', $cliente->numero_cliente ?? 0)
                    . '-'
                    . sprintf('%06d', $ordenCountPorUser[$cliente->id]);

                $ordenId = DB::table('ordenes')->insertGetId([
                    'numero'      => $numero,
                    'user_id'     => $cliente->id,
                    'estado'      => $estado,
                    'zona'        => $zona,
                    'ingresa_por' => rand(0, 9) < 8 ? 'whatsapp' : 'mail',
                    'total'       => $precio,
                    'notas'       => null,
                    'created_at'  => $hora,
                    'updated_at'  => $hora,
                ]);

                DB::table('orden_items')->insert([
                    'orden_id'        => $ordenId,
                    'producto_id'     => $prod->id,
                    'tamano'          => $tamano,
                    'forma_pago'      => $this->randomFormaPago(),
                    'cantidad'        => 1,
                    'precio_unitario' => $precio,
                    'subtotal'        => $precio,
                    'created_at'      => $hora,
                    'updated_at'      => $hora,
                ]);

                // ~20% de órdenes tienen un segundo ítem (producto distinto)
                if (rand(0, 4) === 0) {
                    $prod2   = $productos->where('id', '!=', $prod->id)->random();
                    $tam2    = rand(0, 1) ? '250kcal' : '400kcal';
                    $precio2 = ($this->precios[$prod2->id][$tam2 === '250kcal' ? 0 : 1] ?? 60_000) + rand(-1000, 1000);

                    DB::table('orden_items')->insert([
                        'orden_id'        => $ordenId,
                        'producto_id'     => $prod2->id,
                        'tamano'          => $tam2,
                        'forma_pago'      => $this->randomFormaPago(),
                        'cantidad'        => 1,
                        'precio_unitario' => $precio2,
                        'subtotal'        => $precio2,
                        'created_at'      => $hora,
                        'updated_at'      => $hora,
                    ]);

                    // Recalcular total de la orden
                    DB::table('ordenes')->where('id', $ordenId)->update([
                        'total' => $precio + $precio2,
                    ]);
                }

                $inserted++;
            }
        }

        $this->command->info("✓ {$inserted} órdenes de prueba creadas (últimos 30 días).");
    }

    private function randomZona(): string
    {
        $n   = rand(1, 100);
        $acc = 0;
        foreach ($this->zonas as $slug => $peso) {
            $acc += $peso;
            if ($n <= $acc) return $slug;
        }
        return 'bsas';
    }

    private function randomEstado(int $daysAgo): string
    {
        // Órdenes de los últimos 2 días: mezcla activa
        if ($daysAgo <= 2) {
            return $this->pick(['pendiente' => 35, 'aprobada' => 35, 'lista_para_entrega' => 20, 'entregada' => 10]);
        }
        // 3–7 días: en tránsito hacia entregada
        if ($daysAgo <= 7) {
            return $this->pick(['aprobada' => 15, 'lista_para_entrega' => 15, 'entregada' => 60, 'cancelada' => 10]);
        }
        // Más de 7 días: casi todo entregado
        return $this->pick(['entregada' => 82, 'cancelada' => 8, 'pendiente' => 5, 'aprobada' => 5]);
    }

    private function randomFormaPago(): string
    {
        return $this->pick(['transferencia' => 55, 'en_destino' => 35, 'no_definido' => 10]);
    }

    private function pick(array $pesos): string
    {
        $n   = rand(1, array_sum($pesos));
        $acc = 0;
        foreach ($pesos as $valor => $peso) {
            $acc += $peso;
            if ($n <= $acc) return $valor;
        }
        return array_key_first($pesos);
    }
}
