<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table->decimal("weather_city_lat", 10, 7)->nullable()->after("weather_city");
            $table->decimal("weather_city_lon", 10, 7)->nullable()->after("weather_city_lat");
        });
    }

    public function down(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table->dropColumn(["weather_city_lat", "weather_city_lon"]);
        });
    }
};
