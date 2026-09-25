<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Song\Song;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SongStemStorage
{
    public const STEMS = [
        'vocals',
        'lead_vocals',
        'backing_vocals',
        'drums',
        'bass',
        'guitar',
        'piano',
        'other',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function storageRoot(Song $song): string
    {
        $hash = $song->getAudioSha256();
        if (!is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new \RuntimeException('Song has no valid source audio hash.');
        }

        return $this->projectDir
            . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'stems'
            . DIRECTORY_SEPARATOR . 'song-' . (int) $song->getId()
            . DIRECTORY_SEPARATOR . $hash;
    }

    public function progressPath(Song $song): string
    {
        return $this->storageRoot($song) . DIRECTORY_SEPARATOR . 'progress.json';
    }

    public function logPath(Song $song): string
    {
        return $this->storageRoot($song) . DIRECTORY_SEPARATOR . 'worker.log';
    }


    public function logExists(Song $song): bool
    {
        $path = $this->logPath($song);

        return is_file($path) && filesize($path) > 0;
    }

    public function readLogTail(Song $song, int $maxBytes = 60000): string
    {
        $path = $this->logPath($song);
        if (!is_file($path)) {
            return '';
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return '';
        }

        $maxBytes = max(4096, min(250000, $maxBytes));
        $offset = max(0, $size - $maxBytes);

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        try {
            if ($offset > 0) {
                fseek($handle, $offset);
                fgets($handle);
            }

            $content = stream_get_contents($handle);

            return is_string($content) ? $content : '';
        } finally {
            fclose($handle);
        }
    }

    public function sourcePath(Song $song): string
    {
        $relative = trim((string) $song->getAudioStoragePath());
        if ($relative === '') {
            throw new \RuntimeException('Song has no persisted source audio.');
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

        return $this->projectDir . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function manifest(Song $song): ?array
    {
        $runDir = $this->currentRunDirectory($song);
        if ($runDir === null) {
            return null;
        }

        $path = $runDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function progress(Song $song): ?array
    {
        $path = $this->progressPath($song);
        if (!is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : null;
    }

    public function stemPath(Song $song, string $name): ?string
    {
        if (!in_array($name, self::STEMS, true)) {
            return null;
        }

        $runDir = $this->currentRunDirectory($song);
        if ($runDir === null) {
            return null;
        }

        $path = $runDir . DIRECTORY_SEPARATOR . $name . '.wav';

        return is_file($path) && filesize($path) > 0 ? $path : null;
    }

    public function hasCompleteStems(Song $song): bool
    {
        $manifest = $this->manifest($song);
        if (!is_array($manifest)) {
            return false;
        }

        foreach (self::STEMS as $stem) {
            if ($this->stemPath($song, $stem) === null) {
                return false;
            }
        }

        return true;
    }


    public function pruneOtherAudioHashes(Song $song): void
    {
        $currentHash = (string) $song->getAudioSha256();
        if (!preg_match('/^[a-f0-9]{64}$/', $currentHash)) {
            return;
        }

        $songDir = $this->projectDir
            . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'stems'
            . DIRECTORY_SEPARATOR . 'song-' . (int) $song->getId();

        if (!is_dir($songDir)) {
            return;
        }

        foreach (new \DirectoryIterator($songDir) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            if ($item->getFilename() !== $currentHash) {
                $this->removeDirectory($item->getPathname());
            }
        }
    }

    public function deleteForSong(Song $song): void
    {
        $songDir = $this->projectDir
            . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'stems'
            . DIRECTORY_SEPARATOR . 'song-' . (int) $song->getId();

        $this->removeDirectory($songDir);
    }

    private function currentRunDirectory(Song $song): ?string
    {
        $root = $this->storageRoot($song);
        $pointer = $root . DIRECTORY_SEPARATOR . 'current.json';

        if (!is_file($pointer)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($pointer), true);
        $run = is_array($payload) ? trim((string) ($payload['run'] ?? '')) : '';

        if (!preg_match('/^run-[A-Za-z0-9]+$/', $run)) {
            return null;
        }

        $runDir = $root . DIRECTORY_SEPARATOR . 'runs' . DIRECTORY_SEPARATOR . $run;

        return is_dir($runDir) ? $runDir : null;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }
}
