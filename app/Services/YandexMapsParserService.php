<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class YandexMapsParserService
{
    public function parse(string $url): array
    {
        $scriptPath = base_path('scripts/yandex-parser.cjs');

        if (! file_exists($scriptPath)) {
            throw new RuntimeException('Parser script not found: ' . $scriptPath);
        }

        $tempPath = storage_path('app/playwright-temp');

        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $process = new Process(
            [
                'node',
                $scriptPath,
                $url,
            ],
            base_path(),
            [
                'TEMP' => $tempPath,
                'TMP' => $tempPath,
                'TMPDIR' => $tempPath,
                'NODE_OPTIONS' => '',
            ]
        );

        $process->setTimeout(120);
        $process->run();

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                $output ?: $errorOutput ?: 'Parser process failed.'
            );
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Parser returned invalid JSON: ' . $output);
        }

        if (($decoded['success'] ?? false) !== true) {
            throw new RuntimeException(json_encode($decoded, JSON_UNESCAPED_UNICODE));
        }

        if (! isset($decoded['data']) || ! is_array($decoded['data'])) {
            throw new RuntimeException('Parser response does not contain data.');
        }

        return $decoded['data'];
    }
}
