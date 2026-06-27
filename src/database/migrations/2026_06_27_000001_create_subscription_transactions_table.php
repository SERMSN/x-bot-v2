<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create("subscription_transactions", function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId("telegraph_bot_id")
                ->nullable()
                ->constrained("telegraph_bots")
                ->cascadeOnDelete();
            $table
                ->foreignId("telegraph_chat_id")
                ->nullable()
                ->constrained("telegraph_chats")
                ->nullOnDelete();
            $table
                ->foreignId("subscription_plan_id")
                ->nullable()
                ->constrained("subscription_plans")
                ->nullOnDelete();
            $table->string("transaction_type", 32);
            $table->unsignedInteger("reports_before")->default(0);
            $table->unsignedInteger("reports_delta")->default(0);
            $table->unsignedInteger("reports_after")->default(0);
            $table->decimal("amount_rub", 10, 2)->nullable();
            $table->string("status", 32)->default("done");
            $table->text("comment")->nullable();
            $table->json("meta")->nullable();
            $table->timestamps();

            $table->index(
                [
                    "telegraph_bot_id",
                    "telegraph_chat_id",
                    "transaction_type",
                ],
                "sub_tx_bot_chat_type_idx",
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("subscription_transactions");
    }
};
