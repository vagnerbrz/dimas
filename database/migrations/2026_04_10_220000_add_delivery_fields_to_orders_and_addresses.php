<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('reference');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('total_amount');
            $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'delivery_distance_km']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
