<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table
                ->boolean("weather_auto_save_location_city")
                ->default(true)
                ->after("weather_units");
        });
    }

    public function down(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table->dropColumn("weather_auto_save_location_city");
        });
    }
};
