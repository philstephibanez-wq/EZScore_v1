<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Analysis\AnalysisJob;
use App\Domain\Playlist\Playlist;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
    ) {
    }

    public function delete(User $target, User $actor): void
    {
        if ($target->getId() === null || $actor->getId() === null) {
            throw new \LogicException('Persisted users are required.');
        }

        if ($target->getId() === $actor->getId()) {
            throw new \DomainException('users.error.delete_self');
        }

        if (
            $target->isActive()
            && $target->getPrimaryRole() === 'ROLE_ADMIN'
            && $this->users->countActiveAdmins() <= 1
        ) {
            throw new \DomainException('users.error.delete_last_admin');
        }

        $analysisJobCount = $this->em
            ->getRepository(AnalysisJob::class)
            ->count(['createdBy' => $target]);

        if ($analysisJobCount > 0) {
            throw new \DomainException('users.error.delete_analysis_history');
        }

        /*
         * Personal playlists owned by the deleted account are removed.
         * Other playlists created by that account (for example group playlists)
         * are preserved and their technical creator is transferred to the admin
         * performing the deletion.
         */
        $playlists = $this->em
            ->getRepository(Playlist::class)
            ->findBy(['createdBy' => $target]);

        foreach ($playlists as $playlist) {
            if (
                $playlist->getOwnerType() === 'user'
                && $playlist->getOwnerId() === $target->getId()
            ) {
                $this->em->remove($playlist);
                continue;
            }

            $playlist->setCreatedBy($actor);
        }

        /*
         * Existing DB constraints then apply:
         * - group memberships: CASCADE
         * - songs.editor_id: SET NULL
         * - remaining playlists: already reassigned above
         * - analysis_jobs.created_by: protected above (RESTRICT)
         */
        $this->em->remove($target);
        $this->em->flush();
    }
}
