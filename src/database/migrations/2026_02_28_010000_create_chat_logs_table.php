<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create("chat_logs", function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId("telegraph_bot_id")
                ->constrained("telegraph_bots")
                ->cascadeOnDelete();
            $table
                ->foreignId("telegraph_chat_id")
                ->nullable()
                ->constrained("telegraph_chats")
                ->nullOnDelete();

            $table->string("direction", 10)->index(); // in|out
            $table->string("event_type", 32)->index(); // message|command|callback|response

            $table->unsignedBigInteger("update_id")->nullable()->index();
            $table->string("telegram_chat_id", 64)->nullable()->index();
            $table->string("telegram_user_id", 64)->nullable()->index();

            $table->string("command", 64)->nullable()->index();
            $table->string("callback_action", 128)->nullable()->index();
            $table->text("message_text")->nullable();
            $table->json("meta")->nullable();

            $table->timestamp("created_at")->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("chat_logs");
    }
};
