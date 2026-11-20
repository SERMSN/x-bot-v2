<?php

namespace Tests\Unit;

use App\Services\Telegram\Services\VinImageOcrService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VinImageOcrServiceTest extends TestCase
{
    private string $imagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imagePath = tempnam(sys_get_temp_dir(), "vin-test-") ?: "";
        file_put_contents($this->imagePath, "fake-image-content");

        config([
            "vin.vision.enabled" => true,
            "vin.vision.api_key" => "test-deepseek-key",
            "vin.vision.base_url" => "https://api.deepseek.com",
            "vin.vision.model" => "deepseek-flash",
            "vin.validation.length" => 17,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->imagePath !== "" && is_file($this->imagePath)) {
            unlink($this->imagePath);
        }

        parent::tearDown();
    }

    public function test_it_recognizes_a_valid_vin_from_a_photo(): void
    {
        Http::fake([
            "api.deepseek.com/*" => Http::response([
                "choices" => [[
                    "message" => [
                        "content" => json_encode([
                            "vin" => "XW8ZZZ61ZJG012345",
                            "confidence" => 0.93,
                            "alternatives" => [],
                            "notes" => "",
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);
        Http::preventStrayRequests();

        $result = (new VinImageOcrService())->recognize($this->imagePath, 1);

        $this->assertNotNull($result);
        $this->assertSame("XW8ZZZ61ZJG012345", $result->vin);
        $this->assertSame(0.93, $result->confidence);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $data["model"] === "deepseek-flash"
                && $data["temperature"] === 0
                && $data["response_format"] === ["type" => "json_object"]
                && str_starts_with(
                    $data["messages"][1]["content"][1]["image_url"]["url"],
                    "data:image/jpeg;base64,",
                );
        });
    }

    public function test_it_accepts_json_wrapped_in_markdown_fences(): void
    {
        Http::fake([
            "api.deepseek.com/*" => Http::response([
                "choices" => [[
                    "message" => [
                        "content" => "```json\n" . json_encode([
                            "vin" => "WVWZZZ1JZXW000001",
                            "confidence" => 0.81,
                            "alternatives" => [],
                            "notes" => "небольшой блик",
                        ], JSON_THROW_ON_ERROR) . "\n```",
                    ],
                ]],
            ]),
        ]);
        Http::preventStrayRequests();

        $result = (new VinImageOcrService())->recognize($this->imagePath, 1);

        $this->assertNotNull($result);
        $this->assertSame("WVWZZZ1JZXW000001", $result->vin);
    }

    public function test_it_falls_back_to_a_valid_alternative(): void
    {
        Http::fake([
            "api.deepseek.com/*" => Http::response([
                "choices" => [[
                    "message" => [
                        "content" => json_encode([
                            "vin" => "XW8ZZZ61ZJG01234O",
                            "confidence" => 0.45,
                            "alternatives" => ["XW8ZZZ61ZJG012340", "XW8ZZZ61ZJG01234O"],
                            "notes" => "последний символ неоднозначен",
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);
        Http::preventStrayRequests();

        $result = (new VinImageOcrService())->recognize($this->imagePath, 1);

        $this->assertNotNull($result);
        $this->assertSame("XW8ZZZ61ZJG012340", $result->vin);
        $this->assertSame([], $result->alternatives);
    }

    public function test_it_returns_null_for_an_invalid_vin(): void
    {
        Http::fake([
            "api.deepseek.com/*" => Http::response([
                "choices" => [[
                    "message" => [
                        "content" => json_encode([
                            "vin" => "XW8ZZZ61ZJG01234O",
                            "confidence" => 0.9,
                            "alternatives" => [],
                            "notes" => "",
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);
        Http::preventStrayRequests();

        $this->assertNull((new VinImageOcrService())->recognize($this->imagePath, 1));
    }

    public function test_it_returns_null_when_the_api_fails(): void
    {
        Http::fake([
            "api.deepseek.com/*" => Http::response(["error" => ["message" => "bad request"]], 400),
        ]);
        Http::preventStrayRequests();

        $this->assertNull((new VinImageOcrService())->recognize($this->imagePath, 1));
    }
}
