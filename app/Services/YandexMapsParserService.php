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

        $nodePath = $this->findNodePath();

        $env = array_merge($_ENV, $_SERVER, [
            'TEMP' => $tempPath,
            'TMP' => $tempPath,
            'TMPDIR' => $tempPath,
            'NODE_OPTIONS' => '',
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'PATH' => getenv('PATH') ?: ($_SERVER['PATH'] ?? ''),
            'USERPROFILE' => getenv('USERPROFILE') ?: 'C:\\Users\\ABOBUS',
            'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: 'C:\\Users\\ABOBUS\\AppData\\Local',
        ]);

        $process = new Process(
            [
                $nodePath,
                $scriptPath,
                $url,
            ],
            base_path(),
            $env
        );

        $process->setTimeout(600);
        $process->setIdleTimeout(600);
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
