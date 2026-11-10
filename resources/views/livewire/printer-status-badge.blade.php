<div wire:poll.20s>
    @if($enabled)
        <a href="{{ route('printing.index') }}" class="flex items-center gap-2 text-sm px-3 py-1.5 rounded-full border transition
            {{ $status['online'] ? 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100' : 'bg-red-50 border-red-200 text-red-700 hover:bg-red-100' }}"
            title="{{ $status['seen_at'] ? 'Ultimo contato: ' . $status['seen_at']->format('d/m H:i:s') : 'O agente ainda nao se conectou' }}">
            <span class="relative flex h-2.5 w-2.5">
                @if($status['online'])
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                @endif
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $status['online'] ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
            </span>
            <span class="font-medium">
                @if($status['online'])
                    Impressora online
                @elseif($status['seen_at'])
                    Impressora offline ha {{ $status['seen_at']->diffForHumans(null, true) }}
                @else
                    Impressora nunca conectou
                @endif
            </span>
            @if($queuedCount > 0)
                <span class="text-xs px-2 py-0.5 rounded-full bg-white/70">{{ $queuedCount }} na fila</span>
            @endif
            @if($failedCount > 0)
                <span class="text-xs px-2 py-0.5 rounded-full bg-red-600 text-white font-bold">{{ $failedCount }} com falha</span>
            @endif
        </a>
    @endif
</div>
