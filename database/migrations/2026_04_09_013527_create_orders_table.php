<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('counter'); // counter, delivery, table
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('address_id')->nullable()->constrained();
            $table->string('status')->default('pending'); // pending, preparing, shipped, delivered, cancelled
            $table->decimal('total_amount', 10, 2);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
