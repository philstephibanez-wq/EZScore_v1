<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SongImportStorage
{
    /**
     * MP3 is preferred, but common audio formats are accepted.
     *
     * @var array<string, list<string>>
     */
    private const AUDIO_FORMATS = [
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/x-mp3', 'audio/mpeg3', 'audio/x-mpeg-3'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'aac' => ['audio/aac', 'audio/x-aac'],
    ];

    private const COVER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{
     *   original_name:string,
     *   storage_path:string,
     *   mime_type:string,
     *   size:int,
     *   sha256:string
     * }
     */
    public function storeAudio(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_upload');
        }

        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if (!array_key_exists($extension, self::AUDIO_FORMATS)) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_format');
        }

        $size = (int) $file->getSize();
        if ($size < 1) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_empty');
        }

        $mimeType = $this->detectMimeType($file);

        if (!$this->mimeMatchesExtension($extension, $mimeType)) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_format');
        }

        $sha256 = hash_file('sha256', $file->getPathname());
        if (!is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new \RuntimeException('Unable to calculate audio SHA-256.');
        }

        $relativeDir = 'var/storage/audio';
        $absoluteDir = $this->projectDir
            . DIRECTORY_SEPARATOR . 'var'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'audio';

        $this->ensureDirectory($absoluteDir);

        $storedName = $sha256 . '.' . $extension;
        $absoluteTarget = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;

        if (!is_file($absoluteTarget)) {
            $file->move($absoluteDir, $storedName);
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $relativeDir . '/' . $storedName,
            'mime_type' => $mimeType,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }

    public function storeCover(?UploadedFile $file): ?string
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \InvalidArgumentException('catalog.import.validation.cover_upload');
        }

        $mimeType = $this->detectMimeType($file);

        if (!in_array($mimeType, self::COVER_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('catalog.import.validation.cover_format');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('catalog.import.validation.cover_format'),
        };

        $absoluteDir = $this->projectDir
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'covers';

        $this->ensureDirectory($absoluteDir);

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($absoluteDir, $storedName);

        return '/uploads/covers/' . $storedName;
    }

    private function detectMimeType(UploadedFile $file): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($file->getPathname());

            if (is_string($detected) && $detected !== '') {
                return mb_strtolower($detected);
            }
        }

        $clientMime = trim(mb_strtolower((string) $file->getClientMimeType()));

        return $clientMime !== '' ? $clientMime : 'application/octet-stream';
    }

    private function mimeMatchesExtension(string $extension, string $mimeType): bool
    {
        if ($mimeType === 'application/octet-stream') {
            return true;
        }

        return in_array($mimeType, self::AUDIO_FORMATS[$extension], true);
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create storage directory "%s".', $directory));
        }
    }
}
