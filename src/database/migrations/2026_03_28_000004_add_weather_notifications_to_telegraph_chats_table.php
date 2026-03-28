<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table
                ->boolean("weather_notifications_enabled")
                ->default(false)
                ->after("weather_auto_save_location_city");
            $table
                ->string("weather_notification_time")
                ->nullable()
                ->after("weather_notifications_enabled");
            $table
                ->string("weather_notification_timezone")
                ->nullable()
                ->after("weather_notification_time");
            $table
                ->string("weather_notification_mode")
                ->default("brief")
                ->after("weather_notification_timezone");
            $table
                ->date("weather_last_notification_date")
                ->nullable()
                ->after("weather_notification_mode");
        });
    }

    public function down(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table->dropColumn([
                "weather_notifications_enabled",
                "weather_notification_time",
                "weather_notification_timezone",
                "weather_notification_mode",
                "weather_last_notification_date",
            ]);
        });
    }
};
