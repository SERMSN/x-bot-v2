<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create("subscription_plans", function (Blueprint $table) {
            $table->id();
            $table
                ->foreignId("telegraph_bot_id")
                ->nullable()
                ->constrained("telegraph_bots")
                ->cascadeOnDelete();
            $table->string("name");
            $table->unsignedInteger("report_count");
            $table->decimal("price_rub", 10, 2);
            $table->text("description")->nullable();
            $table->boolean("is_active")->default(true);
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();

            $table->index(["telegraph_bot_id", "is_active", "sort_order"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("subscription_plans");
    }
};
