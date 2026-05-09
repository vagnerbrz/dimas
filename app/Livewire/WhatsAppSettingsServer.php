<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class WhatsAppSettingsServer extends Component
{
    public $server_url;
    public $session_name;
    public $api_secret;
    public $access_token;
    public $connection_status = 'unknown';
    public $qr_code = null;
    public $message = '';
    public $debug_log = [];

    public function mount()
    {
        $this->server_url = Setting::get('whatsapp_server_url', 'http://localhost:21465');
        $this->session_name = Setting::get('whatsapp_session_name', 'dimas');
        $this->api_secret = Setting::get('whatsapp_api_secret', 'THISISMYSECURETOKEN');
        $storedToken = Setting::get('whatsapp_session_token');
        $this->access_token = $this->normalizeStoredToken($storedToken);

        if ($this->access_token !== $storedToken) {
            Setting::set('whatsapp_session_token', $this->access_token);
        }

        $this->checkStatus();
    }

    public function updatedServerUrl()
    {
        $this->server_url = $this->normalizeServerUrl($this->server_url);
        Setting::set('whatsapp_server_url', $this->server_url);
        $this->debugLog("URL alterada para: {$this->server_url}");
    }

    public function updatedSessionName()
    {
        Setting::set('whatsapp_session_name', $this->session_name);
        Setting::set('whatsapp_session_token', null);
        $this->access_token = null;
        $this->debugLog("Sessao alterada para: {$this->session_name}. Token anterior descartado.");
    }

    public function updatedApiSecret()
    {
        Setting::set('whatsapp_api_secret', $this->api_secret);
        Setting::set('whatsapp_session_token', null);
        $this->access_token = null;
        $this->debugLog('Secret key atualizada. Token anterior descartado.');
    }

    public function checkStatus()
    {
        $this->message = '';
        $this->server_url = $this->normalizeServerUrl($this->server_url);

        if (!$this->server_url || !$this->session_name || !$this->api_secret) {
            $this->connection_status = 'error';
            $this->message = 'Preencha URL do servidor, nome da sessao e secret key.';
            return;
        }

        $this->debugLog('Validando token da sessao...');

        try {
            $this->ensureAccessToken();

            $response = $this->authorizedClient()->get($this->sessionApiUrl('status-session'));
            $this->debugLog('Status-session: ' . $response->status());

            if ($response->successful()) {
                $this->applyStatusPayload($response->json());
                return;
            }

            $this->debugLog('Status indisponivel. Tentando start-session para obter o estado atual...');
            $this->startSessionRequest();
        } catch (\Throwable $e) {
            $this->connection_status = 'error';
            $this->message = 'Erro tecnico: ' . $e->getMessage();
            $this->debugLog('ERRO STATUS: ' . $e->getMessage());
        }
    }

    public function generateQRCode()
    {
        $this->message = '';
        $this->debugLog('Solicitando inicio da sessao/QR Code...');

        try {
            $this->ensureAccessToken();
            $this->startSessionRequest(true);
        } catch (\Throwable $e) {
            $this->connection_status = 'error';
            $this->message = 'Erro ao gerar QR Code: ' . $e->getMessage();
            $this->debugLog('ERRO QR: ' . $e->getMessage());
        }
    }

    public function refreshConnectionState()
    {
        if (!$this->access_token || !$this->server_url || !$this->session_name) {
            return;
        }

        try {
            $response = $this->authorizedClient()->get($this->sessionApiUrl('status-session'));
            $this->debugLog('Refresh status-session: ' . $response->status());

            if ($response->successful()) {
                $payload = $response->json();

                if (is_array($payload)) {
                    $this->applyStatusPayload($payload, false);
                }
            }

            if ($this->connection_status === 'waiting_qr') {
                $this->refreshQrCode();
            }
        } catch (\Throwable $e) {
            $this->debugLog('ERRO REFRESH: ' . $e->getMessage());
        }
    }

    public function logout()
    {
        $this->message = '';
        $this->debugLog('Solicitando encerramento da sessao...');

        try {
            $this->ensureAccessToken();

            $response = $this->authorizedClient()->post($this->sessionApiUrl('close-session'));
            $this->debugLog('Close-session: ' . $response->status());

            if (!$response->successful()) {
                throw new \RuntimeException('Servidor recusou o encerramento da sessao.');
            }

            $this->connection_status = 'disconnected';
            $this->qr_code = null;
            $this->message = 'Sessao encerrada com sucesso.';
        } catch (\Throwable $e) {
            $this->connection_status = 'error';
            $this->message = 'Erro ao encerrar sessao: ' . $e->getMessage();
            $this->debugLog('ERRO LOGOUT: ' . $e->getMessage());
        }
    }

    public function debugLog($msg)
    {
        $this->debug_log[] = '[' . now()->format('H:i:s') . '] ' . $msg;

        if (count($this->debug_log) > 12) {
            array_shift($this->debug_log);
        }
    }

    public function render()
    {
        return view('livewire.whats-app-settings');
    }

    protected function ensureAccessToken(): void
    {
        if ($this->access_token) {
            return;
        }

        $this->debugLog('Gerando token de acesso...');

        $response = $this->baseClient()->post(
            $this->baseApiUrl("/{$this->session_name}/{$this->api_secret}/generate-token")
        );

        $this->debugLog('Generate-token: ' . $response->status());

        if (!$response->successful()) {
            throw new \RuntimeException('Nao foi possivel gerar o token no WPPConnect Server.');
        }

        $payload = $response->json();
        $token = $payload['token'] ?? $this->normalizeStoredToken($payload['full'] ?? null);

        if (!$token) {
            throw new \RuntimeException('O servidor nao retornou um token valido.');
        }

        $this->access_token = $token;
        Setting::set('whatsapp_session_token', $token);
        $this->debugLog('Token salvo com sucesso.');
    }

    protected function startSessionRequest(bool $preferQrMessage = false): void
    {
        $webhookUrl = url('/api/whatsapp/webhook');

        $response = $this->authorizedClient()->post($this->sessionApiUrl('start-session'), [
            'webhook' => $webhookUrl,
            'waitQrCode' => true,
        ]);
        $this->debugLog('Start-session: ' . $response->status());
        $this->debugLog('Webhook configurado para: ' . $webhookUrl);

        if (!$response->successful()) {
            throw new \RuntimeException('Servidor recusou a inicializacao da sessao.');
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new \RuntimeException('Servidor retornou uma resposta invalida ao iniciar a sessao.');
        }

        $this->applyStatusPayload($payload);

        if (!$this->qr_code && $this->connection_status === 'waiting_qr') {
            $this->pollQrCode();
        }

        if ($preferQrMessage && $this->connection_status === 'waiting_qr' && !$this->message) {
            $this->message = 'QR Code atualizado. Escaneie com o WhatsApp do aparelho.';
        }
    }

    protected function applyStatusPayload(array $payload, bool $replaceMessage = true): void
    {
        $rawStatus = strtoupper((string) ($payload['status'] ?? $payload['state'] ?? ''));
        $this->debugLog('Status recebido: ' . ($rawStatus ?: 'sem status'));

        if (in_array($rawStatus, ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'], true)) {
            $this->connection_status = 'connected';
            $this->qr_code = null;
            if ($replaceMessage) {
                $this->message = 'Sessao conectada com sucesso.';
            }
            return;
        }

        $this->qr_code = $this->extractQrCode($payload);

        if ($this->qr_code) {
            $this->connection_status = 'waiting_qr';
            if ($replaceMessage) {
                $this->message = 'Sessao aguardando leitura do QR Code.';
            }
            return;
        }

        if (in_array($rawStatus, ['INITIALIZING', 'QRCODE', 'SCAN_QRCODE', 'STARTING'], true)) {
            $this->connection_status = 'waiting_qr';
            if ($replaceMessage) {
                $this->message = 'Sessao inicializando. Gere ou atualize o QR Code se necessario.';
            }
            return;
        }

        if (in_array($rawStatus, ['CLOSED', 'CLOSE', 'DISCONNECTED', 'NOT_LOGGED'], true)) {
            $this->connection_status = 'disconnected';
            if ($replaceMessage) {
                $this->message = 'Sessao desconectada.';
            }
            return;
        }

        $this->connection_status = 'unknown';
        if ($replaceMessage) {
            $this->message = $payload['message'] ?? 'Servidor respondeu, mas retornou um status nao reconhecido.';
        }
    }

    protected function pollQrCode(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            usleep(1500000);

            $response = $this->authorizedClient()->get($this->sessionApiUrl('qrcode-session'));
            $this->debugLog('Qrcode-session tentativa ' . $attempt . ': ' . $response->status());

            if (!$response->successful()) {
                continue;
            }

            $payload = $response->json();

            if (!is_array($payload)) {
                continue;
            }

            $qrCode = $this->extractQrCode($payload);

            if ($qrCode) {
                $this->qr_code = $qrCode;
                $this->connection_status = 'waiting_qr';
                $this->message = 'QR Code recebido. Escaneie com o WhatsApp do aparelho.';
                return;
            }

            $status = strtoupper((string) ($payload['status'] ?? ''));

            if ($status === 'CLOSED') {
                $this->connection_status = 'disconnected';
                $this->message = $payload['message'] ?? 'A sessao foi fechada antes do QR Code ficar disponivel.';
                return;
            }
        }
    }

    protected function refreshQrCode(): void
    {
        $response = $this->authorizedClient()->get($this->sessionApiUrl('qrcode-session'));
        $this->debugLog('Refresh qrcode-session: ' . $response->status());

        if (!$response->successful()) {
            return;
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            return;
        }

        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (in_array($status, ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'], true)) {
            $this->connection_status = 'connected';
            $this->qr_code = null;
            $this->message = 'Sessao conectada com sucesso.';
            return;
        }

        $qrCode = $this->extractQrCode($payload);

        if ($qrCode) {
            $this->qr_code = $qrCode;
            $this->connection_status = 'waiting_qr';
            $this->message = 'QR Code recebido. Escaneie com o WhatsApp do aparelho.';
            return;
        }

        if ($status === 'CONNECTED' || $status === 'OPEN') {
            $this->connection_status = 'connected';
            $this->qr_code = null;
            $this->message = 'Sessao conectada com sucesso.';
            return;
        }

        if ($status === 'CLOSED') {
            $this->connection_status = 'disconnected';
            $this->message = $payload['message'] ?? 'A sessao foi fechada.';
        }
    }

    protected function extractQrCode(array $payload): ?string
    {
        $qrCode = $payload['qrcode'] ?? $payload['qrCode'] ?? $payload['base64Qrimg'] ?? null;

        if (!$qrCode || !is_string($qrCode)) {
            return null;
        }

        if (str_starts_with($qrCode, 'data:image')) {
            return $qrCode;
        }

        return 'data:image/png;base64,' . $qrCode;
    }

    protected function baseClient(): PendingRequest
    {
        return Http::timeout(15)
            ->acceptJson()
            ->asJson();
    }

    protected function authorizedClient(): PendingRequest
    {
        return $this->baseClient()->withToken($this->access_token);
    }

    protected function normalizeServerUrl(?string $url): string
    {
        return rtrim((string) $url, '/');
    }

    protected function baseApiUrl(string $suffix = ''): string
    {
        return $this->normalizeServerUrl($this->server_url) . '/api' . $suffix;
    }

    protected function sessionApiUrl(string $action): string
    {
        return $this->baseApiUrl("/{$this->session_name}/{$action}");
    }

    protected function normalizeStoredToken(?string $token): ?string
    {
        if (!$token) {
            return null;
        }

        if (str_contains($token, ':')) {
            $parts = explode(':', $token, 2);
            return $parts[1] ?: null;
        }

        return $token;
    }
}
