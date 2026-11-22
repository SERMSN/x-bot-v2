<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("psychologist_profiles", function (Blueprint $table) {
            $table->id();
            $table->foreignId("telegraph_bot_id")
                ->constrained("telegraph_bots")
                ->cascadeOnDelete();
            $table->foreignId("telegraph_chat_id")
                ->constrained("telegraph_chats")
                ->cascadeOnDelete();
            $table->string("preferred_name")->nullable();
            $table->string("style", 32)->default("warm");
            $table->timestamp("consent_at")->nullable();
            $table->timestamp("last_active_at")->nullable();
            $table->timestamps();

            $table->unique(
                ["telegraph_bot_id", "telegraph_chat_id"],
                "psychologist_profile_bot_chat_unique",
            );
        });

        Schema::create("psychologist_memories", function (Blueprint $table) {
            $table->id();
            $table->foreignId("psychologist_profile_id")
                ->unique()
                ->constrained("psychologist_profiles")
                ->cascadeOnDelete();
            $table->longText("summary")->nullable();
            $table->text("goals")->nullable();
            $table->text("topics")->nullable();
            $table->timestamp("last_summarized_at")->nullable();
            $table->timestamps();
        });

        Schema::create("psychologist_sessions", function (Blueprint $table) {
            $table->id();
            $table->foreignId("psychologist_profile_id")
                ->constrained("psychologist_profiles")
                ->cascadeOnDelete();
            $table->timestamp("started_at")->nullable();
            $table->timestamp("ended_at")->nullable();
            $table->unsignedInteger("message_count")->default(0);
            $table->unsignedInteger("user_messages")->default(0);
            $table->unsignedInteger("assistant_messages")->default(0);
            $table->unsignedBigInteger("input_tokens")->default(0);
            $table->unsignedBigInteger("output_tokens")->default(0);
            $table->unsignedInteger("safety_flags")->default(0);
            $table->string("status", 32)->default("active");
            $table->timestamps();

            $table->index(
                ["psychologist_profile_id", "status"],
                "psychologist_session_profile_status_idx",
            );
        });

        Schema::create("psychologist_mood_entries", function (Blueprint $table) {
            $table->id();
            $table->foreignId("psychologist_profile_id")
                ->constrained("psychologist_profiles")
                ->cascadeOnDelete();
            $table->unsignedTinyInteger("score");
            $table->json("tags")->nullable();
            $table->timestamps();

            $table->index(
                ["psychologist_profile_id", "created_at"],
                "psychologist_mood_profile_created_idx",
            );
        });

        Schema::create("psychologist_events", function (Blueprint $table) {
            $table->id();
            $table->foreignId("psychologist_profile_id")
                ->nullable()
                ->constrained("psychologist_profiles")
                ->nullOnDelete();
            $table->string("event_type", 64);
            $table->json("meta")->nullable();
            $table->timestamps();

            $table->index(
                ["event_type", "created_at"],
                "psychologist_event_type_created_idx",
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("psychologist_events");
        Schema::dropIfExists("psychologist_mood_entries");
        Schema::dropIfExists("psychologist_sessions");
        Schema::dropIfExists("psychologist_memories");
        Schema::dropIfExists("psychologist_profiles");
    }
};
