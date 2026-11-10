<?php

namespace Tests\Feature;

use App\Jobs\PrintOrderReceipt;
use App\Models\Customer;
use App\Models\LocalPrintJob;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalPrintQueueTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'printing.prefer_env' => true,
            'printing.local_api_token' => self::TOKEN,
            'printing.local_max_attempts' => 3,
            'printing.local_retry_after_seconds' => 30,
            'printing.local_claim_timeout_seconds' => 120,
            'printing.defaults.print_connection' => 'local',
            'printing.defaults.print_enabled' => 'true',
        ]);
    }

    private function makeOrder(): Order
    {
        $customer = Customer::create(['name' => 'Cliente', 'phone' => '5511' . random_int(10000000, 99999999)]);

        return Order::create([
            'customer_id' => $customer->id,
            'type' => 'counter',
            'status' => 'pending',
            'total_amount' => 10,
        ]);
    }

    private function agent(): static
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . self::TOKEN,
            'X-Agent-Name' => 'PC-CAIXA',
            'X-Agent-Version' => '2.0',
        ]);
    }

    public function test_rejects_requests_without_valid_token(): void
    {
        $this->getJson('/api/local-printer/jobs')->assertStatus(401);
        $this->withHeaders(['Authorization' => 'Bearer errado'])->getJson('/api/local-printer/jobs')->assertStatus(401);
    }

    public function test_auto_print_enqueues_once_per_order(): void
    {
        $order = $this->makeOrder();

        PrintOrderReceipt::dispatchSync($order->id);
        PrintOrderReceipt::dispatchSync($order->id);

        $this->assertSame(1, LocalPrintJob::where('order_id', $order->id)->count());
    }

    public function test_auto_print_respects_disabled_setting_but_reprint_does_not(): void
    {
        config(['printing.defaults.print_enabled' => 'false']);
        $order = $this->makeOrder();

        PrintOrderReceipt::dispatchSync($order->id);
        $this->assertSame(0, LocalPrintJob::count());

        PrintOrderReceipt::dispatchSync($order->id, true);
        $this->assertSame(1, LocalPrintJob::count());
    }

    public function test_poll_claims_jobs_so_they_are_not_delivered_twice(): void
    {
        $order = $this->makeOrder();
        LocalPrintJob::createIfNotExists($order->id);

        $this->agent()->getJson('/api/local-printer/jobs')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.order.id', $order->id);

        $this->assertSame(LocalPrintJob::STATUS_PRINTING, LocalPrintJob::first()->status);

        // Segunda consulta (ou segundo agente) nao recebe o mesmo job.
        $this->agent()->getJson('/api/local-printer/jobs')->assertOk()->assertJsonCount(0);
    }

    public function test_stale_claim_returns_to_queue(): void
    {
        $order = $this->makeOrder();
        $job = LocalPrintJob::createIfNotExists($order->id);
        $job->update(['status' => LocalPrintJob::STATUS_PRINTING, 'claimed_at' => now()->subMinutes(5)]);

        $this->agent()->getJson('/api/local-printer/jobs')->assertOk()->assertJsonCount(1);
    }

    public function test_complete_marks_printed_and_is_idempotent(): void
    {
        $job = LocalPrintJob::createIfNotExists($this->makeOrder()->id);

        $this->agent()->postJson("/api/local-printer/jobs/{$job->id}/complete")->assertOk();
        $this->agent()->postJson("/api/local-printer/jobs/{$job->id}/complete")->assertOk();
        // Uma falha atrasada nao desfaz um cupom ja impresso.
        $this->agent()->postJson("/api/local-printer/jobs/{$job->id}/fail", ['error_message' => 'x'])->assertOk();

        $job->refresh();
        $this->assertSame(LocalPrintJob::STATUS_PRINTED, $job->status);
        $this->assertNotNull($job->printed_at);
        $this->assertSame(0, $job->attempts);
    }

    public function test_failures_retry_until_exhausted(): void
    {
        $job = LocalPrintJob::createIfNotExists($this->makeOrder()->id);

        for ($i = 1; $i <= 3; $i++) {
            $this->agent()->postJson("/api/local-printer/jobs/{$job->id}/fail", ['error_message' => 'Connection refused'])->assertOk();
            // Logo apos falhar, espera o intervalo de retry.
            $this->assertSame(0, LocalPrintJob::pollable()->count());
            $job->refresh()->update(['last_attempted_at' => now()->subMinute()]);
        }

        $job->refresh();
        $this->assertSame(3, $job->attempts);
        $this->assertTrue($job->isExhausted());
        $this->assertSame(0, LocalPrintJob::pollable()->count());
        $this->assertSame(1, LocalPrintJob::exhausted()->count());
        $this->assertSame('Connection refused', $job->error_message);
    }

    public function test_reprint_creates_new_job_but_not_while_one_is_open(): void
    {
        $order = $this->makeOrder();
        $first = LocalPrintJob::createIfNotExists($order->id);
        $first->markPrinted();

        $reprint = LocalPrintJob::enqueueReprint($order->id);
        $again = LocalPrintJob::enqueueReprint($order->id);

        $this->assertNotSame($first->id, $reprint->id);
        $this->assertSame($reprint->id, $again->id);
        $this->assertSame(LocalPrintJob::REASON_REPRINT, $reprint->reason);
        $this->assertSame(2, LocalPrintJob::where('order_id', $order->id)->count());
    }

    public function test_poll_records_agent_heartbeat(): void
    {
        $this->assertFalse(LocalPrintJob::agentStatus()['online']);

        $this->agent()->getJson('/api/local-printer/jobs')->assertOk();

        $status = LocalPrintJob::agentStatus();
        $this->assertTrue($status['online']);
        $this->assertSame('PC-CAIXA', $status['agent']);
        $this->assertSame('2.0', $status['version']);

        $this->travel(5)->minutes();
        $this->assertFalse(LocalPrintJob::agentStatus()['online']);
    }

    public function test_panel_pages_render_and_reprint_button_enqueues(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder();
        $job = LocalPrintJob::createIfNotExists($order->id);
        $job->update(['status' => LocalPrintJob::STATUS_FAILED, 'attempts' => 3, 'error_message' => 'Impressora desligada']);

        $this->actingAs($user)->get(route('printing.index'))
            ->assertOk()
            ->assertSee('Impressora desligada')
            ->assertSee('Tentar de novo')
            ->assertSee('Impressora nunca conectou');

        $this->actingAs($user)->post(route('orders.reprint', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(1, LocalPrintJob::where('order_id', $order->id)->where('reason', 'reprint')->count());
    }
}
