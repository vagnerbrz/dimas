<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LocalPrintJob extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PRINTING = 'printing';
    public const STATUS_PRINTED = 'printed';
    public const STATUS_FAILED = 'failed';

    public const REASON_AUTO = 'auto';
    public const REASON_REPRINT = 'reprint';

    /** Chave em settings onde o agente registra o ultimo contato. */
    public const HEARTBEAT_SETTING = 'local_printer_heartbeat';

    protected $fillable = [
        'order_id',
        'status',
        'reason',
        'attempts',
        'claimed_at',
        'last_attempted_at',
        'printed_at',
        'agent_name',
        'error_message',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'claimed_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'printed_at' => 'datetime',
    ];

    /**
     * Impressao automatica: so cria se o pedido ainda nao tem nenhum job,
     * evitando cupom duplicado quando o dispatch acontece mais de uma vez.
     */
    public static function createIfNotExists(int $orderId): self
    {
        return self::firstOrCreate(
            ['order_id' => $orderId],
            ['status' => self::STATUS_PENDING, 'reason' => self::REASON_AUTO, 'attempts' => 0]
        );
    }

    /**
     * Reimpressao manual: cria um novo job, a menos que ja exista um
     * aguardando/imprimindo para o mesmo pedido (evita clique duplo).
     */
    public static function enqueueReprint(int $orderId): self
    {
        $open = self::where('order_id', $orderId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PRINTING])
            ->latest('id')
            ->first();

        return $open ?? self::create([
            'order_id' => $orderId,
            'status' => self::STATUS_PENDING,
            'reason' => self::REASON_REPRINT,
            'attempts' => 0,
        ]);
    }

    /**
     * Reserva atomicamente os proximos jobs para o agente, marcando-os como
     * "printing". Consultas simultaneas nao recebem os mesmos jobs.
     */
    public static function claimNext(int $limit, ?string $agentName = null): Collection
    {
        return DB::transaction(function () use ($limit, $agentName) {
            $ids = self::pollable()
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return new Collection();
            }

            self::whereIn('id', $ids)->update([
                'status' => self::STATUS_PRINTING,
                'claimed_at' => now(),
                'agent_name' => $agentName,
                'updated_at' => now(),
            ]);

            return self::whereIn('id', $ids)
                ->with(['order.customer', 'order.address', 'order.items.product'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        });
    }

    public function markPrinted(): void
    {
        $this->update([
            'status' => self::STATUS_PRINTED,
            'printed_at' => now(),
            'last_attempted_at' => now(),
            'claimed_at' => null,
            'error_message' => null,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'attempts' => $this->attempts + 1,
            'last_attempted_at' => now(),
            'claimed_at' => null,
            'error_message' => mb_substr($message, 0, 2000),
        ]);
    }

    /** Falhou e nao sera mais tentado automaticamente. */
    public function isExhausted(): bool
    {
        return $this->status === self::STATUS_FAILED
            && $this->attempts >= self::maxAttempts();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** Falhas que esgotaram as tentativas automaticas. */
    public function scopeExhausted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('attempts', '>=', self::maxAttempts());
    }

    public function scopePollable(Builder $query): Builder
    {
        $maxAttempts = self::maxAttempts();
        $retryAfter = now()->subSeconds(max(1, (int) config('printing.local_retry_after_seconds', 30)));
        $claimExpired = now()->subSeconds(max(10, (int) config('printing.local_claim_timeout_seconds', 120)));

        return $query->where(function (Builder $query) use ($maxAttempts, $retryAfter, $claimExpired) {
            $query->where('status', self::STATUS_PENDING)
                ->orWhere(function (Builder $query) use ($maxAttempts, $retryAfter) {
                    $query->where('status', self::STATUS_FAILED)
                        ->where('attempts', '<', $maxAttempts)
                        ->where(function (Builder $query) use ($retryAfter) {
                            $query->whereNull('last_attempted_at')
                                ->orWhere('last_attempted_at', '<=', $retryAfter);
                        });
                })
                // Agente caiu no meio da impressao: devolve o job para a fila.
                ->orWhere(function (Builder $query) use ($claimExpired) {
                    $query->where('status', self::STATUS_PRINTING)
                        ->where('claimed_at', '<=', $claimExpired);
                });
        });
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('printing.local_max_attempts', 10));
    }

    /** Registra que o agente local falou com o servidor agora. */
    public static function recordHeartbeat(?string $agentName, ?string $agentVersion): void
    {
        Setting::set(self::HEARTBEAT_SETTING, json_encode([
            'seen_at' => now()->toIso8601String(),
            'agent' => $agentName,
            'version' => $agentVersion,
        ]));
    }

    /**
     * @return array{online: bool, seen_at: ?Carbon, agent: ?string, version: ?string}
     */
    public static function agentStatus(): array
    {
        $data = json_decode((string) Setting::get(self::HEARTBEAT_SETTING, ''), true) ?: [];
        $seenAt = !empty($data['seen_at']) ? Carbon::parse($data['seen_at']) : null;
        $offlineAfter = max(15, (int) config('printing.local_offline_after_seconds', 60));

        return [
            'online' => $seenAt !== null && $seenAt->gt(now()->subSeconds($offlineAfter)),
            'seen_at' => $seenAt,
            'agent' => $data['agent'] ?? null,
            'version' => $data['version'] ?? null,
        ];
    }
}
