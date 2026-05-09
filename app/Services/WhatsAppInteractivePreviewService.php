<?php

namespace App\Services;

use App\Models\Product;

class WhatsAppInteractivePreviewService extends WppConnectService
{
    public function sendButtonsPreview(string $recipient): array
    {
        $recipient = $this->normalizeRecipient($recipient);
        $products = $this->previewProducts();
        $buttons = [];

        foreach ($products as $index => $product) {
            $buttons[] = [
                'id' => 'produto:' . $product->id,
                'text' => ($index + 1) . '. ' . $product->name,
            ];
        }

        return $this->sendInteractiveRequest('send-buttons', [
            'phone' => [$recipient],
            'isGroup' => str_ends_with($recipient, '@g.us'),
            'isLid' => str_ends_with($recipient, '@lid'),
            'message' => "Ola! Esta e uma previa alternativa de autoatendimento com buttons.\n\nEscolha uma opcao abaixo para continuar.",
            'options' => [
                'title' => 'Cardapio Restaurante do Dimas',
                'footer' => 'Exemplo isolado para avaliacao',
                'buttons' => $buttons,
            ],
        ]);
    }

    public function sendListPreview(string $recipient): array
    {
        $recipient = $this->normalizeRecipient($recipient);
        $products = $this->previewProducts();
        $rows = [];

        foreach ($products as $index => $product) {
            $rows[] = [
                'rowId' => 'produto:' . $product->id,
                'title' => ($index + 1) . '. ' . $product->name,
                'description' => 'R$ ' . number_format((float) $product->price, 2, ',', '.'),
            ];
        }

        return $this->sendInteractiveRequest('send-list-message', [
            'phone' => [$recipient],
            'isGroup' => str_ends_with($recipient, '@g.us'),
            'isLid' => str_ends_with($recipient, '@lid'),
            'description' => "Esta e uma previa alternativa de autoatendimento com lista interativa.\n\nToque no botao abaixo para abrir as opcoes.",
            'buttonText' => 'Ver cardapio',
            'sections' => [
                [
                    'title' => 'Pratos disponiveis',
                    'rows' => $rows,
                ],
            ],
        ]);
    }

    public function sendBooleanButtonsPreview(string $recipient): array
    {
        $recipient = $this->normalizeRecipient($recipient);

        return $this->sendMessageWithOptions(
            $recipient,
            "Esta e uma previa experimental de confirmacao rapida.\n\nDeseja continuar com o pedido?",
            [
                'title' => 'Confirmacao',
                'footer' => 'Teste isolado de botoes',
                'buttons' => [
                    [
                        'id' => 'confirm:yes',
                        'text' => 'Sim',
                    ],
                    [
                        'id' => 'confirm:no',
                        'text' => 'Nao',
                    ],
                ],
            ]
        );
    }

    protected function sendInteractiveRequest(string $action, array $payload): array
    {
        $status = $this->getSessionStatus();

        if (!$this->isConnectedStatus($status['status'])) {
            throw new \RuntimeException(
                'Sessao do WhatsApp indisponivel para envio interativo. Status atual: ' .
                ($status['status'] ?: 'desconhecido')
            );
        }

        $response = $this->authorizedClient()->post($this->sessionApiUrl($action), $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Falha ao enviar mensagem interativa pelo WPPConnect. HTTP ' .
                $response->status() .
                ': ' .
                $response->body()
            );
        }

        return $response->json() ?? [];
    }

    protected function previewProducts()
    {
        $products = Product::where('is_active', true)
            ->orderBy('position')
            ->limit(3)
            ->get();

        if ($products->isEmpty()) {
            throw new \RuntimeException('Nao ha produtos ativos para montar a previa interativa.');
        }

        return $products;
    }
}
