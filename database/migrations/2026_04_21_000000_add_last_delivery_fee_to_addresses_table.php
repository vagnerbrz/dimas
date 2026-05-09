<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->decimal('last_delivery_fee', 10, 2)->nullable()->after('longitude');
            $table->timestamp('last_delivery_fee_updated_at')->nullable()->after('last_delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['last_delivery_fee', 'last_delivery_fee_updated_at']);
        });
    }
};