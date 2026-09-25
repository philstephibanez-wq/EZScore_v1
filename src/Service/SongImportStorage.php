<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SongImportStorage
{
    private const AUDIO_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp3',
        'audio/x-mpeg',
        'audio/x-mp3',
        'audio/mpeg3',
        'audio/x-mpeg-3',
        'application/octet-stream',
    ];
    private const COVER_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** @return array{original_name:string,storage_path:string,mime_type:string,size:int,sha256:string} */
    public function storeMp3(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_upload');
        }

        $mimeType = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');
        $extension = mb_strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== 'mp3') {
            throw new \InvalidArgumentException('catalog.import.validation.audio_format');
        }

        // Some Windows/PHP stacks report valid MP3 files as application/octet-stream.
        // MP3s are stored outside public/ and are never executed; allow the common fallback
        // while still rejecting clearly incompatible MIME types.
        if ($mimeType !== '' && !in_array($mimeType, self::AUDIO_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_format');
        }

        $size = (int) $file->getSize();
        if ($size < 1) {
            throw new \InvalidArgumentException('catalog.import.validation.audio_empty');
        }

        $sha256 = hash_file('sha256', $file->getPathname());
        if (!is_string($sha256) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new \RuntimeException('Unable to calculate audio SHA-256.');
        }

        $absoluteDir = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'audio';
        $this->ensureDirectory($absoluteDir);

        $storedName = $sha256 . '.mp3';
        $absoluteTarget = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;
        if (!is_file($absoluteTarget)) {
            $file->move($absoluteDir, $storedName);
        }

        return [
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => 'var/storage/audio/' . $storedName,
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

        $mimeType = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        if (!in_array($mimeType, self::COVER_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('catalog.import.validation.cover_format');
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Unsupported cover format.'),
        };

        $absoluteDir = $this->projectDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers';
        $this->ensureDirectory($absoluteDir);

        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $file->move($absoluteDir, $storedName);

        return '/uploads/covers/' . $storedName;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create storage directory "%s".', $directory));
        }
    }
}
