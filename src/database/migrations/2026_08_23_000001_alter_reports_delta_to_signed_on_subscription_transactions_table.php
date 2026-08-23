<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("subscription_transactions", function (Blueprint $table) {
            $table->integer("reports_delta")->change();
        });
    }

    public function down(): void
    {
        Schema::table("subscription_transactions", function (Blueprint $table) {
            $table->unsignedInteger("reports_delta")->change();
        });
    }
};
