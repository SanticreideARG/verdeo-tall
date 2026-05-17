<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function geocodificar(string $direccion): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Verdeo/1.0 santi.creide@gmail.com',
            ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                'q'      => $direccion . ', Argentina',
                'format' => 'json',
                'limit'  => 1,
            ]);

            $data = $response->json();

            if (!empty($data[0])) {
                return [
                    'lat' => (float) $data[0]['lat'],
                    'lng' => (float) $data[0]['lon'],
                ];
            }
        } catch (\Throwable) {}

        return null;
    }
}
