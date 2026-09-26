<?php

declare(strict_types=1);

namespace App\Domain\Song;

use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class UserSongStemMixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSongStemMix::class);
    }

    public function findForUserAndSong(User $user, Song $song): ?UserSongStemMix
    {
        return $this->findOneBy([
            'user' => $user,
            'song' => $song,
        ]);
    }
}
