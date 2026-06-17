<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class YandexMapsParserService
{
    public function parse(string $url): array
    {
        // 599+ review uchun 5 minut yetmayapti, shuning uchun 10 minut.
        @set_time_limit(720);
        @ini_set('max_execution_time', '720');
        @ini_set('memory_limit', '1024M');

        $scriptPath = base_path('scripts/yandex-parser.cjs');

        if (! file_exists($scriptPath)) {
            throw new RuntimeException('Parser script not found: ' . $scriptPath);
        }

        $tempPath = storage_path('app/playwright-temp');

        if (! is_dir($tempPath)) {
            mkdir($tempPath, 0777, true);
        }

        $userProfile = getenv('USERPROFILE') ?: ($_SERVER['USERPROFILE'] ?? $tempPath);
        $localAppData = getenv('LOCALAPPDATA') ?: ($userProfile . '\\AppData\\Local');

        $env = array_merge($_ENV, $_SERVER, [
            'TEMP' => $tempPath,
            'TMP' => $tempPath,
            'TMPDIR' => $tempPath,
            'NODE_OPTIONS' => '--max-old-space-size=2048',

            // 10 minutgacha ishlasin.
            'YANDEX_MAX_RUNTIME_MS' => env('YANDEX_MAX_RUNTIME_MS', '600000'),
            'YANDEX_MAX_REVIEWS' => env('YANDEX_MAX_REVIEWS', '650'),
            'YANDEX_MAX_SCROLLS' => env('YANDEX_MAX_SCROLLS', '5000'),
            'YANDEX_SCROLL_WAIT_MS' => env('YANDEX_SCROLL_WAIT_MS', '180'),

            // false bo'lsa, hammasini ololmasa ham saqlaydi va warning beradi.
            'YANDEX_REQUIRE_ALL_REVIEWS' => env('YANDEX_REQUIRE_ALL_REVIEWS', false) ? '1' : '0',
            'YANDEX_DEBUG' => env('YANDEX_DEBUG', true) ? '1' : '0',

            'SystemRoot' => getenv('SystemRoot') ?: ($_SERVER['SystemRoot'] ?? 'C:\\Windows'),
            'WINDIR' => getenv('WINDIR') ?: ($_SERVER['WINDIR'] ?? 'C:\\Windows'),
            'PATH' => getenv('PATH') ?: ($_SERVER['PATH'] ?? ''),
            'USERPROFILE' => $userProfile,
            'LOCALAPPDATA' => $localAppData,
        ]);

        $process = new Process(
            [
                $this->findNodePath(),
                $scriptPath,
                $url,
            ],
            base_path(),
            $env
        );

        // Runtime 600 sekund + 60 sekund zapas.
        $process->setTimeout(660);
        $process->setIdleTimeout(null);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException(
                'Parser timeout. 10 minut ichida tugamadi. Yandex juda sekin ishlayapti yoki bot protection blok qilyapti.'
            );
        }

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if (! $process->isSuccessful()) {
            $decoded = json_decode($output, true);

            if (is_array($decoded) && isset($decoded['error'])) {
                throw new RuntimeException($decoded['error']);
            }

            throw new RuntimeException(
                $output ?: $errorOutput ?: 'Parser process failed.'
            );
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'Parser returned invalid JSON. STDOUT: '
                . mb_substr($output, 0, 2000)
                . ' STDERR: '
                . mb_substr($errorOutput, 0, 2000)
            );
        }

        if (($decoded['success'] ?? false) !== true) {
            throw new RuntimeException(
                $decoded['error'] ?? json_encode($decoded, JSON_UNESCAPED_UNICODE)
            );
        }

        if (! isset($decoded['data']) || ! is_array($decoded['data'])) {
            throw new RuntimeException('Parser response does not contain data.');
        }

        return $decoded['data'];
    }

    private function findNodePath(): string
    {
        $possiblePaths = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            'node',
        ];

        foreach ($possiblePaths as $path) {
            if ($path === 'node' || file_exists($path)) {
                return $path;
            }
        }

        return 'node';
    }
}
