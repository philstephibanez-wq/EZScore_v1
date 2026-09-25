<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AnalysisDesktopStateStore
{
    public const SCHEMA_VERSION = 'ezscore.worker.desktop.v1';
    public const ONLINE_AFTER_SECONDS = 8;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function updateHeartbeat(array $payload): array
    {
        $state = [
            'schema_version' => self::SCHEMA_VERSION,
            'worker_id' => trim((string) ($payload['worker_id'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'unknown')),
            'version' => trim((string) ($payload['version'] ?? '')),
            'capabilities' => is_array($payload['capabilities'] ?? null) ? $payload['capabilities'] : [],
            'current_job' => is_array($payload['current_job'] ?? null) ? $payload['current_job'] : null,
            'last_seen_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $this->writeJson($this->statePath(), $state);

        return $state;
    }

    /**
     * @return array<string,mixed>
     */
    public function state(): array
    {
        return $this->readJson($this->statePath()) ?? [
            'schema_version' => self::SCHEMA_VERSION,
            'status' => 'offline',
            'last_seen_at' => null,
        ];
    }

    public function isOnline(int $maxAgeSeconds = self::ONLINE_AFTER_SECONDS): bool
    {
        $state = $this->state();
        $lastSeen = trim((string) ($state['last_seen_at'] ?? ''));
        if ($lastSeen === '') {
            return false;
        }

        try {
            $lastSeenAt = new \DateTimeImmutable($lastSeen);
        } catch (\Throwable) {
            return false;
        }

        $age = time() - $lastSeenAt->getTimestamp();

        return $age >= 0 && $age <= max(1, $maxAgeSeconds);
    }

    /**
     * @return array{online:bool,status:string,last_seen_at:?string,last_seen_age_seconds:?int}
     */
    public function publicStatus(): array
    {
        $state = $this->state();
        $lastSeen = trim((string) ($state['last_seen_at'] ?? ''));
        $age = null;

        if ($lastSeen !== '') {
            try {
                $lastSeenAt = new \DateTimeImmutable($lastSeen);
                $age = max(0, time() - $lastSeenAt->getTimestamp());
            } catch (\Throwable) {
                $age = null;
            }
        }

        return [
            'online' => $this->isOnline(),
            'status' => $this->isOnline()
                ? trim((string) ($state['status'] ?? 'online'))
                : 'offline',
            'last_seen_at' => $lastSeen !== '' ? $lastSeen : null,
            'last_seen_age_seconds' => $age,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function pendingCommands(string $workerId): array
    {
        $queue = $this->readJson($this->commandsPath()) ?? [];
        if (!is_array($queue)) {
            return [];
        }

        return array_values(array_filter(
            $queue,
            static fn ($item): bool =>
                is_array($item)
                && ($item['acked_at'] ?? null) === null
                && (($item['worker_id'] ?? null) === null || ($item['worker_id'] ?? null) === $workerId),
        ));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function enqueueCommand(string $command, array $payload = [], ?string $workerId = null): array
    {
        $command = trim($command);
        if ($command === '') {
            throw new \InvalidArgumentException('command is required.');
        }

        $queue = $this->readJson($this->commandsPath()) ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }

        $item = [
            'id' => bin2hex(random_bytes(12)),
            'command' => $command,
            'payload' => $payload,
            'worker_id' => $workerId,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'acked_at' => null,
        ];

        $queue[] = $item;
        $this->writeJson($this->commandsPath(), array_slice($queue, -100));

        return $item;
    }

    public function acknowledge(string $commandId): void
    {
        $queue = $this->readJson($this->commandsPath()) ?? [];
        if (!is_array($queue)) {
            return;
        }

        $changed = false;
        foreach ($queue as &$item) {
            if (!is_array($item) || ($item['id'] ?? null) !== $commandId) {
                continue;
            }

            $item['acked_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $changed = true;
            break;
        }
        unset($item);

        if ($changed) {
            $this->writeJson($this->commandsPath(), array_slice($queue, -100));
        }
    }

    private function statePath(): string
    {
        return $this->runtimeDir().DIRECTORY_SEPARATOR.'state.json';
    }

    private function commandsPath(): string
    {
        return $this->runtimeDir().DIRECTORY_SEPARATOR.'commands.json';
    }

    private function runtimeDir(): string
    {
        $dir = $this->projectDir.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'analysis-desktop';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create analysis desktop runtime directory.');
        }

        return $dir;
    }

    /**
     * @return array<mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($tmp, $path);
    }
}
