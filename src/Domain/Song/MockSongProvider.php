<?php

declare(strict_types=1);

namespace App\Domain\Song;

final class MockSongProvider
{
    public function catalog(): array
    {
        return [
            ['id' => 'je-te-donne', 'title' => 'Je te donne', 'artist' => 'Jean-Jacques Goldman', 'editor' => 'Éditeur démo', 'comment' => 'UI mock'],
            ['id' => 'susanna', 'title' => 'Susanna', 'artist' => 'The Art Company', 'editor' => '—', 'comment' => '2/4 à valider'],
            ['id' => 'la-boheme', 'title' => 'La Bohème', 'artist' => 'Charles Aznavour', 'editor' => '—', 'comment' => '6/8 à valider'],
        ];
    }

    public function workspace(string $id): array
    {
        $song = array_values(array_filter($this->catalog(), static fn(array $s): bool => $s['id'] === $id))[0] ?? $this->catalog()[0];
        return [
            'song' => $song + ['time_signature' => '4/4', 'capo' => 0, 'strumming' => '↓ ↓↑ ↑↓↑'],
            'stems' => ['Original', 'Voix', 'Batterie', 'Basse', 'Guitare', 'Piano', 'Other', 'Chant principal', 'Chœurs'],
            'measures' => [
                ['number' => 10, 'beats' => ['G', '-', '-', '-']],
                ['number' => 11, 'beats' => ['G', '-', 'Em', '-']],
                ['number' => 12, 'beats' => ['C', '-', '-', '-']],
                ['number' => 13, 'beats' => ['D', '-', 'G', '-']],
                ['number' => 14, 'beats' => ['G', '-', '-', '-']],
            ],
            'words' => ['Words', 'can', 'light', 'fires', 'in', 'minds', 'Carry', 'your', 'thoughts', 'across', 'the', 'night'],
        ];
    }
}
