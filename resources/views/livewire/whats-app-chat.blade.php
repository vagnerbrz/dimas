<div class="h-full flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden"
     x-data="{
         isAuthenticated: @json(auth()->check()),
         checkAuth() {
             if (!this.isAuthenticated) {
                 this.showSessionExpiredModal();
                 return false;
             }
             return true;
         },
         showSessionExpiredModal() {
             const modal = document.querySelector('[x-data*=\"showSessionExpired\"]');
             if (modal && modal.__x) {
                 modal.__x.$data.showSessionExpired = true;
                 modal.style.display = 'flex';
             }
         }
     }">
    <!-- Header -->
    <div class="p-4 border-b border-gray-200 bg-gray-50">
        <div class="flex justify-between items-center mb-3">
            <div>
                <h2 class="text-lg font-bold text-gray-700">WhatsApp PDV</h2>
                <p class="text-sm text-gray-500">Atendimento em tempo real</p>
            </div>
            <div class="flex items-center space-x-2">
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Buscar conversa..."
                        class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-48"
                    >
                    <div class="absolute left-3 top-2.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                @if($selectedPhone)
                    <button
                        wire:click="createOrderFromChat"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium flex items-center"
                    >
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Criar Pedido
                    </button>
                @endif
            </div>
        </div>

        <!-- Filtros e controles mobile -->
        <div class="flex justify-between items-center">
            <div class="flex space-x-2">
                <button
                    wire:click="applyFilter('all')"
                    class="px-3 py-1.5 text-sm rounded-lg transition {{ $filter === 'all' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}"
                >
                    Todas
                </button>
                <button
                    wire:click="applyFilter('unread')"
                    class="px-3 py-1.5 text-sm rounded-lg transition {{ $filter === 'unread' ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}"
                >
                    Não lidas
                    @php
                        $unreadCount = array_reduce($conversations, function($carry, $conv) {
                            return $carry + ($conv['unread'] > 0 ? 1 : 0);
                        }, 0);
                    @endphp
                    @if($unreadCount > 0)
                        <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ $unreadCount }}</span>
                    @endif
                </button>
                <button
                    wire:click="applyFilter('in_progress')"
                    class="px-3 py-1.5 text-sm rounded-lg transition {{ $filter === 'in_progress' ? 'bg-yellow-100 text-yellow-700 border border-yellow-300' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}"
                >
                    Em atendimento
                    @php
                        $inProgressCount = array_reduce($conversations, function($carry, $conv) {
                            return $carry + ($conv['has_human_conversation'] ? 1 : 0);
                        }, 0);
                    @endphp
                    @if($inProgressCount > 0)
                        <span class="ml-1 bg-yellow-500 text-white text-xs px-1.5 py-0.5 rounded-full">{{ $inProgressCount }}</span>
                    @endif
                </button>
            </div>

            <!-- Botão mobile para alternar entre lista e chat -->
            <button
                type="button"
                class="md:hidden px-3 py-1.5 text-sm bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 transition flex items-center"
                x-data="{ showChat: false }"
                @click="showChat = !showChat;
                        document.querySelector('.lista-conversas').classList.toggle('hidden');
                        document.querySelector('.area-chat').classList.toggle('hidden');
                        document.querySelector('.area-chat').classList.toggle('flex');"
            >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="!showChat">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="showChat" style="display: none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span x-text="showChat ? 'Lista' : 'Chat'">Chat</span>
            </button>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        <!-- Lista de conversas -->
        <div class="lista-conversas w-full md:w-1/3 lg:w-1/3 border-r border-gray-200 overflow-y-auto">
            @forelse($conversations as $conversation)
                <div
                    wire:click="selectConversation('{{ $conversation['phone'] }}')"
                    class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition {{ $selectedPhone === $conversation['phone'] ? 'bg-blue-50 border-l-4 border-l-blue-500' : '' }}"
                >
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center">
                                        <h3 class="font-medium text-gray-900 truncate">{{ $conversation['name'] }}</h3>
                                        @if($conversation['has_human_conversation'])
                                            <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded-full">Atendimento</span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-500 truncate">{{ $conversation['phone'] }}</p>
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 truncate">{{ $conversation['last_message'] }}</p>
                            <div class="mt-1 flex justify-between items-center">
                                <span class="text-xs text-gray-400">{{ $conversation['last_activity'] }}</span>
                                @if($conversation['unread'] > 0)
                                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                                        {{ $conversation['unread'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p>Nenhuma conversa encontrada</p>
                    <p class="text-sm mt-2">As conversas do WhatsApp aparecerão aqui automaticamente</p>
                </div>
            @endforelse
        </div>

        <!-- Área do chat -->
        <div class="area-chat hidden md:flex flex-1 flex-col">
            @if($selectedPhone)
                <!-- Header da conversa -->
                <div class="p-4 border-b border-gray-200 bg-white">
                    @php
                        $currentConv = collect($conversations)->firstWhere('phone', $selectedPhone);
                    @endphp
                    <div class="flex items-center">
                        <!-- Botão voltar (mobile) -->
                        <button
                            type="button"
                            class="md:hidden mr-3 p-2 text-gray-600 hover:bg-gray-100 rounded-lg"
                            @click="document.querySelector('.lista-conversas').classList.remove('hidden');
                                    document.querySelector('.area-chat').classList.add('hidden');
                                    document.querySelector('.area-chat').classList.remove('flex');
                                    showChat = false;"
                            x-data="{ showChat: false }"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                        </button>

                        <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-gray-900 truncate">{{ $currentConv['name'] ?? 'Cliente' }}</h3>
                            <p class="text-sm text-gray-500 truncate">{{ $selectedPhone }}</p>
                        </div>
                    </div>
                </div>

                <!-- Mensagens -->
                <div
                    id="chat-messages"
                    class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50"
                    x-data
                    x-init="$watch('$wire.messages.length', () => {
                        $nextTick(() => {
                            const container = document.getElementById('chat-messages');
                            container.scrollTop = container.scrollHeight;
                        });
                    })"
                >
                    @foreach($messages as $message)
                        <div class="flex {{ $message['is_from_customer'] ? 'justify-start' : 'justify-end' }}">
                            <div class="max-w-[70%]">
                                <div class="rounded-lg px-4 py-2 {{ $message['is_from_customer'] ? 'bg-white border border-gray-200' : 'bg-blue-500 text-white' }}">
                                    <p class="text-sm">{{ $message['message'] }}</p>
                                    <p class="text-xs mt-1 {{ $message['is_from_customer'] ? 'text-gray-400' : 'text-blue-100' }}">
                                        {{ \Carbon\Carbon::parse($message['timestamp'])->format('H:i') }}
                                    </p>
                                </div>
                                @if($message['is_from_customer'])
                                    <p class="text-xs text-gray-500 mt-1 ml-1">{{ $message['contact_name'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Quick Replies (Templates de resposta rápida) -->
                <div class="px-4 pt-3 pb-2 border-t border-gray-200 bg-gray-50">
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($quickReplies as $index => $reply)
                            <button
                                type="button"
                                wire:click="useQuickReply({{ $index }})"
                                class="px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center group disabled:opacity-50 disabled:cursor-not-allowed"
                                title="{{ $reply['message'] }}"
                                :disabled="!isAuthenticated"
                                @click.prevent="if (checkAuth()) $wire.useQuickReply({{ $index }})"
                                @keydown.{{ strtolower($reply['key']) }}.window="if ($wire.$el && $wire.$el.closest('body') && checkAuth()) $wire.useQuickReply({{ $index }})"
                            >
                                <span class="font-mono bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded text-xs mr-2 group-hover:bg-gray-200">
                                    {{ $reply['key'] }}
                                </span>
                                <span class="text-gray-700">{{ $reply['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-2 text-xs text-gray-500 flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        Pressione F1-F8 para usar atalhos
                    </div>
                </div>

                <!-- Input de mensagem -->
                <div class="p-4 border-t border-gray-200 bg-white">
                    <form wire:submit.prevent="sendMessage" class="flex space-x-2">
                        <input
                            type="text"
                            wire:model="newMessage"
                            placeholder="Digite sua mensagem ou use atalhos F1-F8..."
                            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            autocomplete="off"
                            x-data
                            x-ref="messageInput"
                            @keydown.f1.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(0)"
                            @keydown.f2.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(1)"
                            @keydown.f3.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(2)"
                            @keydown.f4.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(3)"
                            @keydown.f5.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(4)"
                            @keydown.f6.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(5)"
                            @keydown.f7.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(6)"
                            @keydown.f8.prevent="if ($wire.$el.closest('body') && checkAuth()) $wire.useQuickReply(7)"
                        >
                        <button
                            type="submit"
                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex items-center"
                            :disabled="!$wire.newMessage.trim()"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span class="ml-2">Enviar</span>
                        </button>
                    </form>
                </div>
            @else
                <!-- Tela vazia -->
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-gray-500">
                    <svg class="w-24 h-24 text-gray-300 mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Selecione uma conversa</h3>
                    <p class="text-center max-w-md">Escolha uma conversa na lista ao lado para começar a atender pelo WhatsApp diretamente do PDV.</p>
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg max-w-md">
                        <h4 class="font-medium text-blue-800 mb-2">💡 Dica rápida</h4>
                        <p class="text-sm text-blue-700">Quando um cliente enviar mensagem pelo WhatsApp, a conversa aparecerá aqui automaticamente em tempo real.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Modal de Sessão Expirada -->
    <div x-data="{ showSessionExpired: false }" x-show="showSessionExpired" style="display: none;"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 max-w-md mx-4">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
                    <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Sessão Expirada</h3>
            </div>
            <p class="text-gray-600 mb-6">
                Sua sessão expirou por inatividade. Por favor, faça login novamente para continuar usando o sistema.
            </p>
            <div class="flex justify-end space-x-3">
                <button @click="showSessionExpired = false" class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    Fechar
                </button>
                <a href="{{ route('login') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    Fazer Login
                </a>
            </div>
        </div>
    </div>

    <!-- Script para scroll automático -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('scroll-to-bottom', () => {
                setTimeout(() => {
                    const container = document.getElementById('chat-messages');
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                }, 100);
            });

            // Enviar automaticamente após selecionar quick reply
            Livewire.on('quick-reply-selected', ({ index }) => {
                setTimeout(() => {
                    // Focar no input
                    const input = document.querySelector('input[wire\\:model="newMessage"]');
                    if (input) {
                        input.focus();
                        // Colocar cursor no final
                        input.setSelectionRange(input.value.length, input.value.length);

                        // Enviar após 1 segundo (tempo para usuário editar se quiser)
                        setTimeout(() => {
                            const sendButton = document.querySelector('button[type="submit"]');
                            if (sendButton && !sendButton.disabled) {
                                sendButton.click();
                            }
                        }, 1000);
                    }
                }, 50);
            });

            // Mostrar modal quando sessão expirar
            Livewire.on('session-expired', () => {
                const modal = document.querySelector('[x-data*="showSessionExpired"]');
                if (modal) {
                    modal.__x.$data.showSessionExpired = true;
                    modal.style.display = 'flex';
                } else {
                    // Fallback: redirecionar para login
                    window.location.href = '{{ route("login") }}';
                }
            });
        });
    </script>
</div>