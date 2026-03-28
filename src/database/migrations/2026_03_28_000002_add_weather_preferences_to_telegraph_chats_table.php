<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table
                ->string("weather_response_mode")
                ->default("detailed")
                ->after("weather_city_lon");
            $table
                ->string("weather_units")
                ->default("metric")
                ->after("weather_response_mode");
            $table
                ->string("last_weather_query_type")
                ->nullable()
                ->after("weather_units");
            $table
                ->string("last_weather_query_city")
                ->nullable()
                ->after("last_weather_query_type");
            $table
                ->decimal("last_weather_query_lat", 10, 7)
                ->nullable()
                ->after("last_weather_query_city");
            $table
                ->decimal("last_weather_query_lon", 10, 7)
                ->nullable()
                ->after("last_weather_query_lat");
        });
    }

    public function down(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table->dropColumn([
                "weather_response_mode",
                "weather_units",
                "last_weather_query_type",
                "last_weather_query_city",
                "last_weather_query_lat",
                "last_weather_query_lon",
            ]);
        });
    }
};
