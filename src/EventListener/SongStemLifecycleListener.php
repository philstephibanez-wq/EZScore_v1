<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Domain\Song\Song;
use App\Service\SongStemStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postRemove)]
final class SongStemLifecycleListener
{
    public function __construct(
        private readonly SongStemStorage $storage,
    ) {}

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof Song) {
            $this->storage->deleteForSong($entity);
        }
    }
}
