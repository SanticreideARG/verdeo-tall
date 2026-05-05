<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversacion>
 */
class ConversacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $zonas = ['bsas', 'valle_nqn', 'cordoba', 'mendoza'];
        $mensajes = [
            'Buenas, quería consultar por el pedido de la semana',
            'Cuándo llega el próximo camión?',
            'Necesito modificar mi pedido',
            'Todo bien, gracias!',
            'Están disponibles los tomates cherry?',
            'El pedido llegó incompleto',
            'Quiero dar de baja el servicio',
            'Consulta sobre precio mayorista',
        ];

        return [
            'zona'             => $this->faker->randomElement($zonas),
            'telefono'         => '549' . $this->faker->numerify('##########'),
            'nombre'           => $this->faker->firstName() . ' ' . $this->faker->lastName(),
            'estado'           => $this->faker->randomElement(['abierta', 'cerrada', 'esperando']),
            'ultimo_mensaje'   => $this->faker->randomElement($mensajes),
            'ultimo_mensaje_at'=> $this->faker->dateTimeBetween('-7 days', 'now'),
            'created_at'       => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
