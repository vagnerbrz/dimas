@php
    $isDelivery = $order->isDelivery();
    $isTable = $order->isTable();

    $statusLabels = [
        'awaiting_acceptance' => 'Aguardando aceite',
        'pending' => 'Pendente',
        'preparing' => 'Em preparo',
        'shipped' => 'Saiu para entrega',
        'delivered' => 'Entregue',
        'cancelled' => 'Cancelado',
    ];

    $paymentLabels = [
        'pix' => 'Pix',
        'debit' => 'Debito',
        'credit' => 'Credito',
        'cash' => 'Dinheiro',
    ];

    $orderTypeLabel = strtoupper($order->type_label);
    $orderTypeSupport = match (true) {
        $isDelivery => 'ENTREGA NO ENDERECO DO CLIENTE',
        $isTable => 'CONSUMO NO LOCAL',
        default => 'RETIRADA NO BALCAO',
    };

    $addressLines = [];

    if ($order->address) {
        $addressLines = array_filter([
            trim(($order->address->street ?? '') . ', ' . ($order->address->number ?? 'S/N')),
            $order->address->complement,
            $order->address->reference ? 'Referencia: ' . $order->address->reference : null,
            trim(($order->address->neighborhood ?? '') . ' - ' . ($order->address->city ?? '')),
            $order->address->state ? 'UF: ' . $order->address->state : null,
        ]);
    }

    $observationLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $order->observations)));
    $deliveryLocationUrl = $order->deliveryLocationUrl();
    $deliveryQrUrl = $deliveryLocationUrl
        ? 'https://quickchart.io/qr?size=180&text=' . urlencode($deliveryLocationUrl)
        : null;
    $printCopies = $isDelivery
        ? ['VIA DO RESTAURANTE', 'VIA DO ENTREGADOR']
        : [null];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressao Pedido #{{ $order->id }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #e5e7eb;
            font-family: "Courier New", Courier, monospace;
            color: #111827;
        }

        .sheet {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            background: #ffffff;
            padding: 10px 10px 18px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.12);
        }

        .center {
            text-align: center;
        }

        .divider {
            border-top: 1px dashed #111827;
            margin: 10px 0;
        }

        .type-box {
            border: 2px solid #111827;
            padding: 8px 6px;
            text-align: center;
            margin: 10px 0;
        }

        .type-label {
            font-size: 28px;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .type-support {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
        }

        .meta-row,
        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
        }

        .meta-row strong,
        .total-row strong {
            font-weight: 700;
        }

        .section-title {
            margin: 10px 0 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .item {
            padding: 6px 0;
            border-top: 1px dotted #9ca3af;
        }

        .item:first-of-type {
            border-top: 0;
        }

        .item-name {
            font-size: 13px;
            font-weight: 700;
        }

        .item-meta,
        .address-line,
        .observation-line,
        .note {
            font-size: 12px;
            line-height: 1.4;
        }

        .qr-block {
            margin-top: 10px;
            text-align: center;
        }

        .qr-block img {
            width: 140px;
            height: 140px;
            display: block;
            margin: 6px auto 0;
        }

        .highlight {
            background: #111827;
            color: #ffffff;
            padding: 4px 6px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            margin: 8px 0;
        }

        .copy-banner {
            border: 2px solid #111827;
            padding: 5px 6px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin: 8px 0;
        }

        .total-row {
            font-size: 16px;
            margin-top: 8px;
        }

        .print-actions {
            width: 70mm;
            max-width: 100%;
            margin: 12px auto 0;
            display: flex;
            justify-content: center;
        }

        .print-actions button {
            border: 0;
            background: #111827;
            color: #ffffff;
            font: inherit;
            padding: 10px 14px;
            cursor: pointer;
        }

        .page-break {
            page-break-after: always;
            break-after: page;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .sheet {
                width: 70mm;
                box-shadow: none;
                margin: 0;
            }

            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    @foreach($printCopies as $copyLabel)
    <div class="sheet {{ !$loop->last ? 'page-break' : '' }}">
        <div class="center">
            <div style="font-size: 18px; font-weight: 700;">RESTAURANTE DO DIMAS</div>
            <div class="note">Pedido #{{ $order->id }}</div>
            <div class="note">{{ $order->created_at?->format('d/m/Y H:i') }}</div>
        </div>

        @if($copyLabel)
            <div class="copy-banner">{{ $copyLabel }}</div>
        @endif

        <div class="type-box">
            <div class="type-label">{{ $orderTypeLabel }}</div>
            <div class="type-support">{{ $orderTypeSupport }}</div>
        </div>

        <div class="highlight">
            {{ $copyLabel === 'VIA DO ENTREGADOR' ? 'ENTREGAR ESTA VIA AO ENTREGADOR' : ($isDelivery ? 'ATENCAO: ENVIAR PARA ENTREGA' : ($isTable ? 'ATENCAO: PEDIDO PARA MESA' : 'ATENCAO: PEDIDO DE BALCAO')) }}
        </div>

        <div class="meta-row">
            <span><strong>Status:</strong></span>
            <span>{{ $statusLabels[$order->status] ?? $order->status }}</span>
        </div>
        <div class="meta-row">
            <span><strong>Cliente:</strong></span>
            <span>{{ $order->customer->name }}</span>
        </div>
        <div class="meta-row">
            <span><strong>Telefone:</strong></span>
            <span>{{ $order->customer->phone }}</span>
        </div>
        <div class="meta-row">
            <span><strong>Pagamento:</strong></span>
            <span>{{ $paymentLabels[$order->payment_method] ?? 'Nao informado' }}</span>
        </div>

        @if($order->payment_method === 'cash')
            <div class="meta-row">
                <span><strong>Troco:</strong></span>
                <span>{{ $order->change_for ? 'R$ ' . number_format($order->change_for, 2, ',', '.') : 'Sem troco' }}</span>
            </div>
        @endif

        <div class="divider"></div>

        <div class="section-title">Itens do pedido</div>
        @foreach($order->items as $item)
            <div class="item">
                <div class="item-name">{{ $item->quantity }}x {{ $item->product->name }}</div>
                <div class="item-meta">Unit.: R$ {{ number_format($item->unit_price, 2, ',', '.') }}</div>
                <div class="item-meta">Subtotal: R$ {{ number_format($item->subtotal, 2, ',', '.') }}</div>
            </div>
        @endforeach

        <div class="divider"></div>

        @if($isDelivery)
            <div class="section-title">Entrega</div>
            @forelse($addressLines as $line)
                <div class="address-line">{{ $line }}</div>
            @empty
                <div class="address-line">Endereco nao informado.</div>
            @endforelse
            @if($deliveryQrUrl)
                <div class="qr-block">
                    <div class="note"><strong>Localizacao da entrega</strong></div>
                    <div class="note">Escaneie para abrir no mapa</div>
                    <img src="{{ $deliveryQrUrl }}" alt="QR Code da localizacao de entrega">
                </div>
            @endif
        @elseif($isTable)
            <div class="section-title">Mesa</div>
            <div class="address-line">Pedido para consumo no local.</div>
            <div class="address-line">Encaminhar para a mesa.</div>
        @else
            <div class="section-title">Retirada</div>
            <div class="address-line">Pedido para retirada no balcao.</div>
            <div class="address-line">Nao enviar para motoboy.</div>
        @endif

        @if(count($observationLines) > 0)
            <div class="divider"></div>
            <div class="section-title">Observacoes</div>
            @foreach($observationLines as $line)
                <div class="observation-line">{{ $line }}</div>
            @endforeach
        @endif

        <div class="divider"></div>

        <div class="meta-row">
            <span><strong>Itens:</strong></span>
            <span>R$ {{ number_format((float) $order->items->sum('subtotal'), 2, ',', '.') }}</span>
        </div>
        @if($isDelivery)
            <div class="meta-row">
                <span><strong>Entrega:</strong></span>
                <span>R$ {{ number_format((float) $order->delivery_fee, 2, ',', '.') }}</span>
            </div>
            @if($order->delivery_distance_km !== null)
                <div class="meta-row">
                    <span><strong>Distancia:</strong></span>
                    <span>{{ number_format((float) $order->delivery_distance_km, 2, ',', '.') }} km</span>
                </div>
            @endif
        @endif

        <div class="total-row">
            <strong>Total</strong>
            <strong>R$ {{ number_format($order->total_amount, 2, ',', '.') }}</strong>
        </div>
    </div>
    @endforeach

    <div class="print-actions">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
