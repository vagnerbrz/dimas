<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocationResolver
{
    public function geocode(string $address): ?array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => config('services.nominatim.user_agent', 'dimas-whatsapp-bot/1.0'),
                ])
                ->get(rtrim(config('services.nominatim.url', 'https://nominatim.openstreetmap.org'), '/') . '/search', [
                    'format' => 'jsonv2',
                    'q' => $address,
                    'limit' => 1,
                    'addressdetails' => 1,
                ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar geocoding.', [
                    'status' => $response->status(),
                    'address' => $address,
                ]);

                return null;
            }

            $payload = $response->json();

            if (!is_array($payload) || !isset($payload[0]) || !is_array($payload[0])) {
                return null;
            }

            $result = $payload[0];
            $latitude = isset($result['lat']) ? (float) $result['lat'] : null;
            $longitude = isset($result['lon']) ? (float) $result['lon'] : null;

            if ($latitude === null || $longitude === null) {
                return null;
            }

            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'formatted' => trim((string) ($result['display_name'] ?? $address)),
            ];
        } catch (\Throwable $e) {
            Log::warning('Erro ao geocodificar endereco.', [
                'message' => $e->getMessage(),
                'address' => $address,
            ]);

            return null;
        }
    }

    public function reverse(float $latitude, float $longitude): ?array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => config('services.nominatim.user_agent', 'dimas-whatsapp-bot/1.0'),
                ])
                ->get(rtrim(config('services.nominatim.url', 'https://nominatim.openstreetmap.org'), '/') . '/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'addressdetails' => 1,
                ]);

            if (!$response->successful()) {
                Log::warning('Falha ao consultar reverse geocoding.', [
                    'status' => $response->status(),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                return null;
            }

            $payload = $response->json();

            if (!is_array($payload)) {
                return null;
            }

            $address = $payload['address'] ?? [];

            $street = trim((string) ($address['road'] ?? $address['pedestrian'] ?? $address['footway'] ?? $address['street'] ?? ''));
            $number = trim((string) ($address['house_number'] ?? 'S/N'));
            $neighborhood = trim((string) ($address['suburb'] ?? $address['neighbourhood'] ?? $address['quarter'] ?? 'Nao informado'));
            $city = trim((string) ($address['city'] ?? $address['town'] ?? $address['village'] ?? $address['municipality'] ?? 'Nao informado'));
            $state = trim((string) ($address['state_code'] ?? $address['state'] ?? ''));
            $zipCode = trim((string) ($address['postcode'] ?? '00000-000'));
            $formatted = trim((string) ($payload['display_name'] ?? ''));

            return [
                'street' => $street !== '' ? $street : 'Localizacao compartilhada via WhatsApp',
                'number' => $number !== '' ? $number : 'S/N',
                'neighborhood' => $neighborhood !== '' ? $neighborhood : 'Nao informado',
                'city' => $city !== '' ? $city : 'Nao informado',
                'state' => $state !== '' ? $state : 'AM',
                'zip_code' => $zipCode !== '' ? $zipCode : '00000-000',
                'formatted' => $formatted,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        } catch (\Throwable $e) {
            Log::warning('Erro ao resolver localizacao do WhatsApp.', [
                'message' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return null;
        }
    }
}
