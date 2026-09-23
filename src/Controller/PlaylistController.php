<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/playlists')]
final class PlaylistController extends AbstractController
{
    #[Route('', name: 'app_playlists', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $groupMemberships = $em->getRepository(GroupMember::class)->findBy(['user' => $user]);
        $groups = array_map(static fn(GroupMember $m): UserGroup => $m->getGroup(), $groupMemberships);
        if ($this->isGranted('ROLE_ADMIN')) $groups = $em->getRepository(UserGroup::class)->findBy([], ['name' => 'ASC']);

        if ($request->isMethod('POST') && $this->isCsrfTokenValid('create_playlist', (string) $request->request->get('_token'))) {
            $name = trim((string) $request->request->get('name'));
            $ownerType = (string) $request->request->get('owner_type', 'user');
            $ownerId = $user->getId();
            if ($ownerType === 'group') {
                $candidate = (int) $request->request->get('group_id');
                $allowed = array_filter($groups, static fn(UserGroup $g): bool => $g->getId() === $candidate);
                if ($allowed === []) throw $this->createAccessDeniedException();
                $ownerId = $candidate;
            }
            if ($name !== '') {
                $playlist = (new Playlist())
                    ->setName($name)
                    ->setDescription((string) $request->request->get('description'))
                    ->setOwnerType($ownerType)
                    ->setOwnerId((int) $ownerId)
                    ->setPublic($request->request->getBoolean('public'))
                    ->setCreatedBy($user);
                $em->persist($playlist);
                $em->flush();
                $this->addFlash('success', 'Playlist créée.');
                return $this->redirectToRoute('app_playlists');
            }
        }

        $all = $em->getRepository(Playlist::class)->findBy([], ['name' => 'ASC']);
        $groupIds = array_map(static fn(UserGroup $g): int => (int) $g->getId(), $groups);
        $visible = array_values(array_filter($all, static function (Playlist $p) use ($user, $groupIds): bool {
            return $p->isPublic()
                || ($p->getOwnerType() === 'user' && $p->getOwnerId() === $user->getId())
                || ($p->getOwnerType() === 'group' && in_array($p->getOwnerId(), $groupIds, true));
        }));

        return $this->render('playlists/index.html.twig', ['playlists' => $visible, 'groups' => $groups]);
    }
}
