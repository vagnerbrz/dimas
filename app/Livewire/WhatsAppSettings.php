<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class WhatsAppSettings extends Component
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
        $this->access_token = Setting::get('whatsapp_session_token');
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
        $this->debugLog("Sessão alterada para: {$this->session_name}");
    }

    public function updatedApiSecret()
    {
        Setting::set('whatsapp_api_secret', $this->api_secret);
        Setting::set('whatsapp_session_token', null);
        $this->access_token = null;
        $this->debugLog('Secret key atualizada. Token antigo descartado.');
    }

    public function debugLog($msg)
    {
        $this->debug_log[] = '[' . now()->format('H:i:s') . '] ' . $msg;
        if (count($this->debug_log) > 12) {
            array_shift($this->debug_log);
        }
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
        $this->debugLog("Verificando conexão...");

        try {
            $this->debugLog('Validando token da sessao...');
            $this->ensureAccessToken();

            $response = $this->authorizedClient()->get($this->sessionApiUrl('status-session'));
            $this->debugLog('Status-session: ' . $response->status());

            if ($response->successful()) {
                $this->applyStatusPayload($response->json());
                return;
            }

            $this->debugLog('Falha ao consultar status. Tentando iniciar sessao para obter estado atual...');
            $this->startSessionRequest();
            return;

            $token = Setting::get('whatsapp_session_token');

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . Setting::get('whatsapp_api_secret', 'THISISMYSECURETOKEN')
                ])
                ->post("{$this->server_url}/api/sessions", [
                    'name' => $this->session_name,
                    'waitQrCode' => false
                ]);

            $this->debugLog("Resposta servidor: " . $response->status() . " - " . substr($response->body(), 0, 50) . "...");

            if ($response->successful()) {
                $data = $response->json();
                $this->connection_status = $data['status'] ?? 'disconnected';

                if ($this->connection_status === 'disconnected') {
                    $this->debugLog("Status: Desconectado. Gerando QR Code...");
                    $this->generateQRCode();
                } else {
                    $this->qr_code = null;
                    $this->debugLog("Status: Conectado!");
                }
            } else {
                $this->connection_status = 'error';
                $this->message = "O servidor respondeu com erro: " . $response->status() . " - " . ($response->json()['message'] ?? 'Erro desconhecido');
            }
        } catch (\Throwable $e) {
            $this->connection_status = 'error';
            $this->message = 'Erro ao encerrar sessao: ' . $e->getMessage();
            $this->debugLog('ERRO LOGOUT: ' . $e->getMessage());
            return;
            $this->connection_status = 'error';
            $this->message = 'Erro tecnico: ' . $e->getMessage();
            $this->debugLog('EXCECAO: ' . $e->getMessage());
            return;
            $this->connection_status = 'error';
            $this->message = "Erro técnico: " . $e->getMessage();
            $this->debugLog("EXCEÇÃO: " . $e->getMessage());
        }
    }

    public function generateQRCode()
    {
        $this->message = '';
        $this->debugLog('Solicitando inicio da sessao/QR Code...');

        $this->debugLog("Solicitando QR Code...");
        try {
            $this->ensureAccessToken();
            $this->startSessionRequest(true);
            return;

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . Setting::get('whatsapp_api_secret', 'THISISMYSECURETOKEN')
                ])
                ->post("{$this->server_url}/api/start-session", [
                    'session' => $this->session_name
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Salva o token da sessão retornado pelo servidor
                if (isset($data['token'])) {
                    Setting::set('whatsapp_session_token', $data['token']);
                    $this->debugLog("Token de sessão salvo com sucesso.");
                }

                $this->qr_code = $data['qrCode'] ?? null;
                $this->connection_status = 'waiting_qr';
                $this->debugLog("QR Code recebido com sucesso.");
            } else {
                $this->message = "Servidor recusou a criação da sessão. Status: " . $response->status();
            }
        } catch (\Throwable $e) {
            $this->connection_status = 'error';
            $this->message = 'Erro ao gerar QR Code: ' . $e->getMessage();
            $this->debugLog('ERRO QR: ' . $e->getMessage());
            return;
            $this->message = "Erro ao gerar QR Code: " . $e->getMessage();
            $this->debugLog("ERRO QR: " . $e->getMessage());
        }
    }

    public function logout()
    {
        $this->message = '';
        $this->debugLog('Solicitando encerramento da sessao...');
        $this->debugLog("Solicitando logout...");
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
            return;

            $token = Setting::get('whatsapp_session_token');

            Http::timeout(5)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . Setting::get('whatsapp_api_secret', 'THISISMYSECURETOKEN')
                ])
                ->post("{$this->server_url}/api/logout", [
                    'session' => $this->session_name,
                    'token' => $token
                ]);

            Setting::set('whatsapp_session_token', null); // Limpa o token ao deslogar
            $this->connection_status = 'disconnected';
            $this->qr_code = null;
            $this->message = "Sessão encerrada com sucesso.";
            $this->debugLog("Sessão encerrada.");
        } catch (\Throwable $e) {
            $this->message = "Erro ao encerrar sessão: " . $e->getMessage();
        }
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
            throw new \RuntimeException('Nao foi possivel gerar o token de acesso no WPPConnect Server.');
        }

        $payload = $response->json();
        $token = $payload['full'] ?? $payload['token'] ?? null;

        if (!$token) {
            throw new \RuntimeException('O servidor nao retornou um token valido.');
        }

        $this->access_token = $token;
        Setting::set('whatsapp_session_token', $token);
        $this->debugLog('Token salvo com sucesso.');
    }

    protected function startSessionRequest(bool $preferQrMessage = false): void
    {
        $response = $this->authorizedClient()->post($this->sessionApiUrl('start-session'));

        $this->debugLog('Start-session: ' . $response->status());

        if (!$response->successful()) {
            throw new \RuntimeException('Servidor recusou a inicializacao da sessao.');
        }

        $this->applyStatusPayload($response->json());

        if ($preferQrMessage && $this->connection_status === 'waiting_qr' && !$this->message) {
            $this->message = 'QR Code atualizado. Escaneie com o WhatsApp do aparelho.';
        }
    }

    protected function applyStatusPayload(array $payload): void
    {
        $rawStatus = strtoupper((string) ($payload['status'] ?? $payload['state'] ?? ''));
        $this->debugLog('Status recebido: ' . ($rawStatus ?: 'sem status'));

        $this->qr_code = $this->extractQrCode($payload);

        if ($this->qr_code) {
            $this->connection_status = 'waiting_qr';
            $this->message = 'Sessao aguardando leitura do QR Code.';
            return;
        }

        if (in_array($rawStatus, ['CONNECTED', 'WORKING', 'OPEN', 'ISLOGGED'], true)) {
            $this->connection_status = 'connected';
            $this->message = 'Sessao conectada com sucesso.';
            return;
        }

        if (in_array($rawStatus, ['INITIALIZING', 'QRCODE', 'SCAN_QRCODE', 'STARTING'], true)) {
            $this->connection_status = 'waiting_qr';
            $this->message = 'Sessao inicializando. Gere ou atualize o QR Code se necessario.';
            return;
        }

        if (in_array($rawStatus, ['CLOSED', 'CLOSE', 'DISCONNECTED', 'NOT_LOGGED'], true)) {
            $this->connection_status = 'disconnected';
            $this->message = 'Sessao desconectada.';
            return;
        }

        $this->connection_status = 'unknown';
        $this->message = $payload['message'] ?? 'Servidor respondeu, mas retornou um status nao reconhecido.';
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

    public function render()
    {
        return view('livewire.whats-app-settings');
    }
}
