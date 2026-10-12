<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table
                ->boolean("weather_precipitation_alerts_enabled")
                ->default(false)
                ->after("weather_last_notification_date");
            $table
                ->unsignedSmallInteger("weather_precipitation_alert_lead_minutes")
                ->default(60)
                ->after("weather_precipitation_alerts_enabled");
            $table
                ->unsignedBigInteger("weather_last_precipitation_alert_timestamp")
                ->nullable()
                ->after("weather_precipitation_alert_lead_minutes");
        });
    }

    public function down(): void
    {
        Schema::table("telegraph_chats", function (Blueprint $table) {
            $table->dropColumn([
                "weather_precipitation_alerts_enabled",
                "weather_precipitation_alert_lead_minutes",
                "weather_last_precipitation_alert_timestamp",
            ]);
        });
    }
};