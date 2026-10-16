<?php

namespace App\Services\Telegram\Services;

use Symfony\Component\Process\Process;

class VinImageOcrService
{
    private const VIN_PATTERN = '/^[A-HJ-NPR-Z0-9]{17}$/D';
    private const VIN_CHAR_WHITELIST = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';
    private const LABEL_TOKENS = ['VIN', 'VN', 'VINNO', 'VINNUMBER', 'CHASSIS', 'NUMBER', 'NO'];

    public function recognize(string $imagePath): ?string
    {
        $process = new Process([
            (string) config('vin.ocr.binary', 'tesseract'),
            $imagePath,
            'stdout',
            '-l',
            'eng',
            '--psm',
            '11',
            '-c',
            'tessedit_char_whitelist=' . self::VIN_CHAR_WHITELIST,
        ]);
        $process->setTimeout((float) config('vin.ocr.timeout_seconds', 20));
        $process->mustRun();

        return $this->extractVinCandidate($process->getOutput());
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