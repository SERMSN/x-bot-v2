<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegraph_bots', function (Blueprint $table) {
            // Добавляем поле для хранения класса хендлера
            $table->string('handler_class')->nullable()->after('token');
            $table->string('webhook_url')->nullable()->after('handler_class');
            $table->json('settings')->nullable()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('telegraph_bots', function (Blueprint $table) {
            $table->dropColumn(['handler_class', 'webhook_url', 'settings']);
        });
    }
};