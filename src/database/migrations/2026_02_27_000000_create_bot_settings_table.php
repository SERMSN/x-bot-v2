<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->foreignId('telegraph_bot_id')
                ->constrained('telegraph_bots')
                ->cascadeOnDelete();
            $table->string('volue');
            $table->timestamps();

            $table->unique(['telegraph_bot_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
