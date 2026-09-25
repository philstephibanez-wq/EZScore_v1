<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Event\Event;
use App\Domain\Event\EventParticipant;
use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/picker/events')]
final class EventPickerController extends AbstractController
{
    private const PAGE_SIZE = 25;

    #[Route('/groups', name: 'app_event_picker_groups', methods: ['GET'])]
    public function groups(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $event = $this->event($request, $em);
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_MANAGE, $event);

        $qb = $em->getRepository(UserGroup::class)->createQueryBuilder('g')
            ->orderBy('LOWER(g.name)', 'ASC');

        $user = $this->requireUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->innerJoin(GroupMember::class, 'gm', 'WITH', 'gm.group = g AND gm.user = :user')
                ->setParameter('user', $user)
                ->andWhere('gm.role IN (:roles)')
                ->setParameter('roles', ['owner', 'manager']);
        }

        $this->applyText($qb, 'g.name', $request);
        [$rows, $page, $pages, $total] = $this->paginate($qb, $request);

        return $this->json([
            'items' => array_map(static fn(UserGroup $group): array => [
                'id' => (int) $group->getId(),
                'label' => $group->getName(),
                'meta' => ['#'.$group->getId()],
            ], $rows),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [],
        ]);
    }

    #[Route('/playlists', name: 'app_event_picker_playlists', methods: ['GET'])]
    public function playlists(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $event = $this->event($request, $em);
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_MANAGE, $event);

        $user = $this->requireUser();
        $qb = $em->getRepository(Playlist::class)->createQueryBuilder('p')
            ->leftJoin('p.ownerUser', 'o')
            ->addSelect('o')
            ->orderBy('LOWER(p.name)', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('p.ownerUser = :owner')->setParameter('owner', $user);
        }

        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ($query !== '') {
            $qb->andWhere("(LOWER(p.name) LIKE :q OR LOWER(COALESCE(p.description, '')) LIKE :q)")
                ->setParameter('q', '%'.$query.'%');
        }

        [$rows, $page, $pages, $total] = $this->paginate($qb, $request);

        return $this->json([
            'items' => array_map(static fn(Playlist $playlist): array => [
                'id' => (int) $playlist->getId(),
                'label' => $playlist->getName(),
                'meta' => [$playlist->getOwnerUser()->getDisplayName(), '#'.$playlist->getId()],
            ], $rows),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [],
        ]);
    }

    #[Route('/users', name: 'app_event_picker_users', methods: ['GET'])]
    public function users(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $event = $this->event($request, $em);
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_INVITE, $event);

        $selectedIds = [];
        foreach ($em->getRepository(EventParticipant::class)->findBy(['event' => $event]) as $participant) {
            $selectedIds[] = (int) $participant->getUser()->getId();
        }

        $membership = (string) $request->query->get('membership', 'out');
        $qb = $em->getRepository(User::class)->createQueryBuilder('u')
            ->andWhere('u.active = true')
            ->orderBy('LOWER(u.displayName)', 'ASC');

        if ($membership === 'in') {
            if ($selectedIds === []) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('u.id IN (:selected)')->setParameter('selected', $selectedIds);
            }
        } elseif ($selectedIds !== []) {
            $qb->andWhere('u.id NOT IN (:selected)')->setParameter('selected', $selectedIds);
        }

        if ($event->getGroup() !== null) {
            $qb->innerJoin(GroupMember::class, 'egm', 'WITH', 'egm.user = u AND egm.group = :eventGroup')
                ->setParameter('eventGroup', $event->getGroup());
        } elseif ($event->getPlaylist() !== null) {
            $playlist = $event->getPlaylist();
            $groupIds = array_map(
                static fn(PlaylistGroup $link): int => (int) $link->getGroup()->getId(),
                $em->getRepository(PlaylistGroup::class)->findBy(['playlist' => $playlist]),
            );
            $acceptedIds = array_map(
                static fn(PlaylistInvitation $inv): int => (int) $inv->getInvitedUser()->getId(),
                $em->getRepository(PlaylistInvitation::class)->findBy([
                    'playlist' => $playlist,
                    'status' => PlaylistInvitationStatus::Accepted,
                ]),
            );

            $allowedIds = $acceptedIds;
            $allowedIds[] = (int) $playlist->getOwnerUser()->getId();

            if ($groupIds !== []) {
                foreach ($em->getRepository(GroupMember::class)->createQueryBuilder('gm')
                    ->andWhere('gm.group IN (:groups)')
                    ->setParameter('groups', $groupIds)
                    ->getQuery()->getResult() as $membershipRow) {
                    $allowedIds[] = (int) $membershipRow->getUser()->getId();
                }
            }

            $allowedIds = array_values(array_unique($allowedIds));
            if (!$playlist->isPublic()) {
                if ($allowedIds === []) {
                    $qb->andWhere('1 = 0');
                } else {
                    $qb->andWhere('u.id IN (:allowed)')->setParameter('allowed', $allowedIds);
                }
            }
        }

        $this->applyText($qb, 'u.displayName', $request);
        [$rows, $page, $pages, $total] = $this->paginate($qb, $request);

        return $this->json([
            'items' => array_map(static fn(User $user): array => [
                'id' => (int) $user->getId(),
                'label' => $user->getDisplayName(),
                'meta' => [$user->getPrimaryRole(), '#'.$user->getId()],
            ], $rows),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'filters' => [],
        ]);
    }

    private function event(Request $request, EntityManagerInterface $em): Event
    {
        $event = $em->getRepository(Event::class)->find($request->query->getInt('event_id'));
        if (!$event instanceof Event) throw $this->createNotFoundException();
        return $event;
    }

    private function applyText(QueryBuilder $qb, string $field, Request $request): void
    {
        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if ($query !== '') {
            $qb->andWhere('LOWER('.$field.') LIKE :q')->setParameter('q', '%'.$query.'%');
        }
    }

    /** @return array{0:list<object>,1:int,2:int,3:int} */
    private function paginate(QueryBuilder $qb, Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $countQb = clone $qb;
        $alias = $qb->getRootAliases()[0];
        $total = (int) $countQb
            ->select('COUNT(DISTINCT '.$alias.'.id)')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $rows = $qb
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return [$rows, $page, $pages, $total];
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        return $user;
    }
}
