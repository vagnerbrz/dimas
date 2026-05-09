@extends('layouts.app')

@section('header', 'Atendimento Humano')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Fila de atendimento</h2>
                        <p class="text-sm text-slate-500">Conversas escaladas pelo bot ou pela equipe.</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">
                        {{ $conversations->total() }} conversa(s)
                    </span>
                </div>

                <form method="GET" action="{{ route('human-conversations.index') }}" class="mt-4 space-y-3">
                    <input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Buscar por nome ou telefone"
                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                    >
                    <div class="grid grid-cols-2 gap-3">
                        <select
                            name="status"
                            class="rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >
                            <option value="">Abertas e em andamento</option>
                            <option value="open" @selected($filters['status'] === 'open')>Abertas</option>
                            <option value="in_progress" @selected($filters['status'] === 'in_progress')>Em andamento</option>
                            <option value="closed" @selected($filters['status'] === 'closed')>Encerradas</option>
                        </select>
                        <button class="rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <div class="max-h-[70vh] overflow-y-auto">
                @forelse($conversations as $conversation)
                    @php
                        $isSelected = $selectedConversation && $selectedConversation->id === $conversation->id;
                        $statusClasses = [
                            'open' => 'bg-amber-100 text-amber-800',
                            'in_progress' => 'bg-blue-100 text-blue-800',
                            'closed' => 'bg-slate-200 text-slate-700',
                        ];
                        $statusLabels = [
                            'open' => 'Aberta',
                            'in_progress' => 'Em andamento',
                            'closed' => 'Encerrada',
                        ];
                    @endphp
                    <a
                        href="{{ route('human-conversations.show', ['humanConversation' => $conversation->id, 'status' => $filters['status'], 'search' => $filters['search']]) }}"
                        class="block border-b border-slate-100 px-5 py-4 transition {{ $isSelected ? 'bg-blue-50' : 'hover:bg-slate-50' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">
                                    {{ $conversation->customer->name ?? $conversation->contact_name ?? 'Cliente WhatsApp' }}
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ $conversation->phone }}</div>
                                <div class="mt-2 truncate text-xs text-slate-400">
                                    {{ $conversation->assignedUser?->name ? 'Atendente: ' . $conversation->assignedUser->name : 'Aguardando atendente' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusClasses[$conversation->status] ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ $statusLabels[$conversation->status] ?? $conversation->status }}
                                </span>
                                <div class="mt-2 text-[11px] text-slate-400">
                                    {{ optional($conversation->last_message_at)->format('d/m H:i') ?? '--' }}
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">
                        Nenhuma conversa encontrada para os filtros atuais.
                    </div>
                @endforelse
            </div>

            <div class="border-t border-slate-100 p-4">
                {{ $conversations->links() }}
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            @if($selectedConversation)
                @php
                    $statusLabels = [
                        'open' => 'Aberta',
                        'in_progress' => 'Em andamento',
                        'closed' => 'Encerrada',
                    ];
                @endphp
                <div class="border-b border-slate-100 bg-slate-50 px-6 py-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">
                                {{ $selectedConversation->customer->name ?? $selectedConversation->contact_name ?? 'Cliente WhatsApp' }}
                            </h2>
                            <div class="mt-1 text-sm text-slate-500">
                                {{ $selectedConversation->phone }} • {{ $statusLabels[$selectedConversation->status] ?? $selectedConversation->status }}
                            </div>
                            <div class="mt-2 text-xs text-slate-400">
                                {{ $selectedConversation->assignedUser?->name ? 'Atendente atual: ' . $selectedConversation->assignedUser->name : 'Sem atendente responsavel' }}
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @if($selectedConversation->status !== 'closed')
                                <form method="POST" action="{{ route('human-conversations.assign', $selectedConversation) }}">
                                    @csrf
                                    <button class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
                                        Assumir atendimento
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('human-conversations.close', $selectedConversation) }}">
                                    @csrf
                                    <input type="hidden" name="release_bot" value="1">
                                    <button class="rounded-2xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100">
                                        Encerrar atendimento
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('human-conversations.reopen', $selectedConversation) }}">
                                    @csrf
                                    <button class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                        Reabrir conversa
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <div id="conversation-thread" class="flex h-[60vh] flex-col gap-4 overflow-y-auto bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.08),_transparent_45%),linear-gradient(180deg,#f8fafc_0%,#eef2ff_100%)] px-6 py-6">
                    @forelse($selectedConversation->messages as $message)
                        @php
                            $isInbound = $message->direction === 'inbound';
                            $isSystem = $message->direction === 'system';
                        @endphp

                        @if($isSystem)
                            <div class="mx-auto max-w-xl rounded-full bg-slate-900 px-4 py-2 text-center text-xs font-medium text-white/90 shadow-sm">
                                {{ $message->body }}
                            </div>
                        @else
                            <div class="flex {{ $isInbound ? 'justify-start' : 'justify-end' }}">
                                <div class="max-w-2xl rounded-3xl px-4 py-3 shadow-sm {{ $isInbound ? 'bg-white text-slate-800' : 'bg-emerald-500 text-white' }}">
                                    <div class="whitespace-pre-wrap text-sm leading-6">{{ $message->body }}</div>
                                    <div class="mt-2 text-[11px] {{ $isInbound ? 'text-slate-400' : 'text-emerald-50' }}">
                                        {{ optional($message->sent_at ?? $message->created_at)->format('d/m H:i') }}
                                        @if(!$isInbound && $message->senderUser)
                                            • {{ $message->senderUser->name }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="mx-auto max-w-md rounded-3xl border border-dashed border-slate-300 bg-white/80 px-6 py-8 text-center text-sm text-slate-500">
                            Essa conversa ainda nao tem mensagens registradas.
                        </div>
                    @endforelse
                </div>

                @if($selectedConversation->status !== 'closed')
                    <div class="border-t border-slate-100 p-5">
                        <form method="POST" action="{{ route('human-conversations.messages.store', $selectedConversation) }}" class="space-y-3">
                            @csrf
                            <textarea
                                name="message"
                                rows="4"
                                placeholder="Digite a resposta para o cliente..."
                                class="w-full rounded-3xl border border-slate-200 px-4 py-4 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            >{{ old('message') }}</textarea>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs text-slate-400">
                                    As mensagens enviadas por aqui saem pelo WhatsApp e mantem o bot suspenso para esse cliente.
                                </p>
                                <button class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    Enviar mensagem
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            @else
                <div class="flex h-[70vh] items-center justify-center bg-[linear-gradient(180deg,#f8fafc_0%,#eff6ff_100%)] px-6">
                    <div class="max-w-md rounded-3xl border border-slate-200 bg-white px-8 py-10 text-center shadow-sm">
                        <h2 class="text-xl font-semibold text-slate-900">Nenhuma conversa selecionada</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-500">
                            Assim que o n8n escalar um atendimento ou um cliente entrar em atendimento humano, a conversa aparece aqui.
                        </p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const thread = document.getElementById('conversation-thread');

        if (thread) {
            thread.scrollTop = thread.scrollHeight;
        }

        window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            window.location.reload();
        }, 8000);
    })();
</script>
@endpush
