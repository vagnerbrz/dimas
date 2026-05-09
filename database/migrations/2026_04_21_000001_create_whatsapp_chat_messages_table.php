<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->text('message');
            $table->string('contact_name')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->boolean('is_from_customer')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at']);
            $table->index(['is_from_customer', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_messages');
    }
};