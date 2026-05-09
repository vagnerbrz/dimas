<?php

namespace App\Jobs;

use App\Services\WppConnectService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $recipient,
        public string $message
    ) {
    }

    public function handle(WppConnectService $wppConnect): void
    {
        $wppConnect->sendMessage($this->recipient, $this->message);
    }
}
