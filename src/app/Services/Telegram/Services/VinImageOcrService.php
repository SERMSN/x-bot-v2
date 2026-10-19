<?php

namespace App\Services\Telegram\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VinImageOcrService
{
    private const VIN_PATTERN = '/^[A-HJ-NPR-Z0-9]{17}$/D';
    private const VIN_CHAR_WHITELIST = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';
    private const LABEL_TOKENS = ['VIN', 'VN', 'VINNO', 'VINNUMBER', 'CHASSIS', 'NUMBER', 'NO'];

    public function recognize(string $imagePath): ?string
    {
        $handle = fopen($imagePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open VIN image for OCR.');
        }

        try {
            $response = $this->ocrRequest()
                ->attach('image', $handle, basename($imagePath))
                ->post('/ocr');

            $response->throw();

            return $this->extractVinCandidate((string) $response->json('text', ''));
        } finally {
            fclose($handle);
        }
    }

    private function ocrRequest(): PendingRequest
    {
        return Http::baseUrl((string) config('vin.ocr.url'))
            ->acceptJson()
            ->connectTimeout((float) config('vin.ocr.connect_timeout_seconds', 3))
            ->timeout((float) config('vin.ocr.timeout_seconds', 30));
    }

    public function extractVinCandidate(string $text): ?string
    {
        $candidates = [];

        foreach (preg_split('/[\r\n]+/', strtoupper($text)) ?: [] as $line) {
            $tokens = preg_split('/[^A-Z0-9]+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($tokens as $start => $token) {
                if (in_array($token, self::LABEL_TOKENS, true)) {
                    continue;
                }

                $candidate = '';

                for ($index = $start; $index < count($tokens) && strlen($candidate) < 17; $index++) {
                    $candidate .= $tokens[$index];

                    if (strlen($candidate) === 17 && preg_match(self::VIN_PATTERN, $candidate) === 1) {
                        $candidates[$candidate] = $candidate;
                    }
                }
            }
        }

        return count($candidates) === 1 ? array_values($candidates)[0] : null;
    }
}
