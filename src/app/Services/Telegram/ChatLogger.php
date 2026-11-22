<?php

namespace App\Services\Telegram;

use App\Models\ChatLog;
use App\Models\TelegraphChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatLogger
{
    public function logInbound(
        Request $request,
        int $botId,
        ?string $handlerKey = null,
    ): void {
        try {
            $isSensitive = $handlerKey === "psychologist";
            $updateId = $this->toInt($request->input("update_id"));
            $telegramChatId =
                $this->toString($request->input("message.chat.id")) ??
                $this->toString($request->input("callback_query.message.chat.id"));
            $telegramUserId =
                $this->toString($request->input("message.from.id")) ??
                $this->toString($request->input("callback_query.from.id"));

            $chatModelId = $this->resolveChatModelId($botId, $telegramChatId);

            $rawMessageText = $this->toString($request->input("message.text"));
            $messageText = $isSensitive ? null : $rawMessageText;
            $command = $this->extractCommand($rawMessageText);
            $callbackAction = $this->extractCallbackAction(
                $request->input("callback_query.data"),
            );

            $eventType = "update";
            if ($callbackAction !== null) {
                $eventType = "callback";
            } elseif ($command !== null) {
                $eventType = "command";
            } elseif ($rawMessageText !== null || $request->has("message.location")) {
                $eventType = "message";
            }

            $meta = [
                "has_location" => !$isSensitive && $request->has("message.location"),
                "callback_query_id" => $this->toString(
                    $request->input("callback_query.id"),
                ),
                "sensitive" => $isSensitive,
                "text_length" => $rawMessageText !== null
                    ? mb_strlen($rawMessageText)
                    : null,
            ];

            if (!$isSensitive && $request->has("message.location")) {
                $meta["location"] = [
                    "latitude" => $request->input("message.location.latitude"),
                    "longitude" => $request->input("message.location.longitude"),
                ];
            }

            ChatLog::query()->create([
                "telegraph_bot_id" => $botId,
                "telegraph_chat_id" => $chatModelId,
                "direction" => "in",
                "event_type" => $eventType,
                "update_id" => $updateId,
                "telegram_chat_id" => $telegramChatId,
                "telegram_user_id" => $telegramUserId,
                "command" => $command,
                "callback_action" => $callbackAction,
                "message_text" => $messageText,
                "meta" => $meta,
            ]);
        } catch (\Throwable $e) {
            Log::warning("ChatLogger inbound failed", [
                "bot_id" => $botId,
                "error" => $e->getMessage(),
            ]);
        }
    }

    public function logOutbound(
        int $botId,
        ?int $chatModelId,
        ?string $telegramChatId,
        ?string $messageText,
        array $meta = [],
        bool $storeText = true,
    ): void {
        try {
            $textLength = $messageText !== null ? mb_strlen($messageText) : null;
            if (!$storeText) {
                $messageText = null;
                $meta["text_length"] = $textLength;
                $meta["sensitive"] = true;
            }

            ChatLog::query()->create([
                "telegraph_bot_id" => $botId,
                "telegraph_chat_id" => $chatModelId,
                "direction" => "out",
                "event_type" => "response",
                "telegram_chat_id" => $telegramChatId,
                "message_text" => $messageText,
                "meta" => $meta,
            ]);
        } catch (\Throwable $e) {
            Log::warning("ChatLogger outbound failed", [
                "bot_id" => $botId,
                "chat_id" => $chatModelId,
                "error" => $e->getMessage(),
            ]);
        }
    }

    private function extractCommand(?string $messageText): ?string
    {
        if (!$messageText) {
            return null;
        }

        $trimmed = trim($messageText);
        if (!str_starts_with($trimmed, "/")) {
            return null;
        }

        $firstToken = explode(" ", $trimmed)[0] ?? "";
        if ($firstToken === "") {
            return null;
        }

        return mb_strtolower($firstToken);
    }

    private function extractCallbackAction(mixed $data): ?string
    {
        if (is_array($data) && isset($data["action"])) {
            return (string) $data["action"];
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded) && isset($decoded["action"])) {
                return (string) $decoded["action"];
            }

            return trim($data) !== "" ? trim($data) : null;
        }

        if (is_object($data) && method_exists($data, "get")) {
            try {
                $action = $data->get("action");
                if (is_string($action) && $action !== "") {
                    return $action;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function resolveChatModelId(int $botId, ?string $telegramChatId): ?int
    {
        if ($telegramChatId === null) {
            return null;
        }

        return TelegraphChat::query()
            ->where("telegraph_bot_id", $botId)
            ->where("chat_id", $telegramChatId)
            ->value("id");
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === "") {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $value = trim((string) $value);
            return $value === "" ? null : $value;
        }

        return null;
    }
}
