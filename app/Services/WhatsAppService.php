<?php

namespace App\Services;

use App\Jobs\PrintOrderReceipt;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\WhatsAppSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const PIX_KEY = '92994565018';

    public function __construct(
        protected LocationResolver $locationResolver,
        protected DeliveryFeeService $deliveryFeeService
    )
    {
    }

    public function markRecentBotOutbound(string $phone, ?string $contactName = null, int $seconds = 30): void
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::firstOrCreate(
            ['customer_id' => $customer->id],
            ['state' => 'start', 'temporary_data' => []]
        );

        $data = $session->temporary_data ?? [];
        $data['ignore_self_messages_until'] = now()->addSeconds($seconds)->toIso8601String();

        $session->update([
            'temporary_data' => $data,
        ]);
    }

    public function shouldIgnoreSelfMessage(string $phone, ?string $contactName = null): bool
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::where('customer_id', $customer->id)->first();

        if (!$session) {
            return false;
        }

        $data = $session->temporary_data ?? [];
        $ignoreUntil = $data['ignore_self_messages_until'] ?? null;

        if (!$ignoreUntil) {
            return false;
        }

        try {
            $until = now()->parse($ignoreUntil);
        } catch (\Throwable $e) {
            $data['ignore_self_messages_until'] = null;
            $session->update(['temporary_data' => $data]);
            return false;
        }

        if ($until->isPast()) {
            $data['ignore_self_messages_until'] = null;
            $session->update(['temporary_data' => $data]);
            return false;
        }

        return true;
    }

    public function dailyMenuFirstMessageReply(string $phone, ?string $contactName = null): ?array
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::firstOrCreate(
            ['customer_id' => $customer->id],
            ['state' => 'daily_menu_only', 'temporary_data' => []]
        );

        $data = $session->temporary_data ?? [];
        $sentAt = $data['daily_menu_sent_at'] ?? null;

        if ($sentAt) {
            try {
                if (now()->parse($sentAt)->addHours(10)->isFuture()) {
                    return null;
                }
            } catch (\Throwable $e) {
                $data['daily_menu_sent_at'] = null;
                $session->update(['temporary_data' => $data]);
            }
        }

        return $this->textReply($this->buildDailyMenuText());
    }

    public function dailyMenuReply(string $phone, ?string $contactName = null): ?array
    {
        return $this->dailyMenuFirstMessageReply($phone, $contactName);
    }

    public function markDailyMenuSent(string $phone, ?string $contactName = null): void
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::firstOrCreate(
            ['customer_id' => $customer->id],
            ['state' => 'daily_menu_only', 'temporary_data' => []]
        );

        $data = $session->temporary_data ?? [];
        $today = now()->toDateString();
        $data['daily_menu_sent_date'] = $today;
        $data['daily_menu_sent_at'] = now()->toIso8601String();

        $session->update([
            'state' => 'daily_menu_only',
            'temporary_data' => $data,
        ]);
    }

    public function suspendBotForContact(string $phone, ?string $contactName = null, int $minutes = 5): array
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::firstOrCreate(
            ['customer_id' => $customer->id],
            ['state' => 'start', 'temporary_data' => []]
        );

        $data = $session->temporary_data ?? [];
        $data['bot_suspended_until'] = now()->addMinutes($minutes)->toIso8601String();

        $session->update([
            'temporary_data' => $data,
        ]);

        return [
            'customer_id' => $customer->id,
            'until' => $data['bot_suspended_until'],
        ];
    }

    public function getSuspensionStatus(string $phone, ?string $contactName = null): ?array
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::where('customer_id', $customer->id)->first();

        if (!$session) {
            return null;
        }

        $data = $session->temporary_data ?? [];
        $suspendedUntil = $data['bot_suspended_until'] ?? null;

        if (!$suspendedUntil) {
            return null;
        }

        try {
            $until = now()->parse($suspendedUntil);
        } catch (\Throwable $e) {
            $data['bot_suspended_until'] = null;
            $session->update(['temporary_data' => $data]);
            return null;
        }

        if ($until->isPast()) {
            $data['bot_suspended_until'] = null;
            $session->update(['temporary_data' => $data]);
            return null;
        }

        return [
            'customer_id' => $customer->id,
            'until' => $until->toIso8601String(),
            'minutes_left' => now()->diffInMinutes($until, false),
        ];
    }

    public function clearSuspension(string $phone, ?string $contactName = null): void
    {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::where('customer_id', $customer->id)->first();

        if (!$session) {
            return;
        }

        $data = $session->temporary_data ?? [];
        $data['bot_suspended_until'] = null;
        $data['ignore_self_messages_until'] = null;

        $session->update([
            'temporary_data' => $data,
        ]);
    }

    public function processMessage(
        string $phone,
        string $message,
        ?string $contactName = null,
        ?string $interactionId = null,
        ?array $location = null
    ): array {
        $customer = $this->findOrCreateCustomer($phone, $contactName);
        $session = WhatsAppSession::firstOrCreate(
            ['customer_id' => $customer->id],
            ['state' => 'start', 'temporary_data' => []]
        );

        $normalizedMessage = $this->normalizeMessage($message);
        $normalizedInteractionId = $this->normalizeInteractionId($interactionId);
        $input = $normalizedInteractionId ?? $normalizedMessage;

        if (in_array($input, ['menu', 'cardapio', 'inicio', 'start'], true)) {
            $session->update([
                'state' => 'start',
                'temporary_data' => [],
            ]);

            return $this->handleStart($session);
        }

        if (in_array($input, ['cancelar', 'cancel', 'sair', 'action:cancel'], true)) {
            $session->delete();

            return $this->textReply('Pedido cancelado. Quando quiser voltar, envie *menu* para comecar novamente.');
        }

        $state = $session->state;
        $data = $session->temporary_data ?? [];

        return match ($state) {
            'start' => $this->handleStart($session),
            'selecting_product' => $this->handleProductSelection($session, $message, $input, $data),
            'selecting_offer_product' => $this->handleOfferSelection($session, $message, $input, $data),
            'selecting_quantity' => $this->handleQuantitySelection($session, $customer, $message, $data),
            'post_cart_action' => $this->handlePostCartAction($session, $customer, $input, $data),
            'selecting_fulfillment' => $this->handleFulfillmentSelection($session, $customer, $input, $data),
            'confirming_address' => $this->handleAddressConfirmation($session, $customer, $message, $input, $data, $location),
            'selecting_payment' => $this->handlePaymentSelection($session, $customer, $input, $data),
            'selecting_change' => $this->handleCashChange($session, $customer, $message, $data),
            'confirmation' => $this->handleFinalConfirmation($session, $customer, $input, $data),
            default => $this->handleStart($session),
        };
    }

    protected function findOrCreateCustomer(string $phone, ?string $contactName = null): Customer
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?: $phone;
        $displayName = $this->formatCustomerName($contactName);

        $customer = Customer::firstOrCreate(
            ['phone' => $normalizedPhone],
            ['name' => $displayName ?? 'Cliente WhatsApp', 'phone' => $normalizedPhone]
        );

        if ($displayName && $customer->name !== $displayName) {
            $customer->update(['name' => $displayName]);
        }

        return $customer;
    }

    protected function handleStart(WhatsAppSession $session): array
    {
        $products = $this->activeProducts();

        if ($products->isEmpty()) {
            return $this->textReply('Nosso cardapio ainda nao esta disponivel. Tente novamente em instantes.');
        }

        $session->update([
            'state' => 'selecting_product',
            'temporary_data' => [],
        ]);

        return $this->productMenuReply($products, 'Ola! Bem-vindo ao Restaurante do Dimas.');
    }

    protected function handleProductSelection(WhatsAppSession $session, string $message, string $input, array $data): array
    {
        $selectedProduct = $this->resolveSelectedProduct($message, $input);

        if (!$selectedProduct) {
            return $this->productMenuReply(
                $this->activeProducts(),
                'Nao encontrei esse item no cardapio. Escolha um prato na lista abaixo.'
            );
        }

        $data['last_product_id'] = $selectedProduct->id;
        $data['last_product_name'] = $selectedProduct->name;
        $data['last_product_source'] = 'menu';

        $session->update([
            'state' => 'selecting_quantity',
            'temporary_data' => $data,
        ]);

        return $this->textReply("Voce escolheu *{$selectedProduct->name}*.\n\nQuantas unidades deseja pedir?");
    }

    protected function handleOfferSelection(WhatsAppSession $session, string $message, string $input, array $data): array
    {
        $selectedProduct = $this->resolveSelectedOfferProduct($message, $input);

        if (!$selectedProduct) {
            return $this->offerMenuReply(
                $this->additionalOfferProducts(),
                'Nao encontrei essa bebida ou complemento. Escolha uma opcao na lista abaixo.'
            );
        }

        $data['last_product_id'] = $selectedProduct->id;
        $data['last_product_name'] = $selectedProduct->name;
        $data['last_product_source'] = 'offer';

        $session->update([
            'state' => 'selecting_quantity',
            'temporary_data' => $data,
        ]);

        return $this->textReply("Voce escolheu *{$selectedProduct->name}*.\n\nQuantas unidades deseja adicionar?");
    }

    protected function handleQuantitySelection(
        WhatsAppSession $session,
        Customer $customer,
        string $message,
        array $data
    ): array
    {
        $quantity = (int) trim($message);

        if ($quantity <= 0) {
            return $this->textReply('Quantidade invalida. Responda com um numero maior que zero.');
        }

        if (!isset($data['last_product_id'])) {
            $session->update([
                'state' => 'start',
                'temporary_data' => [],
            ]);

            return $this->handleStart($session);
        }

        $productId = (int) $data['last_product_id'];
        $product = Product::find($productId);

        if (!$product || !$product->is_active) {
            $session->update([
                'state' => 'start',
                'temporary_data' => [],
            ]);

            return $this->productMenuReply(
                $this->activeProducts(),
                'Esse item nao esta mais disponivel. Vamos voltar ao cardapio.'
            );
        }

        $cart = $data['cart'] ?? [];
        $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
        $data['cart'] = $cart;
        $selectedSource = $data['last_product_source'] ?? 'menu';
        unset($data['last_product_id'], $data['last_product_name']);
        unset($data['last_product_source']);

        if ($this->isReadyForReview($data) && !empty($data['payment_method'])) {
            $nextState = $data['payment_method'] === 'cash' && empty($data['change_for']) ? 'selecting_change' : 'confirmation';

            $session->update([
                'state' => $nextState,
                'temporary_data' => $data,
            ]);

            return $nextState === 'selecting_change'
                ? $this->textReply(
                    'Pagamento em dinheiro selecionado. Troco para quanto? Se nao precisar, responda *sem troco*.'
                )
                : $this->buildOrderReview($customer, $data);
        }

        $session->update([
            'state' => 'post_cart_action',
            'temporary_data' => $data,
        ]);

        return $this->postCartActionReply(
            $data,
            $selectedSource === 'offer'
                ? 'Item adicional adicionado ao pedido.'
                : 'Item adicionado ao pedido.'
        );
    }

    protected function handlePostCartAction(WhatsAppSession $session, Customer $customer, string $input, array $data): array
    {
        if (in_array($input, ['action:add_more', '2', 'mais', 'adicionar', 'adicionar mais itens'], true)) {
            $session->update([
                'state' => 'selecting_product',
                'temporary_data' => $data,
            ]);

            return $this->productMenuReply(
                $this->activeProducts(),
                'Perfeito. Vamos adicionar mais itens ao pedido.'
            );
        }

        if (in_array($input, ['action:view_offers', '3', 'bebidas', 'refrigerante', 'ver bebidas'], true)) {
            $offers = $this->additionalOfferProducts();

            if ($offers->isEmpty()) {
                return $this->postCartActionReply($data, 'Hoje nao temos bebidas ou complementos extras cadastrados.');
            }

            $session->update([
                'state' => 'selecting_offer_product',
                'temporary_data' => $data,
            ]);

            return $this->offerMenuReply($offers, 'Quer aproveitar e incluir uma bebida ou complemento?');
        }

        if (in_array($input, ['action:checkout', '1', 'finalizar', 'fechar', 'finalizar pedido'], true)) {
            $session->update([
                'state' => 'selecting_fulfillment',
                'temporary_data' => $data,
            ]);

            return $this->fulfillmentChoiceReply();
        }

        return $this->postCartActionReply($data, 'Escolha uma das opcoes abaixo para continuar.');
    }

    protected function handleFulfillmentSelection(
        WhatsAppSession $session,
        Customer $customer,
        string $input,
        array $data
    ): array {
        if (in_array($input, ['fulfillment:counter', '1', 'retirada', 'retirar', 'retirada no local'], true)) {
            $data['fulfillment_type'] = 'counter';
            $data['selected_address_id'] = null;
            $data['address_text'] = null;
            $data['awaiting_new_address'] = false;

            $session->update([
                'state' => 'selecting_payment',
                'temporary_data' => $data,
            ]);

            return $this->paymentMethodReply('Pedido para retirada no local selecionado. Agora escolha a forma de pagamento.');
        }

        if (in_array($input, ['fulfillment:delivery', '2', 'entrega', 'delivery'], true)) {
            $data['fulfillment_type'] = 'delivery';
            $data['selected_address_id'] = null;
            $data['address_text'] = null;
            $data['resolved_location'] = null;
            $data['awaiting_new_address'] = true;

            $session->update([
                'state' => 'confirming_address',
                'temporary_data' => $data,
            ]);

            return $this->textReply(
                'Perfeito. Para delivery, envie sua *localizacao pelo WhatsApp* para continuar.'
            );
        }

        return $this->fulfillmentChoiceReply('Escolha se o pedido sera para retirada no local ou delivery.');
    }

    protected function handleAddressConfirmation(
        WhatsAppSession $session,
        Customer $customer,
        string $message,
        string $input,
        array $data,
        ?array $location = null
    ): array {
        if ($location !== null) {
            $resolvedAddress = $this->locationResolver->reverse(
                (float) $location['latitude'],
                (float) $location['longitude']
            );

            if ($resolvedAddress !== null) {
                $data['selected_address_id'] = null;
                $data['address_text'] = $resolvedAddress['formatted'] ?: $this->formatResolvedAddress($resolvedAddress);
                $data['resolved_location'] = $resolvedAddress;
                $data['awaiting_new_address'] = false;
                $this->syncDeliveryData($customer, $data);

                $session->update([
                    'state' => 'selecting_payment',
                    'temporary_data' => $data,
                ]);

                return $this->paymentMethodReply(
                    "Localizacao recebida com sucesso.\n\nEndereco identificado:\n" .
                    $this->formatResolvedAddress($resolvedAddress) .
                    "\n\nAgora escolha a forma de pagamento."
                );
            }

            return $this->textReply(
                'Recebi sua localizacao, mas nao consegui identificar o endereco automaticamente. ' .
                'Tente compartilhar a localizacao novamente para continuar.'
            );
        }

        return $this->textReply(
            'Para finalizar delivery, preciso que voce compartilhe sua *localizacao atual pelo WhatsApp*.'
        );
    }

    protected function handlePaymentSelection(
        WhatsAppSession $session,
        Customer $customer,
        string $input,
        array $data
    ): array {
        $paymentMethod = match ($input) {
            'payment:pix', '1', 'pix' => 'pix',
            'payment:debit', '2', 'debito', 'débito' => 'debit',
            'payment:credit', '3', 'credito', 'crédito' => 'credit',
            'payment:cash', '4', 'dinheiro' => 'cash',
            default => null,
        };

        if (!$paymentMethod) {
            return $this->paymentMethodReply('Escolha uma forma de pagamento na lista abaixo.');
        }

        $data['payment_method'] = $paymentMethod;
        $data['change_for'] = null;

        if ($paymentMethod === 'cash') {
            $session->update([
                'state' => 'selecting_change',
                'temporary_data' => $data,
            ]);

            return $this->textReply(
                'Pagamento em dinheiro selecionado. Troco para quanto? Se nao precisar, responda *sem troco*.'
            );
        }

        $session->update([
            'state' => 'confirmation',
            'temporary_data' => $data,
        ]);

        if ($paymentMethod === 'pix') {
            return $this->reviewActionReply(
                $customer,
                $data,
                'Pagamento via Pix selecionado. Chave Pix: *' . self::PIX_KEY . '*'
            );
        }

        return $this->buildOrderReview($customer, $data);
    }

    protected function handleCashChange(
        WhatsAppSession $session,
        Customer $customer,
        string $message,
        array $data
    ): array {
        $normalizedMessage = $this->normalizeMessage($message);

        if (in_array($normalizedMessage, ['sem troco', 'nao precisa', 'não precisa', 'sem'], true)) {
            $data['change_for'] = null;
        } else {
            $changeFor = $this->parseMoney($message);

            if ($changeFor === null) {
                return $this->textReply('Nao entendi o troco. Responda com um valor como *50* ou *100,00*. Se nao precisar, responda *sem troco*.');
            }

            $deliveryData = $this->resolveDeliveryData($customer, $data);
            $total = $this->cartTotal($data['cart'] ?? [], $deliveryData['fee']);

            if ($changeFor < $total) {
                return $this->textReply(
                    'O valor do troco precisa ser igual ou maior que o total do pedido, que esta em *R$ ' .
                    number_format($total, 2, ',', '.') .
                    '*.'
                );
            }

            $data['change_for'] = $changeFor;
        }

        $session->update([
            'state' => 'confirmation',
            'temporary_data' => $data,
        ]);

        return $this->buildOrderReview($customer, $data);
    }

    protected function handleFinalConfirmation(
        WhatsAppSession $session,
        Customer $customer,
        string $input,
        array $data
    ): array {
        if (in_array($input, ['action:add_more', '2', 'mais', 'adicionar', 'adicionar mais itens'], true)) {
            $session->update([
                'state' => 'selecting_product',
                'temporary_data' => $data,
            ]);

            return $this->productMenuReply(
                $this->activeProducts(),
                'Vamos adicionar mais itens ao pedido.'
            );
        }

        if (in_array($input, ['action:cancel', '3', 'cancelar', 'cancel', 'cancelar pedido'], true)) {
            $session->delete();

            return $this->textReply('Pedido cancelado. Se quiser pedir novamente, envie *menu*.');
        }

        if (!in_array($input, ['action:confirm', '1', 'sim', 'confirmar pedido'], true)) {
            return $this->reviewActionReply($customer, $data, 'Escolha uma das opcoes abaixo para concluir o pedido.');
        }

        DB::beginTransaction();

        try {
            $cart = $data['cart'] ?? [];
            $fulfillmentType = (string) ($data['fulfillment_type'] ?? 'delivery');

            if ($cart === []) {
                throw new \RuntimeException('Carrinho vazio ao finalizar pedido.');
            }

            $address = null;

            if ($fulfillmentType === 'delivery' && !empty($data['selected_address_id'])) {
                $address = $customer->addresses()->find($data['selected_address_id']);
            }

            if ($fulfillmentType === 'delivery' && !$address) {
                $resolvedLocation = $data['resolved_location'] ?? null;
                $addressPayload = is_array($resolvedLocation)
                    ? [
                        'street' => (string) ($resolvedLocation['street'] ?? 'Localizacao compartilhada via WhatsApp'),
                        'number' => (string) ($resolvedLocation['number'] ?? 'S/N'),
                        'neighborhood' => (string) ($resolvedLocation['neighborhood'] ?? 'Nao informado'),
                        'city' => (string) ($resolvedLocation['city'] ?? 'Nao informado'),
                        'state' => (string) ($resolvedLocation['state'] ?? 'AM'),
                        'zip_code' => (string) ($resolvedLocation['zip_code'] ?? '00000-000'),
                        'is_primary' => !$customer->addresses()->exists(),
                        'reference' => $this->locationReference($resolvedLocation),
                    ]
                    : [
                        'street' => (string) ($data['address_text'] ?? 'Endereco informado via WhatsApp'),
                        'number' => 'S/N',
                        'neighborhood' => 'Nao informado',
                        'city' => 'Nao informado',
                        'state' => 'AM',
                        'zip_code' => '00000-000',
                        'is_primary' => !$customer->addresses()->exists(),
                    ];

                $address = $customer->addresses()->create($addressPayload);
            }

            $paymentMethod = (string) ($data['payment_method'] ?? 'pix');
            $changeFor = $data['change_for'] ?? null;
            $deliveryData = $this->resolveDeliveryData($customer, $data);

            if ($fulfillmentType === 'delivery' && $deliveryData['distance_km'] === null) {
                throw new \RuntimeException('Nao foi possivel calcular a distancia de entrega.');
            }

            $total = $this->cartTotal($cart, $deliveryData['fee']);

            if ($paymentMethod === 'cash' && $changeFor !== null && $changeFor < $total) {
                throw new \RuntimeException('Troco informado abaixo do total do pedido.');
            }

            $order = Order::create([
                'customer_id' => $customer->id,
                'address_id' => $address?->id,
                'type' => $fulfillmentType,
                'status' => 'awaiting_acceptance',
                'total_amount' => 0,
                'delivery_fee' => $deliveryData['fee'],
                'delivery_distance_km' => $deliveryData['distance_km'],
                'payment_method' => $paymentMethod,
                'change_for' => $changeFor,
                'observations' => $this->buildOrderObservation(
                    $fulfillmentType,
                    $paymentMethod,
                    $changeFor,
                    $deliveryData
                ),
            ]);

            foreach ($cart as $productId => $quantity) {
                $product = Product::find($productId);

                if (!$product) {
                    continue;
                }

                $subtotal = (float) $product->price * (int) $quantity;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => (int) $quantity,
                    'unit_price' => (float) $product->price,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update(['total_amount' => $total]);

            DB::commit();
            PrintOrderReceipt::dispatch($order->id);

            $session->delete();

            return $this->textReply(
                "Pedido confirmado com sucesso.\n\n" .
                "Numero do pedido: *#{$order->id}*\n" .
                'Total: *R$ ' . number_format($total, 2, ',', '.') . "*\n" .
                'Pagamento: *' . $this->paymentMethodLabel($paymentMethod) . "*\n" .
                ($paymentMethod === 'pix' ? 'Chave Pix: *' . self::PIX_KEY . "*\n " : '') . "\n" .
                'Envie o comprovante para que possamos aceitar seu pedido.'
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Erro ao finalizar pedido do WhatsApp.', [
                'message' => $e->getMessage(),
                'customer_id' => $customer->id,
            ]);

            return $this->textReply('Nao consegui finalizar seu pedido agora. Tente novamente em instantes.');
        }
    }

    protected function buildOrderReview(Customer $customer, array $data): array
    {
        $cart = $data['cart'] ?? [];
        $lines = [];
        $itemsTotal = 0;

        foreach ($cart as $productId => $quantity) {
            $product = Product::find($productId);

            if (!$product) {
                continue;
            }

            $subtotal = (float) $product->price * (int) $quantity;
            $itemsTotal += $subtotal;

            $lines[] = "{$quantity}x {$product->name} - R$ " . number_format($subtotal, 2, ',', '.');
        }

        $fulfillmentType = (string) ($data['fulfillment_type'] ?? 'delivery');
        $deliveryData = $this->resolveDeliveryData($customer, $data);
        $address = !empty($data['selected_address_id']) ? $customer->addresses()->find($data['selected_address_id']) : null;
        $fulfillmentText = $fulfillmentType === 'counter'
            ? 'Retirada no local'
            : ($address ? $this->formatAddress($address) : (string) ($data['address_text'] ?? 'Nao informado'));

        $paymentText = $this->paymentMethodLabel((string) ($data['payment_method'] ?? ''));

        if (($data['payment_method'] ?? null) === 'cash' && !empty($data['change_for'])) {
            $paymentText .= ' (troco para R$ ' . number_format((float) $data['change_for'], 2, ',', '.') . ')';
        }

        if ($fulfillmentType === 'delivery') {
            $lines[] = 'Taxa de entrega - R$ ' . number_format($deliveryData['fee'], 2, ',', '.');
        }

        $total = $itemsTotal + $deliveryData['fee'];

        $description = "Vamos revisar seu pedido:\n\n" .
            implode("\n", $lines) .
            "\n\n" . ($fulfillmentType === 'counter' ? "Retirada:\n{$fulfillmentText}\n" : "Entrega em:\n Localização enviada\n") .
            ($fulfillmentType === 'delivery' && $deliveryData['distance_km'] !== null
                ? 'Distancia: ' . number_format($deliveryData['distance_km'], 2, ',', '.') . " km\n"
                : '') .
            "\nPagamento:\n{$paymentText}\n\n" .
            'Total: *R$ ' . number_format($total, 2, ',', '.') . "*";

        return $this->listReply(
            $description,
            'Concluir pedido',
            [[
                'title' => 'Proximos passos',
                'rows' => [
                    [
                        'rowId' => 'action:confirm',
                        'title' => 'Confirmar pedido',
                        'description' => 'Fechar pedido com os itens atuais',
                    ],
                    [
                        'rowId' => 'action:add_more',
                        'title' => 'Adicionar mais itens',
                        'description' => 'Voltar ao cardapio',
                    ],
                    [
                        'rowId' => 'action:cancel',
                        'title' => 'Cancelar pedido',
                        'description' => 'Encerrar este atendimento',
                    ],
                ],
            ]]
        );
    }

    protected function productMenuReply(Collection $products, string $intro): array
    {
        $rows = [];

        foreach ($products as $index => $product) {
            $rows[] = [
                'rowId' => 'product:' . $product->id,
                'title' => ($index + 1) . '. ' . $product->name,
                'description' => 'R$ ' . number_format((float) $product->price, 2, ',', '.'),
            ];
        }

        return $this->listReply(
            $intro . "\n\nEscolha um prato na lista abaixo.",
            'Ver cardapio',
            [[
                'title' => 'Cardapio de hoje',
                'rows' => $rows,
            ]]
        );
    }

    protected function offerMenuReply(Collection $products, string $intro): array
    {
        $rows = [];

        foreach ($products as $index => $product) {
            $rows[] = [
                'rowId' => 'offer:' . $product->id,
                'title' => ($index + 1) . '. ' . $product->name,
                'description' => 'R$ ' . number_format((float) $product->price, 2, ',', '.'),
            ];
        }

        return $this->listReply(
            $intro . "\n\nEscolha uma bebida ou complemento na lista abaixo.",
            'Ver bebidas',
            [[
                'title' => 'Bebidas e complementos',
                'rows' => $rows,
            ]]
        );
    }

    protected function postCartActionReply(array $data, ?string $intro = null): array
    {
        $itemCount = array_sum($data['cart'] ?? []);
        $rows = [
            [
                'rowId' => 'action:add_more',
                'title' => 'Adicionar mais itens',
                'description' => 'Voltar ao cardapio principal',
            ],
        ];

        if ($this->additionalOfferProducts()->isNotEmpty()) {
            $rows[] = [
                'rowId' => 'action:view_offers',
                'title' => 'Ver bebidas e extras',
                'description' => 'Adicionar refrigerante ou complemento',
            ];
        }

        $rows[] = [
            'rowId' => 'action:checkout',
            'title' => 'Finalizar pedido',
            'description' => 'Informar endereco e pagamento',
        ];

        $rows[] = [
            'rowId' => 'action:cancel',
            'title' => 'Cancelar pedido',
            'description' => 'Encerrar este atendimento',
        ];

        return $this->listReply(
            ($intro ?: 'Item adicionado ao pedido.') .
            "\n\nItens no carrinho: {$itemCount}\nEscolha o que deseja fazer agora.",
            'Escolher proximo passo',
            [[
                'title' => 'Pedido em andamento',
                'rows' => $rows,
            ]]
        );
    }

    protected function fulfillmentChoiceReply(?string $intro = null): array
    {
        return $this->listReply(
            $intro ?: 'Como voce deseja receber seu pedido?',
            'Escolher modalidade',
            [[
                'title' => 'Tipo de atendimento',
                'rows' => [
                    [
                        'rowId' => 'fulfillment:counter',
                        'title' => 'Retirada no local',
                        'description' => 'Voce busca o pedido no restaurante',
                    ],
                    [
                        'rowId' => 'fulfillment:delivery',
                        'title' => 'Delivery',
                        'description' => 'Receber no endereco informado',
                    ],
                ],
            ]]
        );
    }

    protected function addressChoiceReply($primaryAddress, ?string $intro = null): array
    {
        return $this->listReply(
            ($intro ?: 'Endereco principal encontrado:') .
            "\n\n" . $this->formatAddress($primaryAddress),
            'Escolher endereco',
            [[
                'title' => 'Endereco de entrega',
                'rows' => [
                    [
                        'rowId' => 'address:primary',
                        'title' => 'Usar endereco principal',
                        'description' => 'Continuar com este endereco',
                    ],
                    [
                        'rowId' => 'address:new',
                        'title' => 'Informar outro endereco',
                        'description' => 'Digitar um endereco diferente',
                    ],
                ],
            ]]
        );
    }

    protected function paymentMethodReply(?string $intro = null): array
    {
        return $this->listReply(
            ($intro ?: 'Agora escolha a forma de pagamento.'),
            'Escolher pagamento',
            [[
                'title' => 'Formas de pagamento',
                'rows' => [
                    [
                        'rowId' => 'payment:pix',
                        'title' => 'Pix',
                        'description' => 'Pagamento via chave ou QR Code',
                    ],
                    [
                        'rowId' => 'payment:debit',
                        'title' => 'Debito',
                        'description' => 'Cartao de debito na entrega',
                    ],
                    [
                        'rowId' => 'payment:credit',
                        'title' => 'Credito',
                        'description' => 'Cartao de credito na entrega',
                    ],
                    [
                        'rowId' => 'payment:cash',
                        'title' => 'Dinheiro',
                        'description' => 'Informar troco se necessario',
                    ],
                ],
            ]]
        );
    }

    protected function reviewActionReply(Customer $customer, array $data, string $intro): array
    {
        $review = $this->buildOrderReview($customer, $data);
        $review['description'] = $intro . "\n\n" . ($review['description'] ?? '');

        return $review;
    }

    protected function resolveSelectedProduct(string $message, string $input): ?Product
    {
        if (str_starts_with($input, 'product:')) {
            return Product::where('is_active', true)
                ->where('is_additional_offer', false)
                ->find((int) str_replace('product:', '', $input));
        }

        $selectedIndex = (int) trim($message);

        if ($selectedIndex <= 0) {
            return null;
        }

        return $this->activeProducts()->get($selectedIndex - 1);
    }

    protected function resolveSelectedOfferProduct(string $message, string $input): ?Product
    {
        if (str_starts_with($input, 'offer:')) {
            return Product::where('is_active', true)
                ->where('is_additional_offer', true)
                ->find((int) str_replace('offer:', '', $input));
        }

        $selectedIndex = (int) trim($message);

        if ($selectedIndex <= 0) {
            return null;
        }

        return $this->additionalOfferProducts()->get($selectedIndex - 1);
    }

    protected function cartTotal(array $cart, float $deliveryFee = 0): float
    {
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = Product::find($productId);

            if (!$product) {
                continue;
            }

            $total += (float) $product->price * (int) $quantity;
        }

        return $total + $deliveryFee;
    }

    protected function buildOrderObservation(
        string $fulfillmentType,
        string $paymentMethod,
        ?float $changeFor,
        array $deliveryData = ['fee' => 0.0, 'distance_km' => null]
    ): string
    {
        $lines = [
            'Pedido via autoatendimento WhatsApp',
            'Canal: ' . $this->fulfillmentTypeLabel($fulfillmentType),
        ];

        if ($fulfillmentType === 'delivery') {
            $lines[] = 'Taxa de entrega: R$ ' . number_format((float) ($deliveryData['fee'] ?? 0), 2, ',', '.');

            if (($deliveryData['distance_km'] ?? null) !== null) {
                $lines[] = 'Distancia: ' . number_format((float) $deliveryData['distance_km'], 2, ',', '.') . ' km';
            }
        }

        if ($paymentMethod !== '') {
            $lines[] = 'Pagamento: ' . $this->paymentMethodLabel($paymentMethod);
        }

        if ($paymentMethod === 'cash' && $changeFor !== null) {
            $lines[] = 'Troco para: R$ ' . number_format($changeFor, 2, ',', '.');
        }

        $lines[] = 'Aguardando aceite da equipe';

        return implode("\n", $lines);
    }

    protected function fulfillmentTypeLabel(string $fulfillmentType): string
    {
        return match ($fulfillmentType) {
            'delivery' => 'Delivery',
            'table' => 'Mesa',
            default => 'Balcao',
        };
    }

    protected function paymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'pix' => 'Pix',
            'debit' => 'Debito',
            'credit' => 'Credito',
            'cash' => 'Dinheiro',
            default => 'Nao informado',
        };
    }

    protected function hasAddress(array $data): bool
    {
        return !empty($data['selected_address_id']) || !empty($data['address_text']);
    }

    protected function isReadyForReview(array $data): bool
    {
        $fulfillmentType = (string) ($data['fulfillment_type'] ?? '');

        if ($fulfillmentType === 'counter') {
            return true;
        }

        return $fulfillmentType === 'delivery' && $this->hasAddress($data);
    }

    protected function parseMoney(string $value): ?float
    {
        $normalized = preg_replace('/[^\d,\.]/', '', trim($value));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    protected function activeProducts(): Collection
    {
        return Product::where('is_active', true)
            ->where('is_additional_offer', false)
            ->orderBy('position')
            ->get();
    }

    protected function buildDailyMenuText(): string
    {
        $mainProducts = Product::query()
            ->where('is_active', true)
            ->where('is_additional_offer', false)
            ->where('is_daily_special', true)
            ->orderBy('position')
            ->get();

        if ($mainProducts->isEmpty()) {
            $mainProducts = $this->activeProducts();
        }

        if ($mainProducts->isEmpty()) {
            return "Ola! Nosso cardapio do dia ainda nao esta disponivel. Tente novamente em instantes.";
        }

        $lines = [
            'Ola! Segue o cardapio do dia do Restaurante do Dimas:',
            '',
        ];

        foreach ($mainProducts as $product) {
            $line = '* ' . $product->name . ' - R$ ' . number_format((float) $product->price, 2, ',', '.');

            if ($product->description) {
                $line .= "\n  " . trim($product->description);
            }

            $lines[] = $line;
        }

        $extraProducts = $this->additionalOfferProducts();

        if ($extraProducts->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Bebidas e complementos:';

            foreach ($extraProducts as $product) {
                $lines[] = '* ' . $product->name . ' - R$ ' . number_format((float) $product->price, 2, ',', '.');
            }
        }

        return implode("\n", $lines);
    }

    protected function additionalOfferProducts(): Collection
    {
        return Product::where('is_active', true)
            ->where('is_additional_offer', true)
            ->orderBy('position')
            ->get();
    }

    protected function formatAddress($address): string
    {
        return trim(implode(', ', array_filter([
            $address->street,
            $address->number,
            $address->complement,
            $address->neighborhood,
            $address->city,
            $address->state,
        ])));
    }

    protected function formatResolvedAddress(array $resolvedAddress): string
    {
        return trim(implode(', ', array_filter([
            trim(($resolvedAddress['street'] ?? '') . ', ' . ($resolvedAddress['number'] ?? 'S/N')),
            $resolvedAddress['neighborhood'] ?? null,
            $resolvedAddress['city'] ?? null,
            $resolvedAddress['state'] ?? null,
        ])));
    }

    protected function locationReference(array $resolvedAddress): ?string
    {
        $latitude = $resolvedAddress['latitude'] ?? null;
        $longitude = $resolvedAddress['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return 'Localizacao compartilhada via WhatsApp (' . $latitude . ', ' . $longitude . ')';
    }

    protected function syncDeliveryData(Customer $customer, array &$data): void
    {
        $deliveryData = $this->resolveDeliveryData($customer, $data);
        $data['delivery_fee'] = $deliveryData['fee'];
        $data['delivery_distance_km'] = $deliveryData['distance_km'];
    }

    protected function resolveDeliveryData(Customer $customer, array $data): array
    {
        $fulfillmentType = (string) ($data['fulfillment_type'] ?? '');

        if ($fulfillmentType !== 'delivery') {
            return ['fee' => 0.0, 'distance_km' => null];
        }

        if (!empty($data['selected_address_id'])) {
            $address = $customer->addresses()->find($data['selected_address_id']);

            if ($address) {
                return $this->deliveryFeeService->calculateForAddress($address)
                    ?? ['fee' => 0.0, 'distance_km' => null];
            }
        }

        if (!empty($data['resolved_location']) && is_array($data['resolved_location'])) {
            return $this->deliveryFeeService->calculateForResolvedLocation($data['resolved_location'])
                ?? ['fee' => 0.0, 'distance_km' => null];
        }

        $addressText = trim((string) ($data['address_text'] ?? ''));

        if ($addressText !== '') {
            return $this->deliveryFeeService->calculateForAddressText($addressText)
                ?? ['fee' => 0.0, 'distance_km' => null];
        }

        return ['fee' => 0.0, 'distance_km' => null];
    }

    protected function normalizeMessage(string $message): string
    {
        $message = trim(mb_strtolower($message));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $message);

        return trim($normalized !== false ? $normalized : $message);
    }

    protected function normalizeInteractionId(?string $interactionId): ?string
    {
        $interactionId = trim((string) $interactionId);

        if ($interactionId === '') {
            return null;
        }

        return $this->normalizeMessage($interactionId);
    }

    protected function formatCustomerName(?string $contactName): ?string
    {
        $contactName = trim((string) $contactName);

        if ($contactName === '') {
            return null;
        }

        $storeName = trim((string) config('app.name', ''));

        if ($storeName !== '' && mb_strtolower($contactName) === mb_strtolower($storeName)) {
            return null;
        }

        if (preg_match('/\s+\(whatsapp\)$/i', $contactName)) {
            return $contactName;
        }

        return $contactName . ' (WhatsApp)';
    }

    protected function textReply(string $message): array
    {
        return [
            'type' => 'text',
            'message' => $message,
        ];
    }

    protected function listReply(string $description, string $buttonText, array $sections): array
    {
        return [
            'type' => 'list',
            'description' => $description,
            'buttonText' => $buttonText,
            'sections' => $sections,
        ];
    }
}
