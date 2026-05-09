<div class="grid grid-cols-1 lg:grid-cols-3 gap-6"
     @if(in_array($connection_status, ['waiting_qr', 'connected', 'unknown'])) wire:poll.5s="refreshConnectionState" @endif>
    <!-- Coluna de Configurações (Esquerda) -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-6">
        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.125 2.25 14.25 6.25 12.25 8.25 10.125 6.25 6.25 8.25 6.25 12.25 10.125 14.25 12.25 16.25 14.25 12.25 14.25 10.125 18.25 6.25 16.25 12.25 14.25"></path></svg>
            Configurações do Servidor
        </h3>

        <div class="space-y-4">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">URL do Servidor WPPConnect</label>
                <input type="text" wire:model.live="server_url"
                       class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition"
                       placeholder="http://localhost:8080">
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Nome da Sessão</label>
                <input type="text" wire:model.live="session_name"
                       class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition"
                       placeholder="dimas-bot">
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-semibold text-gray-700">Secret Key do WPPConnect</label>
                <input type="text" wire:model.live="api_secret"
                       class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition"
                       placeholder="THISISMYSECURETOKEN">
            </div>

            <div class="rounded-lg bg-gray-50 border border-gray-200 p-3">
                <span class="block text-xs font-semibold text-gray-600 uppercase tracking-wide">Token atual</span>
                <span class="block mt-1 text-xs text-gray-500 break-all">
                    {{ $access_token ?: 'Ainda nao gerado. Ele sera criado automaticamente ao consultar o servidor.' }}
                </span>
            </div>
        </div>

        <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
            <span class="text-xs text-gray-500 font-medium italic">Salva automaticamente ao digitar.</span>
            <button wire:click="checkStatus" wire:loading.attr="disabled" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="checkStatus">🔄 Atualizar Status</span>
                <span wire:loading wire:target="checkStatus" class="flex items-center gap-2">
                    <svg class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.35 0 0 5.35 0 12h4zm13 0a8 8 0 01-8-8V0C15.65 0 20 5.35 20 12h-4z"></path></svg>
                    Verificando...
                </span>
            </button>
        </div>
    </div>

    <!-- Coluna do Stepper de Autenticação (Centro) -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-6">Fluxo de Autenticação</h3>

        <ol class="relative border-l border-gray-200">
            <!-- Passo 1: Configuração -->
            <li class="mb-10 ml-4">
                <div class="absolute w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center -left-3 border border-gray-300 {{ $connection_status !== 'error' ? 'bg-green-100 border-green-500 text-green-600' : '' }}">
                    @if($connection_status !== 'error')
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.//707-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                    @else
                        <span class="text-xs font-bold">1</span>
                    @endif
                </div>
                <h3 class="flex items-center mb-1 text-sm font-semibold {{ $connection_status !== 'error' ? 'text-green-600' : 'text-gray-900' }}">Configuração do Servidor</h3>
                <span class="text-xs text-gray-500">Defina a URL e o nome da sessão para iniciar a conexão.</span>
            </li>

            <!-- Passo 2: QR Code -->
            <li class="mb-10 ml-4">
                <div class="absolute w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center -left-3 border border-gray-300 {{ $connection_status === 'waiting_qr' || $connection_status === 'connected' ? 'bg-green-100 border-green-500 text-green-600' : '' }}">
                    @if($connection_status === 'waiting_qr' || $connection_status === 'connected')
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                    @else
                        <span class="text-xs font-bold">2</span>
                    @endif
                </div>
                <h3 class="flex items-center mb-1 text-sm font-semibold {{ $connection_status === 'waiting_qr' || $connection_status === 'connected' ? 'text-green-600' : 'text-gray-900' }}">Autenticação via QR Code</h3>
                <span class="text-xs text-gray-500">Gere o código e escaneie com seu aparelho celular.</span>
            </li>

            <!-- Passo 3: Conexão -->
            <li class="ml-4">
                <div class="absolute w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center -left-3 border border-gray-300 {{ $connection_status === 'connected' ? 'bg-green-100 border-green-500 text-green-600' : '' }}">
                    @if($connection_status === 'connected')
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                    @else
                        <span class="text-xs font-bold">3</span>
                    @endif
                </div>
                <h3 class="flex items-center mb-1 text-sm font-semibold {{ $connection_status === 'connected' ? 'text-green-600' : 'text-gray-900' }}">Conexão Estabelecida</h3>
                <span class="text-xs text-gray-500">Sessão ativa e pronta para processar mensagens.</span>
            </li>
        </ol>
    </div>

    <!-- Coluna de Status e QR Code (Direita) -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col items-center justify-center text-center space-y-6">
        <h3 class="text-lg font-bold text-gray-800 uppercase tracking-wide">Status da Conexão</h3>

        <div class="relative">
            @if($connection_status === 'connected')
                <div class="flex flex-col items-center gap-3">
                    <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                        {{-- <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13.1B19 3l-6 6m6-6-6 6"></path></svg> --}}
                        <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m10.6 16.6l7.05-7.05l-1.4-1.4l-5.65 5.65l-2.85-2.85l-1.4 1.4l4.25 4.25ZM12 22q-2.075 0-3.9-.788t-3.175-2.137q-1.35-1.35-2.137-3.175T2 12q0-2.075.788-3.9t2.137-3.175q1.35-1.35 3.175-2.137T12 2q2.075 0 3.9.788t3.175 2.137q1.35 1.35 2.138 3.175T22 12q0 2.075-.788 3.9t-2.137 3.175q-1.35 1.35-3.175 2.138T12 22Zm0-2q3.35 0 5.675-2.325T20 12q0-3.35-2.325-5.675T12 4Q8.65 4 6.325 6.325T4 12q0 3.35 2.325 5.675T12 20Zm0-8Z"/></svg>
                        </div>
                    <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full border border-green-200">CONECTADO</span>
                </div>
            @elseif($connection_status === 'waiting_qr')
                <div class="flex flex-col items-center gap-3">
                    <div class="w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill=""><path fill="" d="M2 7V2h5v2H4v3H2Zm0 15v-5h2v3h3v2H2Zm15 0v-2h3v-3h2v5h-5Zm3-15V4h-3V2h5v5h-2Zm-2.5 10.5H19V19h-1.5v-1.5Zm0-3H19V16h-1.5v-1.5ZM16 16h1.5v1.5H16V16Zm-1.5 1.5H16V19h-1.5v-1.5ZM13 16h1.5v1.5H13V16Zm3-3h1.5v1.5H16V13Zm-1.5 1.5H16V16h-1.5v-1.5ZM13 13h1.5v1.5H13V13Zm6-8v6h-6V5h6Zm-8 8v6H5v-6h6Zm0-8v6H5V5h6ZM9.5 17.5v-3h-3v3h3Zm0-8v-3h-3v3h3Zm8 0v-3h-3v3h3Z"/></svg>
                    </div>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-bold rounded-full border border-yellow-200">AGUARDANDO QR CODE</span>
                </div>
            @else
                <div class="flex flex-col items-center gap-3">
                    <div class="w-20 h-20 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="m17.1 14.275l-1.225-1.225q.55-.65.838-1.425T17 10q0-1-.4-1.9t-1.1-1.6l1.2-1.2q.95.95 1.475 2.15T18.7 10q0 1.2-.425 2.288T17.1 14.274ZM14.125 11.3L10.7 7.875q.3-.175.625-.275T12 7.5q1.05 0 1.775.725T14.5 10q0 .35-.1.675t-.275.625Zm5.375 5.35l-1.2-1.2q1-1.125 1.5-2.537T20.3 10q0-1.65-.613-3.188T17.9 4.1l1.2-1.2q1.375 1.45 2.138 3.275T22 10q0 1.85-.638 3.563T19.5 16.65Zm.275 5.95L13 15.825V21h-2v-7.175L7 9.85V10q0 1 .4 1.9t1.1 1.6l-1.2 1.2q-.95-.95-1.475-2.15T5.3 10q0-.425.05-.825t.175-.825L4.25 7.075q-.275.725-.413 1.45T3.7 10q0 1.65.612 3.188T6.1 15.9l-1.2 1.2q-1.375-1.45-2.138-3.275T2 10q0-1.1.238-2.163t.712-2.062L1.4 4.225L2.8 2.8l18.4 18.4l-1.425 1.4Z"/></svg>
                    </div>
                    <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full border border-red-200">DESCONECTADO</span>
                </div>
            @endif
        </div>

        @if($qr_code)
            <div class="mt-4 p-4 bg-white border-2 border-dashed border-gray-300 rounded-xl">
                <img src="{{ $qr_code }}" class="w-48 h-48 rounded shadow-sm" alt="WhatsApp QR Code">
                <p class="text-xs text-gray-500 mt-2">Escaneie com seu WhatsApp</p>
            </div>
        @endif

        @if($message)
            <div class="text-xs text-red-500 font-medium px-4 py-2 bg-red-50 rounded-lg border border-red-100">
                {{ $message }}
            </div>
        @endif

        <div class="flex gap-2 pt-4">
            <button wire:click="generateQRCode" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold transition">
                Gerar Novo QR Code
            </button>
            <button wire:click="logout" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold transition">
                Desconectar
            </button>
        </div>

        @if(count($debug_log))
            <div class="w-full text-left bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-2">
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide">Log de integracao</h4>
                @foreach($debug_log as $logLine)
                    <p class="text-xs text-gray-600 break-words">{{ $logLine }}</p>
                @endforeach
            </div>
        @endif
    </div>
</div>
