<div wire:poll.10s class="space-y-6">
    @if(!$localMode)
        <div class="p-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 text-sm">
            A impressao esta configurada como <strong>{{ $connection ?: 'nao definida' }}</strong>.
            Esta fila so e usada quando <code>PRINT_CONNECTION=local</code> no servidor.
        </div>
    @endif

    @if(session('print_message'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
            {{ session('print_message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 border-l-4 {{ $status['online'] ? 'border-l-emerald-500' : 'border-l-red-500' }}">
            <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Agente local</div>
            <div class="text-2xl font-bold mt-1 {{ $status['online'] ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $status['online'] ? 'Online' : 'Offline' }}
            </div>
            <div class="text-xs text-gray-500 mt-1">
                @if($status['seen_at'])
                    Ultimo contato {{ $status['seen_at']->diffForHumans() }}
                    @if($status['agent']) &middot; {{ $status['agent'] }} @endif
                    @if($status['version']) &middot; v{{ $status['version'] }} @endif
                @else
                    O agente ainda nao se conectou a este servidor.
                @endif
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 border-l-4 {{ $failedCount ? 'border-l-red-500' : 'border-l-gray-300' }}">
            <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Precisam de atencao</div>
            <div class="text-2xl font-bold mt-1 {{ $failedCount ? 'text-red-600' : 'text-gray-800' }}">{{ $failedCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Falharam {{ $maxAttempts }} vezes e nao serao tentados de novo sozinhos.</div>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 border-l-4 border-l-blue-500 text-sm text-gray-600">
            <div class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-1">Se estiver offline</div>
            Verifique se o computador do restaurante esta ligado, com internet, e se o agente esta rodando
            (<code class="text-xs">storage\logs\agente-impressao.log</code>).
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h2 class="font-semibold text-gray-800">Ultimas impressoes</h2>
            <select wire:model.live="filter" class="text-sm border-gray-300 rounded-lg">
                <option value="all">Todas</option>
                <option value="queued">Na fila</option>
                <option value="failed">Com falha</option>
            </select>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="text-left p-3">Pedido</th>
                    <th class="text-left p-3">Situacao</th>
                    <th class="text-left p-3">Tipo</th>
                    <th class="text-left p-3">Tentativas</th>
                    <th class="text-left p-3">Atualizado</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($jobs as $job)
                    @php
                        $badge = match (true) {
                            $job->status === 'printed' => ['Impresso', 'bg-emerald-100 text-emerald-700'],
                            $job->status === 'printing' => ['Imprimindo', 'bg-blue-100 text-blue-700'],
                            $job->status === 'pending' => ['Na fila', 'bg-gray-100 text-gray-700'],
                            $job->isExhausted() => ['Falhou', 'bg-red-100 text-red-700'],
                            default => ['Tentando de novo', 'bg-amber-100 text-amber-700'],
                        };
                    @endphp
                    <tr class="{{ $job->isExhausted() ? 'bg-red-50/50' : '' }}">
                        <td class="p-3">
                            <a href="{{ route('orders.show', $job->order_id) }}" class="text-blue-600 font-medium hover:underline">#{{ $job->order_id }}</a>
                            <div class="text-xs text-gray-500">{{ $job->order?->customer?->name }}</div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                            @if($job->error_message && $job->status === 'failed')
                                <div class="text-xs text-red-600 mt-1 max-w-xs truncate" title="{{ $job->error_message }}">{{ $job->error_message }}</div>
                            @endif
                        </td>
                        <td class="p-3 text-gray-600">{{ $job->reason === 'reprint' ? 'Reimpressao' : 'Automatica' }}</td>
                        <td class="p-3 text-gray-600">{{ $job->attempts }}</td>
                        <td class="p-3 text-gray-600">{{ $job->updated_at?->format('d/m H:i:s') }}</td>
                        <td class="p-3 text-right">
                            @if(in_array($job->status, ['failed', 'printed']))
                                <button wire:click="retry({{ $job->id }})" wire:loading.attr="disabled"
                                    class="px-3 py-1.5 rounded-lg text-xs font-bold {{ $job->status === 'failed' ? 'bg-red-600 text-white hover:bg-red-500' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                                    {{ $job->status === 'failed' ? 'Tentar de novo' : 'Reimprimir' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">Nenhuma impressao registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
