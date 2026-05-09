<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class OrderReceiptPrinter
{
    public function print(Order $order): void
    {
        if (!$this->isEnabled()) {
            Log::info('Impressao automatica desativada. Pedido nao enviado para a impressora.', [
                'order_id' => $order->id,
            ]);

            return;
        }

        $connector = $this->makeConnector();
        $printer = new Printer($connector);

        try {
            if ($order->isDelivery()) {
                $this->renderReceipt($printer, $order, 'VIA DO RESTAURANTE');
                $this->renderReceipt($printer, $order, 'VIA DO ENTREGADOR');
            } else {
                $this->renderReceipt($printer, $order);
            }
        } finally {
            $printer->close();
        }
    }

    protected function renderReceipt(Printer $printer, Order $order, ?string $copyLabel = null): void
    {
        $isDelivery = $order->isDelivery();
        $isTable = $order->isTable();

        $printer->initialize();
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($this->setting('print_store_name', config('app.name', 'Restaurante do Dimas')) . "\n");
        $printer->setEmphasis(false);
        $printer->text('Pedido #' . $order->id . "\n");
        $printer->text(optional($order->created_at)->format('d/m/Y H:i') . "\n");

        if ($copyLabel) {
            $printer->setEmphasis(true);
            $printer->text($copyLabel . "\n");
            $printer->setEmphasis(false);
        }

        $printer->feed();

        $printer->selectPrintMode(
            Printer::MODE_DOUBLE_WIDTH |
            Printer::MODE_DOUBLE_HEIGHT |
            Printer::MODE_EMPHASIZED
        );
        $printer->text($order->type_label . "\n");
        $printer->selectPrintMode();

        $printer->setEmphasis(true);
        $printer->text(match (true) {
            $copyLabel === 'VIA DO ENTREGADOR' => "ENTREGAR ESTA VIA AO ENTREGADOR\n",
            $isDelivery => "ATENCAO: ENVIAR PARA ENTREGA\n",
            $isTable => "ATENCAO: PEDIDO PARA MESA\n",
            default => "ATENCAO: PEDIDO DE BALCAO\n",
        });
        $printer->setEmphasis(false);
        $printer->text(str_repeat('-', 32) . "\n");

        $this->printLine($printer, 'Status', $this->statusLabel((string) $order->status));
        $this->printLine($printer, 'Cliente', (string) optional($order->customer)->name);
        $this->printLine($printer, 'Telefone', (string) optional($order->customer)->phone);
        $this->printLine($printer, 'Pagamento', $this->paymentLabel((string) $order->payment_method));

        if ($order->payment_method === 'cash') {
            $troco = $order->change_for
                ? 'R$ ' . number_format((float) $order->change_for, 2, ',', '.')
                : 'Sem troco';

            $this->printLine($printer, 'Troco', $troco);
        }

        $printer->text(str_repeat('-', 32) . "\n");
        $printer->setEmphasis(true);
        $printer->text("ITENS\n");
        $printer->setEmphasis(false);

        foreach ($order->items as $item) {
            $name = (string) optional($item->product)->name;
            $subtotal = 'R$ ' . number_format((float) $item->subtotal, 2, ',', '.');

            $printer->setEmphasis(true);
            $printer->text($item->quantity . 'x ' . $name . "\n");
            $printer->setEmphasis(false);
            $this->printLine(
                $printer,
                'Unit.',
                'R$ ' . number_format((float) $item->unit_price, 2, ',', '.')
            );
            $this->printLine($printer, 'Subtotal', $subtotal);
            $printer->text(str_repeat('.', 32) . "\n");
        }

        $printer->setEmphasis(true);
        $printer->text(match (true) {
            $isDelivery => "ENTREGA\n",
            $isTable => "MESA\n",
            default => "RETIRADA\n",
        });
        $printer->setEmphasis(false);

        if ($isDelivery) {
            foreach ($this->deliveryLines($order) as $line) {
                $printer->text($line . "\n");
            }

            $locationUrl = $order->deliveryLocationUrl();

            if ($locationUrl !== null) {
                $printer->feed();
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("LOCALIZACAO\n");
                $printer->qrCode($locationUrl, Printer::QR_ECLEVEL_M, 5, Printer::QR_MODEL_2);
                $printer->text("Escaneie para abrir\n");
                $printer->setJustification(Printer::JUSTIFY_LEFT);
            }
        } elseif ($isTable) {
            $printer->text("Consumir no local\n");
            $printer->text("Encaminhar para a mesa\n");
        } else {
            $printer->text("Pedido para retirada no balcao\n");
            $printer->text("Nao enviar para motoboy\n");
        }

        $observationLines = $this->observationLines((string) $order->observations);

        if ($observationLines !== []) {
            $printer->text(str_repeat('-', 32) . "\n");
            $printer->setEmphasis(true);
            $printer->text("OBSERVACOES\n");
            $printer->setEmphasis(false);

            foreach ($observationLines as $line) {
                $printer->text($line . "\n");
            }
        }

        $printer->text(str_repeat('-', 32) . "\n");
        $itemsTotal = (float) $order->items->sum('subtotal');

        $this->printLine(
            $printer,
            'Itens',
            'R$ ' . number_format($itemsTotal, 2, ',', '.')
        );

        if ($isDelivery) {
            $this->printLine(
                $printer,
                'Entrega',
                'R$ ' . number_format((float) $order->delivery_fee, 2, ',', '.')
            );

            if ($order->delivery_distance_km !== null) {
                $this->printLine(
                    $printer,
                    'Distancia',
                    number_format((float) $order->delivery_distance_km, 2, ',', '.') . ' km'
                );
            }
        }

        $printer->setEmphasis(true);
        $this->printLine(
            $printer,
            'TOTAL',
            'R$ ' . number_format((float) $order->total_amount, 2, ',', '.')
        );
        $printer->setEmphasis(false);
        $printer->feed(3);
        $printer->cut();
    }

    protected function makeConnector(): FilePrintConnector|NetworkPrintConnector|WindowsPrintConnector
    {
        $connection = $this->setting('print_connection', 'network');

        return match ($connection) {
            'windows' => new WindowsPrintConnector(
                $this->requiredSetting('print_windows_connector')
            ),
            'file' => new FilePrintConnector(
                $this->requiredSetting('print_file_connector')
            ),
            default => new NetworkPrintConnector(
                $this->requiredSetting('print_host'),
                (int) $this->setting('print_port', '9100')
            ),
        };
    }

    protected function printLine(Printer $printer, string $label, string $value): void
    {
        $label = $this->limit($label, 10);
        $value = trim($value);

        if ($value === '') {
            return;
        }

        $left = str_pad($label . ':', 11);
        $rightWidth = 21;

        foreach ($this->wrapText($value, $rightWidth) as $index => $line) {
            $printer->text(($index === 0 ? $left : str_repeat(' ', 11)) . $line . "\n");
        }
    }

    protected function deliveryLines(Order $order): array
    {
        if (!$order->address) {
            return ['Endereco nao informado'];
        }

        return array_values(array_filter([
            trim(($order->address->street ?? '') . ', ' . ($order->address->number ?? 'S/N')),
            $order->address->complement,
            $order->address->reference ? 'Ref: ' . $order->address->reference : null,
            trim(($order->address->neighborhood ?? '') . ' - ' . ($order->address->city ?? '')),
            $order->address->state ? 'UF: ' . $order->address->state : null,
        ]));
    }

    protected function observationLines(string $observations): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $observations) ?: [];

        return array_values(array_filter(array_map('trim', $lines)));
    }

    protected function wrapText(string $text, int $width): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?: '';

        if ($text === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', wordwrap($text, $width, "\n", true)) ?: [$text];
    }

    protected function limit(string $text, int $width): string
    {
        return mb_strlen($text) > $width
            ? mb_substr($text, 0, $width)
            : $text;
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'awaiting_acceptance' => 'Aguardando aceite',
            'pending' => 'Pendente',
            'preparing' => 'Em preparo',
            'shipped' => 'Saiu para entrega',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    protected function paymentLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'pix' => 'Pix',
            'debit' => 'Debito',
            'credit' => 'Credito',
            'cash' => 'Dinheiro',
            default => 'Nao informado',
        };
    }

    protected function isEnabled(): bool
    {
        return filter_var($this->setting('print_enabled', 'false'), FILTER_VALIDATE_BOOL);
    }

    protected function requiredSetting(string $key): string
    {
        $value = trim((string) $this->setting($key));

        if ($value === '') {
            throw new \RuntimeException('Configuracao obrigatoria de impressao ausente: ' . $key);
        }

        return $value;
    }

    protected function setting(string $key, ?string $default = null): ?string
    {
        $envMap = config('printing.settings_map', []);
        $envDefault = array_key_exists($key, $envMap) ? env($envMap[$key], $default) : $default;

        return Setting::get($key, $envDefault);
    }
}
