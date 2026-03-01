<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("bot_settings", function (Blueprint $table) {
            if (
                Schema::hasColumn("bot_settings", "volue") &&
                !Schema::hasColumn("bot_settings", "value")
            ) {
                $table->renameColumn("volue", "value");
            }
        });
    }

    public function down(): void
    {
        Schema::table("bot_settings", function (Blueprint $table) {
            if (
                Schema::hasColumn("bot_settings", "value") &&
                !Schema::hasColumn("bot_settings", "volue")
            ) {
                $table->renameColumn("value", "volue");
            }
        });
    }
};
