<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_print_jobs', function (Blueprint $table) {
            // Indice simples antes de remover o unico, para a FK continuar coberta.
            $table->index('order_id', 'local_print_jobs_order_id_index');
        });

        Schema::table('local_print_jobs', function (Blueprint $table) {
            // Permite varios jobs por pedido (reimpressao).
            $table->dropUnique(['order_id']);
            $table->string('reason')->default('auto')->after('status');
            $table->timestamp('claimed_at')->nullable()->after('attempts');
            $table->timestamp('printed_at')->nullable()->after('last_attempted_at');
            $table->string('agent_name')->nullable()->after('printed_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('local_print_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['reason', 'claimed_at', 'printed_at', 'agent_name']);
            $table->unique('order_id');
        });

        Schema::table('local_print_jobs', function (Blueprint $table) {
            $table->dropIndex('local_print_jobs_order_id_index');
        });
    }
};
