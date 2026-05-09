<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\WhatsAppChatMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;

class WhatsAppChat extends Component
{
    public $selectedPhone = null;
    public $messages = [];
    public $newMessage = '';
    public $conversations = [];
    public $search = '';
    public $filter = 'all'; // all, unread, in_progress, closed
    public $quickReplies = [];

    public function mount()
    {
        // Carregar quick replies mesmo sem autenticação
        // A autenticação será verificada em cada método individualmente
        $this->loadQuickReplies();

        // Tentar carregar conversas apenas se autenticado
        if (Auth::check()) {
            $this->loadConversations();
        }
    }

    public function loadQuickReplies()
    {
        $this->quickReplies = [
            [
                'key' => 'F1',
                'label' => 'Cardápio',
                'message' => 'Olá! Aqui está nosso cardápio do dia: [LINK_DO_CARDAPIO]'
            ],
            [
                'key' => 'F2',
                'label' => 'Horário',
                'message' => 'Funcionamos de segunda a sábado, das 11h às 22h. Domingo das 12h às 20h.'
            ],
            [
                'key' => 'F3',
                'label' => 'Entrega',
                'message' => 'Entregamos em toda região central. Taxa de entrega: R$ 5,00. Tempo médio: 40min.'
            ],
            [
                'key' => 'F4',
                'label' => 'Pagamento',
                'message' => 'Aceitamos: Dinheiro, PIX, Cartão (débito/crédito) e VR/VA.'
            ],
            [
                'key' => 'F5',
                'label' => 'Confirmar',
                'message' => 'Perfeito! Seu pedido foi confirmado. Tempo de preparo: ~30 minutos.'
            ],
            [
                'key' => 'F6',
                'label' => 'Agradecer',
                'message' => 'Obrigado pelo seu pedido! Apreciamos sua preferência. Volte sempre!'
            ],
            [
                'key' => 'F7',
                'label' => 'Transferir',
                'message' => 'Um momento, vou transferir você para um de nossos atendentes.'
            ],
            [
                'key' => 'F8',
                'label' => 'Status',
                'message' => 'Vou verificar o status do seu pedido e já retorno.'
            ],
        ];
    }

    public function loadConversations()
    {
        // Verificar autenticação
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return;
        }

        // Carregar conversas reais do banco de dados
        $query = \App\Models\WhatsAppChatMessage::selectRaw('
            phone,
            MAX(contact_name) as contact_name,
            MAX(message) as last_message,
            MAX(created_at) as last_activity,
            COUNT(CASE WHEN is_from_customer = 1 AND read_at IS NULL THEN 1 END) as unread_count
        ')
        ->groupBy('phone');

        // Aplicar filtro de busca
        if ($this->search) {
            $query->where(function($q) {
                $q->where('phone', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_name', 'like', '%' . $this->search . '%');
            });
        }

        // Aplicar filtro de status
        if ($this->filter === 'unread') {
            $query->havingRaw('COUNT(CASE WHEN is_from_customer = 1 AND read_at IS NULL THEN 1 END) > 0');
        }

        $this->conversations = $query
            ->orderBy('last_activity', 'desc')
            ->limit(30)
            ->get()
            ->map(function ($message) {
                $customer = \App\Models\Customer::where('phone', $message->phone)->first();

                // Verificar se há conversa humana em andamento
                $humanConversation = \App\Models\HumanConversation::where('phone', $message->phone)
                    ->whereIn('status', [
                        \App\Models\HumanConversation::STATUS_OPEN,
                        \App\Models\HumanConversation::STATUS_IN_PROGRESS
                    ])
                    ->first();

                return [
                    'phone' => $message->phone,
                    'name' => $customer?->name ?: ($message->contact_name ?: 'Cliente'),
                    'last_message' => strlen($message->last_message) > 50
                        ? substr($message->last_message, 0, 50) . '...'
                        : $message->last_message,
                    'unread' => (int) $message->unread_count,
                    'last_activity' => \Carbon\Carbon::parse($message->last_activity)->diffForHumans(),
                    'has_human_conversation' => !is_null($humanConversation),
                    'human_status' => $humanConversation?->status,
                ];
            })->toArray();

        // Ordenar por prioridade: não lidas primeiro, depois com conversa humana
        usort($this->conversations, function($a, $b) {
            // Prioridade 1: Não lidas
            if ($a['unread'] > 0 && $b['unread'] === 0) return -1;
            if ($a['unread'] === 0 && $b['unread'] > 0) return 1;

            // Prioridade 2: Com conversa humana
            if ($a['has_human_conversation'] && !$b['has_human_conversation']) return -1;
            if (!$a['has_human_conversation'] && $b['has_human_conversation']) return 1;

            return 0;
        });
    }

    public function selectConversation($phone)
    {
        // Verificar autenticação
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return;
        }

        $this->selectedPhone = $phone;
        $this->messages = [];

        // Carregar mensagens da conversa
        $this->loadMockMessages($phone);

        // NOTA: Não abrir conversa humana automaticamente para evitar criação de chat "Atendente"
        // O chat do PDV é apenas para visualização e comunicação interna
        \Log::info('Conversa selecionada no PDV', ['phone' => $phone]);
    }

    public function loadMockMessages($phone)
    {
        // Verificar autenticação
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return;
        }

        // Carregar mensagens reais do banco de dados
        $this->messages = \App\Models\WhatsAppChatMessage::where('phone', $phone)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'phone' => $message->phone,
                    'message' => $message->message,
                    'contact_name' => $message->contact_name,
                    'timestamp' => $message->created_at->toISOString(),
                    'is_from_customer' => $message->is_from_customer,
                ];
            })->toArray();

        // Marcar mensagens como lidas
        \App\Models\WhatsAppChatMessage::where('phone', $phone)
            ->where('is_from_customer', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function sendMessage()
    {
        // Verificar autenticação
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return;
        }

        if (empty(trim($this->newMessage)) || !$this->selectedPhone) {
            return;
        }

        try {
            // Tentar enviar mensagem via WPPConnect
            $wppConnectService = app(\App\Services\WppConnectService::class);
            $result = $wppConnectService->sendMessage($this->selectedPhone, $this->newMessage);

            $messageId = $result['messageId'] ?? ('pdv_' . time() . '_' . rand(1000, 9999));

            // Emitir evento para mensagem enviada
            \App\Events\WhatsAppMessageSent::dispatch(
                $this->selectedPhone,
                $this->newMessage,
                $messageId
            );

            // Adicionar à lista local (o evento também fará isso via WebSocket)
            $this->addLocalMessage($this->selectedPhone, $this->newMessage, 'Atendente', false);

            $this->newMessage = '';

            // Scroll para a última mensagem
            $this->dispatch('scroll-to-bottom');

        } catch (\Exception $e) {
            // Se falhar, apenas mostrar localmente (modo simulado)
            $this->addLocalMessage($this->selectedPhone, $this->newMessage . ' (modo simulado)', 'Atendente', false);

            \Log::warning('WPPConnect falhou, usando modo simulado para mensagem do PDV', [
                'phone' => $this->selectedPhone,
                'error' => $e->getMessage()
            ]);

            $this->newMessage = '';
            $this->dispatch('scroll-to-bottom');
        }
    }

    protected function addLocalMessage($phone, $message, $contactName, $isFromCustomer)
    {
        $this->messages[] = [
            'id' => 'local_' . time() . '_' . rand(1000, 9999),
            'phone' => $phone,
            'message' => $message,
            'contact_name' => $contactName,
            'timestamp' => now()->toISOString(),
            'is_from_customer' => $isFromCustomer,
        ];
    }

    #[On('echo:whatsapp.messages,whatsapp.message.received')]
    public function onMessageReceived($payload)
    {
        // Se a mensagem é para a conversa selecionada, adiciona à lista
        if ($this->selectedPhone === $payload['phone']) {
            $this->messages[] = [
                'id' => 'ws_' . time() . '_' . rand(1000, 9999),
                'phone' => $payload['phone'],
                'message' => $payload['message'],
                'contact_name' => $payload['display_name'],
                'timestamp' => $payload['timestamp'],
                'is_from_customer' => $payload['is_from_customer'],
            ];

            // Scroll para a última mensagem
            $this->dispatch('scroll-to-bottom');
        }

        // Atualizar lista de conversas
        $this->updateConversationList($payload);
    }

    #[On('echo:whatsapp.messages,whatsapp.message.sent')]
    public function onMessageSent($payload)
    {
        // Se a mensagem é para a conversa selecionada, adiciona à lista
        if ($this->selectedPhone === $payload['phone'] && !$payload['is_from_customer']) {
            $this->messages[] = [
                'id' => 'ws_' . time() . '_' . rand(1000, 9999),
                'phone' => $payload['phone'],
                'message' => $payload['message'],
                'contact_name' => $payload['display_name'],
                'timestamp' => $payload['timestamp'],
                'is_from_customer' => $payload['is_from_customer'],
            ];

            // Scroll para a última mensagem
            $this->dispatch('scroll-to-bottom');
        }
    }

    protected function updateConversationList($payload)
    {
        // Atualizar ou adicionar conversa na lista
        $index = array_search($payload['phone'], array_column($this->conversations, 'phone'));

        if ($index !== false) {
            // Atualizar conversa existente
            $this->conversations[$index]['last_message'] = substr($payload['message'], 0, 50) . '...';
            $this->conversations[$index]['last_activity'] = now()->diffForHumans();
            $this->conversations[$index]['unread'] += $payload['is_from_customer'] ? 1 : 0;
        } else {
            // Adicionar nova conversa
            array_unshift($this->conversations, [
                'phone' => $payload['phone'],
                'name' => $payload['display_name'],
                'last_message' => substr($payload['message'], 0, 50) . '...',
                'unread' => $payload['is_from_customer'] ? 1 : 0,
                'last_activity' => now()->diffForHumans(),
            ]);

            // Manter apenas as 20 conversas mais recentes
            $this->conversations = array_slice($this->conversations, 0, 20);
        }
    }

    public function createOrderFromChat()
    {
        if (!$this->selectedPhone) {
            return;
        }

        // Redirecionar para criação de pedido com o telefone pré-preenchido
        return redirect()->route('orders.create', ['phone' => $this->selectedPhone]);
    }

    public function useQuickReply($index)
    {
        // Verificar autenticação
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return;
        }

        if (!isset($this->quickReplies[$index]) || !$this->selectedPhone) {
            return;
        }

        $quickReply = $this->quickReplies[$index];
        $this->newMessage = $quickReply['message'];

        // Disparar evento para enviar automaticamente após 500ms
        $this->dispatch('quick-reply-selected', index: $index);
    }

    public function checkAuth()
    {
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return false;
        }
        return true;
    }

    public function applyFilter($filter)
    {
        // Verificar autenticação
        if (!Auth::check()) {
            $this->dispatch('session-expired');
            return;
        }

        $this->filter = $filter;
        $this->loadConversations();
    }

    public function render()
    {
        return view('livewire.whats-app-chat');
    }
}