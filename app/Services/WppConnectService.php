<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WppConnectService
{
    public function sendMessage(string $recipient, string $message): array
    {
        $recipient = $this->normalizeRecipient($recipient);
        $message = trim($message);

        if ($recipient === '' || $message === '') {
            throw new \RuntimeException('Telefone ou mensagem invalidos para envio.');
        }

        $sessionStatus = $this->getSessionStatus();

        if (!$this->isConnectedStatus($sessionStatus['status'])) {
            throw new \RuntimeException(
                'Sessao do WhatsApp indisponivel para envio. Status atual: ' .
                ($sessionStatus['status'] ?: 'desconhecido')
            );
        }

        $response = $this->authorizedClient()->post($this->sessionApiUrl('send-message'), [
            'phone' => [$recipient],
            'isGroup' => str_ends_with($recipient, '@g.us'),
            'isLid' => str_ends_with($recipient, '@lid'),
            'message' => $message,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Falha ao enviar mensagem pelo WPPConnect. HTTP ' .
                $response->status() .
                ': ' .
                $response->body()
            );
        }

        return $response->json() ?? [];
    }

    public function sendMessageWithOptions(string $recipient, string $message, array $options = []): array
    {
        $recipient = $this->normalizeRecipient($recipient);
        $message = trim($message);

        if ($recipient === '' || $message === '') {
            throw new \RuntimeException('Telefone ou mensagem invalidos para envio.');
        }

        return $this->sendInteractiveRequest('send-message', [
            'phone' => [$recipient],
            'isGroup' => str_ends_with($recipient, '@g.us'),
            'isLid' => str_ends_with($recipient, '@lid'),
            'message' => $message,
            'options' => $options,
        ]);
    }

    public function sendListMessage(
        string $recipient,
        string $description,
        string $buttonText,
        array $sections
    ): array {
        $recipient = $this->normalizeRecipient($recipient);
        $description = trim($description);

        if ($recipient === '' || $description === '' || $sections === []) {
            throw new \RuntimeException('Dados invalidos para envio de list message.');
        }

        return $this->sendInteractiveRequest('send-list-message', [
            'phone' => [$recipient],
            'isGroup' => str_ends_with($recipient, '@g.us'),
            'isLid' => str_ends_with($recipient, '@lid'),
            'description' => $description,
            'buttonText' => $buttonText,
            'sections' => $sections,
        ]);
    }

    public function getSessionStatus(): array
    {
        $response = $this->authorizedClient()->get($this->sessionApiUrl('status-session'));

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Nao foi possivel consultar o status da sessao. HTTP ' .
                $response->status() .
                ': ' .
                $response->body()
            );
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new \RuntimeException('Resposta invalida ao consultar status da sessao.');
        }

        return [
            'status' => strtoupper((string) ($payload['status'] ?? '')),
            'payload' => $payload,
        ];
    }

    protected function authorizedClient(): PendingRequest
    {
        return Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withToken($this->token());
    }

    protected function sessionApiUrl(string $action): string
    {
        return $this->baseApiUrl('/' . $this->sessionName() . '/' . $action);
    }

    protected function baseApiUrl(string $suffix = ''): string
    {
        return rtrim($this->serverUrl(), '/') . '/api' . $suffix;
    }

    protected function serverUrl(): string
    {
        $url = Setting::get('whatsapp_server_url', 'http://localhost:21465');

        return rtrim((string) $url, '/');
    }

    protected function sessionName(): string
    {
        $session = Setting::get('whatsapp_session_name', 'dimas');

        if (!$session) {
            throw new \RuntimeException('Sessao do WhatsApp nao configurada.');
        }

        return (string) $session;
    }

    protected function token(): string
    {
        $token = Setting::get('whatsapp_session_token');

        if (!$token) {
            throw new \RuntimeException('Token do WhatsApp nao configurado.');
        }

        if (str_contains($token, ':')) {
            [, $token] = explode(':', $token, 2);
        }

        return (string) $token;
    }

    protected function normalizeRecipient(string $recipient): string
    {
        $recipient = trim($recipient);

        if (
            str_ends_with($recipient, '@g.us') ||
            str_ends_with($recipient, '@lid') ||
            str_ends_with($recipient, '@c.us') ||
            str_ends_with($recipient, '@s.whatsapp.net')
        ) {
            return $recipient;
        }

        return preg_replace('/\D+/', '', $recipient) ?: $recipient;
    }

    protected function isConnectedStatus(string $status): bool
    {
        return in_array($status, ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'], true);
    }

    protected function sendInteractiveRequest(string $action, array $payload): array
    {
        $sessionStatus = $this->getSessionStatus();

        if (!$this->isConnectedStatus($sessionStatus['status'])) {
            throw new \RuntimeException(
                'Sessao do WhatsApp indisponivel para envio. Status atual: ' .
                ($sessionStatus['status'] ?: 'desconhecido')
            );
        }

        $response = $this->authorizedClient()->post($this->sessionApiUrl($action), $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Falha ao enviar mensagem pelo WPPConnect. HTTP ' .
                $response->status() .
                ': ' .
                $response->body()
            );
        }

        return $response->json() ?? [];
    }
}
