<?php

namespace App\Services;

use App\Jobs\PrintOrderReceipt;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class N8nRestaurantAssistantService
{
    public function __construct(
        protected DeliveryFeeService $deliveryFeeService,
        protected LocationResolver $locationResolver,
        protected WhatsAppService $whatsAppService,
        protected HumanConversationService $humanConversationService,
    ) {
    }

    public function getMenu(): array
    {
        $items = Product::query()
            ->where('is_active', true)
            ->orderBy('is_additional_offer')
            ->orderBy('position')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float) $product->price,
                'formatted_price' => 'R$ ' . number_format((float) $product->price, 2, ',', '.'),
                'is_additional_offer' => (bool) $product->is_additional_offer,
                'is_daily_special' => (bool) $product->is_daily_special,
            ])
            ->values()
            ->all();

        return [
            'restaurant' => config('app.name'),
            'currency' => 'BRL',
            'items' => $items,
            'main_items' => array_values(array_filter($items, fn (array $item) => !$item['is_additional_offer'])),
            'extra_items' => array_values(array_filter($items, fn (array $item) => $item['is_additional_offer'])),
        ];
    }

    public function createOrder(array $payload): array
    {
        $customer = $this->findOrCreateCustomer(
            (string) data_get($payload, 'customer.phone'),
            data_get($payload, 'customer.name')
        );

        $items = $this->buildItems((array) ($payload['items'] ?? []));
        $fulfillmentType = (string) ($payload['fulfillment_type'] ?? Order::TYPE_COUNTER);
        $paymentMethod = (string) ($payload['payment_method'] ?? '');
        $assistantNotes = trim((string) ($payload['assistant_notes'] ?? ''));

        if ($fulfillmentType === '') {
            $fulfillmentType = Order::TYPE_COUNTER;
        }

        $address = null;
        $deliveryData = ['fee' => 0.0, 'distance_km' => null];

        if ($fulfillmentType === Order::TYPE_DELIVERY) {
            [$address, $deliveryData] = $this->resolveDeliveryAddress($customer, (array) ($payload['address'] ?? []));
        }

        $itemsSubtotal = array_sum(array_column($items, 'subtotal'));
        $total = round($itemsSubtotal + (float) $deliveryData['fee'], 2);
        $changeFor = $this->normalizeMoney($payload['change_for'] ?? null);

        if ($paymentMethod === 'cash' && $changeFor !== null && $changeFor < $total) {
            throw new \RuntimeException('O valor do troco precisa ser igual ou maior que o total do pedido.');
        }

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_id' => $customer->id,
                'address_id' => $address?->id,
                'type' => $fulfillmentType,
                'status' => 'awaiting_acceptance',
                'total_amount' => $total,
                'delivery_fee' => (float) $deliveryData['fee'],
                'delivery_distance_km' => $deliveryData['distance_km'],
                'payment_method' => $paymentMethod,
                'change_for' => $paymentMethod === 'cash' ? $changeFor : null,
                'observations' => $this->buildObservations($fulfillmentType, $paymentMethod, $changeFor, $deliveryData, $assistantNotes),
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            DB::commit();

            PrintOrderReceipt::dispatch($order->id);
            $order->load(['customer', 'address', 'items.product']);

            return [
                'order_id' => $order->id,
                'status' => $order->status,
                'type' => $order->type,
                'payment_method' => $order->payment_method,
                'total_amount' => (float) $order->total_amount,
                'delivery_fee' => (float) $order->delivery_fee,
                'delivery_distance_km' => $order->delivery_distance_km !== null ? (float) $order->delivery_distance_km : null,
                'delivery_fee_was_cached' => $deliveryData['is_cached'] ?? false,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                ],
                'address' => $address ? [
                    'id' => $address->id,
                    'street' => $address->street,
                    'number' => $address->number,
                    'complement' => $address->complement,
                    'neighborhood' => $address->neighborhood,
                    'city' => $address->city,
                    'state' => $address->state,
                    'zip_code' => $address->zip_code,
                    'reference' => $address->reference,
                    'latitude' => $address->latitude !== null ? (float) $address->latitude : null,
                    'longitude' => $address->longitude !== null ? (float) $address->longitude : null,
                    'last_delivery_fee' => $address->last_delivery_fee !== null ? (float) $address->last_delivery_fee : null,
                    'last_delivery_fee_updated_at' => $address->last_delivery_fee_updated_at?->toIso8601String(),
                ] : null,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ])->values()->all(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function suspendConversation(string $phone, ?string $name = null, int $minutes = 15): array
    {
        $conversation = $this->humanConversationService->openEscalatedConversation(
            $phone,
            $name,
            ['source' => 'n8n'],
            $minutes,
        );

        $suspension = $this->whatsAppService->getSuspensionStatus($phone, $name)
            ?? $this->whatsAppService->suspendBotForContact($phone, $name, $minutes);

        return array_merge($suspension, [
            'conversation_id' => $conversation->id,
            'conversation_status' => $conversation->status,
        ]);
    }

    protected function buildItems(array $items): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $product = Product::query()
                ->where('is_active', true)
                ->find($productId);

            if (!$product) {
                continue;
            }

            $resolved[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => (float) $product->price,
                'subtotal' => round((float) $product->price * $quantity, 2),
            ];
        }

        if ($resolved === []) {
            throw new \RuntimeException('Nenhum item valido foi informado para o pedido.');
        }

        return $resolved;
    }

    protected function resolveDeliveryAddress(Customer $customer, array $addressData): array
    {
        $latitude = $this->floatOrNull($addressData['latitude'] ?? null);
        $longitude = $this->floatOrNull($addressData['longitude'] ?? null);
        $addressFields = $addressData;

        if ($latitude !== null && $longitude !== null && trim((string) ($addressFields['street'] ?? '')) === '') {
            $resolved = $this->locationResolver->reverse($latitude, $longitude);

            if ($resolved !== null) {
                $addressFields = array_merge($resolved, $addressFields);
            }
        }

        $street = trim((string) ($addressFields['street'] ?? ''));
        $number = trim((string) ($addressFields['number'] ?? 'S/N'));
        $neighborhood = trim((string) ($addressFields['neighborhood'] ?? ''));
        $city = trim((string) ($addressFields['city'] ?? 'Manaus'));
        $state = strtoupper(trim((string) ($addressFields['state'] ?? 'AM')));
        $zipCode = trim((string) ($addressFields['zip_code'] ?? '00000-000'));

        if ($street === '' || $neighborhood === '') {
            throw new \RuntimeException('Para delivery, informe um endereco valido ou as coordenadas da localizacao.');
        }

        $reference = trim((string) ($addressFields['reference'] ?? ''));

        if ($latitude !== null && $longitude !== null) {
            $coordinateNote = '(' . $latitude . ', ' . $longitude . ')';
            $reference = $reference !== '' ? $reference . ' ' . $coordinateNote : 'Localizacao compartilhada ' . $coordinateNote;
        }

        $address = Address::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'street' => $street,
                'number' => $number,
                'zip_code' => $zipCode,
            ],
            [
                'complement' => trim((string) ($addressFields['complement'] ?? '')),
                'neighborhood' => $neighborhood,
                'city' => $city,
                'state' => $state !== '' ? $state : 'AM',
                'reference' => $reference,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'is_primary' => !$customer->addresses()->exists(),
            ]
        );

        if (!$address->wasRecentlyCreated) {
            $address->fill([
                'complement' => trim((string) ($addressFields['complement'] ?? $address->complement)),
                'neighborhood' => $neighborhood ?: $address->neighborhood,
                'city' => $city ?: $address->city,
                'state' => $state !== '' ? $state : $address->state,
                'reference' => $reference !== '' ? $reference : $address->reference,
                'latitude' => $latitude ?? $address->latitude,
                'longitude' => $longitude ?? $address->longitude,
            ])->save();
        }

        $deliveryData = $this->deliveryFeeService->calculateForAddress($address);

        if ($deliveryData === null) {
            throw new \RuntimeException('Nao foi possivel calcular a taxa de entrega para esse endereco.');
        }

        // A taxa já foi automaticamente armazenada no endereço pelo DeliveryFeeService
        // quando calculateForAddress foi chamado (se não estava em cache)

        return [$address, $deliveryData];
    }

    protected function findOrCreateCustomer(string $phone, ?string $contactName = null): Customer
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?: $phone;

        if ($normalizedPhone === '') {
            throw new \RuntimeException('Telefone do cliente nao informado.');
        }

        $displayName = trim((string) $contactName);

        $customer = Customer::firstOrCreate(
            ['phone' => $normalizedPhone],
            ['name' => $displayName !== '' ? $displayName : 'Cliente WhatsApp', 'phone' => $normalizedPhone]
        );

        if ($displayName !== '' && $customer->name !== $displayName) {
            $customer->update(['name' => $displayName]);
        }

        return $customer;
    }

    protected function buildObservations(
        string $fulfillmentType,
        string $paymentMethod,
        ?float $changeFor,
        array $deliveryData,
        string $assistantNotes
    ): string {
        $lines = [
            'Pedido via assistente IA n8n',
            'Canal: ' . match ($fulfillmentType) {
                Order::TYPE_DELIVERY => 'Delivery',
                Order::TYPE_TABLE => 'Mesa',
                default => 'Balcao',
            },
            'Pagamento: ' . match ($paymentMethod) {
                'pix' => 'Pix',
                'debit' => 'Debito',
                'credit' => 'Credito',
                'cash' => 'Dinheiro',
                default => 'Nao informado',
            },
        ];

        if ($fulfillmentType === Order::TYPE_DELIVERY) {
            $lines[] = 'Taxa de entrega: R$ ' . number_format((float) ($deliveryData['fee'] ?? 0), 2, ',', '.');

            if (($deliveryData['distance_km'] ?? null) !== null) {
                $lines[] = 'Distancia: ' . number_format((float) $deliveryData['distance_km'], 2, ',', '.') . ' km';
            }
        }

        if ($paymentMethod === 'cash' && $changeFor !== null) {
            $lines[] = 'Troco para: R$ ' . number_format($changeFor, 2, ',', '.');
        }

        if ($assistantNotes !== '') {
            $lines[] = 'Observacoes da IA: ' . $assistantNotes;
        }

        $lines[] = 'Aguardando aceite da equipe';

        return implode("\n", $lines);
    }

    protected function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^\d,\.]/', '', trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    protected function floatOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
