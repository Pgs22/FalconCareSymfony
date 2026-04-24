<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Minimal file-backed event publisher for SSE consumers.
 */
final class RealtimeSyncPublisher
{
    private const STORAGE_RELATIVE_PATH = '/var/sse-sync-events.log';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param list<string> $topics
     */
    public function publishTopics(array $topics): void
    {
        $normalized = [];
        foreach ($topics as $topic) {
            $value = trim($topic);
            if ($value === '') {
                continue;
            }
            $normalized[$value] = true;
        }

        if ($normalized === []) {
            return;
        }

        $payload = [
            'id' => (string) microtime(true),
            'topics' => array_keys($normalized),
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $line = json_encode($payload, JSON_THROW_ON_ERROR) . PHP_EOL;
        $filePath = $this->getStoragePath();
        $directory = \dirname($filePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
    }

    public function publishTopic(string $topic): void
    {
        $this->publishTopics([$topic]);
    }

    public function getStoragePath(): string
    {
        return rtrim($this->projectDir, '/\\') . self::STORAGE_RELATIVE_PATH;
    }
}
