<?php

namespace App\Services;

use App\Models\Address;

class DeliveryFeeService
{
    private const STORE_LATITUDE = -3.0252861966722593;
    private const STORE_LONGITUDE = -59.96732103032802;

    public function __construct(protected LocationResolver $locationResolver)
    {
    }

    public function calculateForAddress(Address $address, bool $useCachedFee = true): ?array
    {
        // Se o endereço já tem uma taxa armazenada e queremos usar a cache, retornar ela
        if ($useCachedFee && $address->last_delivery_fee !== null) {
            $distanceKm = $this->getCachedDistanceForAddress($address);

            // Atualizar o timestamp para indicar que a taxa foi usada recentemente
            $address->update(['last_delivery_fee_updated_at' => now()]);

            return [
                'distance_km' => $distanceKm,
                'fee' => (float) $address->last_delivery_fee,
                'is_cached' => true,
            ];
        }

        $latitude = $address->latitude !== null ? (float) $address->latitude : null;
        $longitude = $address->longitude !== null ? (float) $address->longitude : null;

        if ($latitude === null || $longitude === null) {
            $resolved = $this->locationResolver->geocode($this->formatAddress($address));

            if ($resolved === null) {
                return null;
            }

            $latitude = (float) $resolved['latitude'];
            $longitude = (float) $resolved['longitude'];

            $address->forceFill([
                'latitude' => $latitude,
                'longitude' => $longitude,
            ])->save();
        }

        $result = $this->calculateForCoordinates($latitude, $longitude);

        // Armazenar a taxa calculada no endereço para uso futuro
        $this->updateAddressDeliveryFee($address, $result['fee'], $result['distance_km']);

        return array_merge($result, ['is_cached' => false]);
    }

    public function calculateForResolvedLocation(array $resolvedLocation): ?array
    {
        $latitude = $resolvedLocation['latitude'] ?? null;
        $longitude = $resolvedLocation['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return $this->calculateForCoordinates((float) $latitude, (float) $longitude);
    }

    public function calculateForAddressText(string $address): ?array
    {
        $resolved = $this->locationResolver->geocode($address);

        if ($resolved === null) {
            return null;
        }

        return $this->calculateForCoordinates(
            (float) $resolved['latitude'],
            (float) $resolved['longitude']
        );
    }

    public function calculateForCoordinates(float $latitude, float $longitude): array
    {
        $distanceKm = $this->haversineDistance(
            self::STORE_LATITUDE,
            self::STORE_LONGITUDE,
            $latitude,
            $longitude
        );

        return [
            'distance_km' => round($distanceKm, 2),
            'fee' => $this->feeForDistance($distanceKm),
        ];
    }

    protected function feeForDistance(float $distanceKm): float
    {
        if ($distanceKm <= 0.7) {
            return 3.0;
        }

        if ($distanceKm <= 2) {
            return 5.0;
        }

        return 7.0;
    }

    protected function haversineDistance(
        float $originLatitude,
        float $originLongitude,
        float $destinationLatitude,
        float $destinationLongitude
    ): float {
        $earthRadiusKm = 6371;

        $latitudeDelta = deg2rad($destinationLatitude - $originLatitude);
        $longitudeDelta = deg2rad($destinationLongitude - $originLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude))
            * cos(deg2rad($destinationLatitude))
            * sin($longitudeDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    protected function formatAddress(Address $address): string
    {
        return implode(', ', array_filter([
            $address->street,
            $address->number,
            $address->neighborhood,
            $address->city,
            $address->state,
            $address->zip_code,
        ]));
    }

    /**
     * Atualiza a taxa de entrega armazenada no endereço
     */
    public function updateAddressDeliveryFee(Address $address, float $fee, ?float $distanceKm = null): void
    {
        $address->update([
            'last_delivery_fee' => $fee,
            'last_delivery_fee_updated_at' => now(),
        ]);

        // Se tivermos uma distância, podemos armazená-la também (em cache ou campo adicional)
        // Por enquanto apenas atualizamos a taxa
    }

    /**
     * Obtém a distância em cache para um endereço (se disponível)
     */
    protected function getCachedDistanceForAddress(Address $address): ?float
    {
        // Por enquanto retornamos null, mas podemos implementar cache de distância também
        // ou calcular a distância novamente se necessário
        return null;
    }

    /**
     * Força o recálculo da taxa ignorando o cache
     */
    public function recalculateForAddress(Address $address): ?array
    {
        return $this->calculateForAddress($address, false);
    }
}
