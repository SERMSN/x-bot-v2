<?php

namespace App\Services\Telegram\Services;

use App\Models\BotSetting;
use DefStudio\Telegraph\DTO\Photo;
use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class VinImageOcrService
{
    private const PROMPT = <<<'PROMPT'
Ты OCR-модуль для распознавания VIN на фотографиях автомобилей.

Найди на изображении VIN — идентификационный номер из 17 символов.
VIN может быть расположен на лобовом стекле, стойке двери, в моторном отсеке или на документе.
Допустимые символы: A-Z и 0-9. Буквы I, O и Q не используются.
Особенно внимательно различай: 0 и O, 1 и I, 8 и B, 5 и S, 2 и Z, 6 и G.
Не придумывай символы и не дополняй номер, если он виден частично.

Верни строго JSON без markdown и пояснений:
{
  "vin": "17 символов или null",
  "confidence": 0.0,
  "alternatives": ["другие возможные варианты, если символы неоднозначны"],
  "notes": "кратко: что мешает распознаванию, или пустая строка"
}

confidence — твоя уверенность от 0 до 1. Если VIN не виден или читается меньше 17 символов, верни "vin": null.
PROMPT;

    public function recognizePhoto(TelegraphBot $bot, Photo $photo): ?VinRecognitionResult
    {
        if (!(bool) config("vin.vision.enabled", true)) {
            return null;
        }

        // Fail fast before downloading the photo if the integration is not configured.
        $this->resolveApiKey((int) $bot->id);

        $maxBytes = max(1, (int) config("vin.vision.max_image_mb", 10)) * 1024 * 1024;
        if (($photo->filesize() ?? 0) > $maxBytes) {
            throw new RuntimeException("Telegram VIN photo is too large");
        }

        $path = $this->downloadPhoto($bot, $photo);

        try {
            return $this->recognize($path, (int) $bot->id);
        } finally {
            File::delete($path);
        }
    }

    public function recognize(string $imagePath, int $botId = 0): ?VinRecognitionResult
    {
        if (!(bool) config("vin.vision.enabled", true)) {
            return null;
        }

        if (!is_file($imagePath) || !is_readable($imagePath)) {
            throw new RuntimeException("VIN image is not readable");
        }

        $maxBytes = max(1, (int) config("vin.vision.max_image_mb", 10)) * 1024 * 1024;
        $imageSize = (int) filesize($imagePath);
        if ($imageSize <= 0 || $imageSize > $maxBytes) {
            throw new RuntimeException("VIN image size is not allowed");
        }

        $apiKey = $this->resolveApiKey($botId);
        $image = File::get($imagePath);
        $mime = $this->detectMimeType($imagePath);

        $payload = [
            "model" => (string) config("vin.vision.model", "deepseek-flash"),
            "messages" => [
                [
                    "role" => "system",
                    "content" => "Ты аккуратный OCR-модуль. Отвечай только валидным JSON.",
                ],
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "text",
                            "text" => self::PROMPT,
                        ],
                        [
                            "type" => "image_url",
                            "image_url" => [
                                "url" => "data:{$mime};base64," . base64_encode($image),
                                "detail" => "original",
                            ],
                        ],
                    ],
                ],
            ],
            "temperature" => 0,
            "max_tokens" => (int) config("vin.vision.max_tokens", 400),
            "response_format" => ["type" => "json_object"],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config("vin.vision.timeout_seconds", 20))
                ->retry(
                    (int) config("vin.vision.retries", 2),
                    (int) config("vin.vision.retry_sleep_ms", 300),
                    null,
                    false,
                )
                ->post(
                    rtrim((string) config("vin.vision.base_url", "https://api.deepseek.com"), "/") .
                        "/chat/completions",
                    $payload,
                );
        } catch (Throwable $e) {
            Log::warning("VIN vision request failed", [
                "bot_id" => $botId,
                "error" => $e->getMessage(),
            ]);

            return null;
        }

        if (!$response->successful()) {
            Log::warning("VIN vision API error", [
                "bot_id" => $botId,
                "status" => $response->status(),
                "error" => Str::limit((string) $response->json("error.message", ""), 300),
            ]);

            return null;
        }

        $content = $response->json("choices.0.message.content");
        if (is_array($content)) {
            $content = collect($content)
                ->map(fn ($part) => is_array($part) ? ($part["text"] ?? "") : "")
                ->filter()
                ->implode("\n");
        }

        if (!is_string($content) || trim($content) === "") {
            Log::warning("VIN vision returned empty content", ["bot_id" => $botId]);

            return null;
        }

        $data = $this->decodeJson($content);
        if ($data === null) {
            Log::warning("VIN vision returned invalid JSON", [
                "bot_id" => $botId,
                "content" => Str::limit($content, 300),
            ]);

            return null;
        }

        $result = $this->toResult($data);
        if ($result === null) {
            Log::info("VIN vision found no valid VIN", ["bot_id" => $botId]);
        }

        return $result;
    }

    private function downloadPhoto(TelegraphBot $bot, Photo $photo): string
    {
        $fileRequest = $bot->getFileInfo($photo->id());
        $fileInfo = $fileRequest->send();

        if ($fileInfo->telegraphError()) {
            throw new RuntimeException("Telegram getFile failed");
        }

        $filePath = $fileInfo->json("result.file_path");
        if (!is_string($filePath) || $filePath === "") {
            throw new RuntimeException("Telegram file_path is missing");
        }

        $maxBytes = max(1, (int) config("vin.vision.max_image_mb", 10)) * 1024 * 1024;
        $fileSize = (int) $fileInfo->json("result.file_size", 0);
        if ($fileSize > $maxBytes) {
            throw new RuntimeException("Telegram VIN photo is too large");
        }

        $url = rtrim($fileRequest->getFilesUrl(), "/") . "/" . ltrim($filePath, "/");
        $download = Http::timeout((int) config("vin.vision.timeout_seconds", 20))->get($url);

        if (!$download->successful()) {
            throw new RuntimeException("Telegram file download failed");
        }

        $body = $download->body();
        if ($body === "" || strlen($body) > $maxBytes) {
            throw new RuntimeException("Telegram VIN photo has invalid size");
        }

        $directory = storage_path("app/vin-recognition");
        File::ensureDirectoryExists($directory);

        $path = $directory . "/" . Str::random(24) . ".jpg";
        File::put($path, $body);

        if (!is_file($path)) {
            throw new RuntimeException("VIN photo could not be stored");
        }

        return $path;
    }

    private function resolveApiKey(int $botId): string
    {
        $configuredKey = trim((string) config("vin.vision.api_key", ""));
        if ($configuredKey !== "") {
            return $configuredKey;
        }

        if ($botId > 0) {
            $botKey = trim((string) Cache::remember(
                "vin_vision_api_key_{$botId}",
                now()->addMinutes((int) config("vin.cache.settings_ttl_minutes", 10)),
                fn () => BotSetting::query()
                    ->where("telegraph_bot_id", $botId)
                    ->where("key", BotSetting::KEY_DEEPSEEK_API_KEY)
                    ->value("value"),
            ));

            if ($botKey !== "") {
                return $botKey;
            }
        }

        throw new RuntimeException("DEEPSEEK_API_KEY is not configured");
    }

    private function detectMimeType(string $imagePath): string
    {
        $mime = function_exists("mime_content_type")
            ? (string) mime_content_type($imagePath)
            : "";

        return in_array($mime, ["image/jpeg", "image/png", "image/gif", "image/webp"], true)
            ? $mime
            : "image/jpeg";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', "", $content) ?? $content;

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) === 1) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toResult(array $data): ?VinRecognitionResult
    {
        $confidence = is_numeric($data["confidence"] ?? null)
            ? (float) $data["confidence"]
            : 0.5;
        $confidence = max(0.0, min(1.0, $confidence));

        $alternatives = [];
        foreach ((array) ($data["alternatives"] ?? []) as $alternative) {
            if (!is_string($alternative)) {
                continue;
            }

            $normalized = $this->normalizeVin($alternative);
            if ($this->isValidVin($normalized) && !in_array($normalized, $alternatives, true)) {
                $alternatives[] = $normalized;
            }
        }

        $vin = is_string($data["vin"] ?? null)
            ? $this->normalizeVin($data["vin"])
            : "";

        if (!$this->isValidVin($vin)) {
            $vin = $alternatives[0] ?? "";
        }

        if (!$this->isValidVin($vin)) {
            return null;
        }

        $notes = is_string($data["notes"] ?? null)
            ? Str::limit(trim($data["notes"]), 300)
            : null;

        return new VinRecognitionResult(
            vin: $vin,
            confidence: $confidence,
            alternatives: array_slice(array_values(array_diff($alternatives, [$vin])), 0, 3),
            notes: $notes === "" ? null : $notes,
        );
    }

    private function normalizeVin(string $vin): string
    {
        $vin = strtoupper(trim($vin));
        $vin = preg_replace('/[\s\-]+/', "", $vin) ?? $vin;

        return $vin;
    }

    private function isValidVin(string $vin): bool
    {
        $length = (int) config("vin.validation.length", 17);

        return strlen($vin) === $length
            && preg_match('/^[A-HJ-NPR-Z0-9]+$/', $vin) === 1;
    }
}
