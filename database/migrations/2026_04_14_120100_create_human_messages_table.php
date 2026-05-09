<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('human_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 20)->index();
            $table->string('message_type', 30)->default('text');
            $table->text('body')->nullable();
            $table->string('whatsapp_message_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_messages');
    }
};
