<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Producto;
use App\Models\Plato;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Plato::truncate();
        Producto::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $menus = [
            [
                'nombre'      => 'Menú Nuevo Keto',
                'tipo'        => 'keto',
                'descripcion' => 'Sin harinas ni cereales',
                'orden'       => 1,
                'platos'      => [
                    'Canelones de pollo a la crema y brócoli | filetto',
                    'Lomo braseado al romero y ajo | salteado de vegetales',
                    'Bondiola al puerro y vino blanco | puré de calabaza asada',
                    'Tagine de pollo con almendras',
                    'Chili de ternera con cheddar | base de zucchinis',
                ],
            ],
            [
                'nombre'      => 'Menú Anti-Age',
                'tipo'        => 'anti_age',
                'descripcion' => 'Ingredientes anti inflamatorios y anti oxidantes',
                'orden'       => 2,
                'platos'      => [
                    'Raviolones integrales capresse y muzzarella | filetto',
                    'Lomo braseado al romero y ajo | tagine de cebada',
                    'Wraps integrales de muzza, berenjenas y atún | revuelto de zapallitos',
                    'Cazuela de lentejas con portobellos y almendras',
                    'Bondiola al puerro y vino blanco | fritatta de brócoli',
                ],
            ],
            [
                'nombre'      => 'Menú Vegetariano',
                'tipo'        => 'vegetariano',
                'descripcion' => 'Sin carnes · Con harina integral orgánica y queso',
                'orden'       => 3,
                'platos'      => [
                    'Raviolones integrales capresse y muzzarella | filetto',
                    'Cazuela de lentejas con portobellos y almendras',
                    'Canastitas de ricotta, zapallitos y nueces | puré de calabaza asada',
                    'Tagine de verduras y cebada | hummus de espinaca',
                    'Wraps integrales de muzza y babaganush | revuelto de zapallitos',
                ],
            ],
            [
                'nombre'      => 'Menú Real',
                'tipo'        => 'real',
                'descripcion' => 'Sin ultraprocesados ni procesados',
                'orden'       => 4,
                'platos'      => [
                    'Cazuela de lentejas con portobello y ternera',
                    'Canelones de pollo, nueces y brócoli | filetto',
                    'Lomo braseado al romero y ajo | salteado de vegetales',
                    'Tagine de verduras y cebada | hummus de espinaca',
                    'Bondiola al puerro y vino blanco | puré de calabaza asada',
                ],
            ],
            [
                'nombre'      => 'Menú Intuitivo',
                'tipo'        => 'intuitivo',
                'descripcion' => 'Una alimentación consciente, variada y saludable, sin restricciones. Sin sal. Elija la combinación de nuestras comidas que ud. más prefiera. Recomendamos porción mediana para su alimentación intuitiva. Mínimo 5 comidas.',
                'orden'       => 5,
                'platos'      => [],
            ],
        ];

        foreach ($menus as $data) {
            $platos = $data['platos'];
            unset($data['platos']);

            $menu = Producto::create(array_merge($data, ['activo' => true]));

            foreach ($platos as $i => $nombre) {
                Plato::create([
                    'producto_id' => $menu->id,
                    'nombre'      => $nombre,
                    'orden'       => $i,
                    'activo'      => true,
                ]);
            }
        }
    }
}
