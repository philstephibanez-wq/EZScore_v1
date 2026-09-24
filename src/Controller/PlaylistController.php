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
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/playlists')]
final class PlaylistController extends AbstractController
{
    #[Route('', name: 'app_playlists', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $editableGroups = $this->editableGroups($user, $em);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_playlist', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'playlists.validation.name');
                return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
            }

            [$ownerType, $ownerId] = $this->resolveOwner($request, $user, $editableGroups, null);

            $playlist = (new Playlist())
                ->setName($name)
                ->setDescription((string) $request->request->get('description'))
                ->setOwnerType($ownerType)
                ->setOwnerId($ownerId)
                ->setPublic($request->request->getBoolean('public'))
                ->setCreatedBy($user);

            $em->persist($playlist);
            $em->flush();

            $this->addFlash('success', 'playlists.created');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $all = $em->getRepository(Playlist::class)->findBy([], ['name' => 'ASC']);
        $visibleGroupIds = $this->visibleGroupIds($user, $em);

        $visible = array_values(array_filter(
            $all,
            function (Playlist $playlist) use ($user, $visibleGroupIds): bool {
                return $this->isGranted('ROLE_ADMIN')
                    || $playlist->isPublic()
                    || ($playlist->getOwnerType() === 'user' && $playlist->getOwnerId() === $user->getId())
                    || ($playlist->getOwnerType() === 'group' && in_array($playlist->getOwnerId(), $visibleGroupIds, true));
            },
        ));

        $editable = [];
        foreach ($visible as $playlist) {
            $editable[(int) $playlist->getId()] = $this->canManagePlaylist($playlist, $user, $em);
        }

        return $this->render('playlists/index.html.twig', [
            'playlists' => $visible,
            'groups' => $editableGroups,
            'editable_playlists' => $editable,
        ]);
    }

    #[Route('/{id}/update', name: 'app_playlist_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->canManagePlaylist($playlist, $user, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('playlist_update_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'playlists.validation.name');
            return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
        }

        $editableGroups = $this->editableGroups($user, $em);
        [$ownerType, $ownerId] = $this->resolveOwner($request, $user, $editableGroups, $playlist);

        $playlist
            ->setName($name)
            ->setDescription((string) $request->request->get('description'))
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
            ->setPublic($request->request->getBoolean('public'));

        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.updated', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/delete', name: 'app_playlist_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Playlist $playlist, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->canManagePlaylist($playlist, $user, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('playlist_delete_'.$playlist->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($playlist);
        $em->flush();

        $this->addFlash('success', $translator->trans('playlists.deleted', [], 'management'));
        return $this->redirectToRoute('app_playlists', ['_locale' => $request->getLocale()]);
    }

    private function canManagePlaylist(Playlist $playlist, User $user, EntityManagerInterface $em): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($playlist->getOwnerType() === 'user') {
            return $playlist->getOwnerId() === $user->getId();
        }

        if ($playlist->getOwnerType() !== 'group') {
            return false;
        }

        $group = $em->getRepository(UserGroup::class)->find($playlist->getOwnerId());
        if (!$group instanceof UserGroup) {
            return false;
        }

        $membership = $em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        return $membership instanceof GroupMember
            && in_array($membership->getRole(), ['owner', 'manager'], true);
    }

    /** @return list<UserGroup> */
    private function editableGroups(User $user, EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $em->getRepository(UserGroup::class)->findBy([], ['name' => 'ASC']);
        }

        $groups = [];
        foreach ($em->getRepository(GroupMember::class)->findBy(['user' => $user]) as $membership) {
            if (in_array($membership->getRole(), ['owner', 'manager'], true)) {
                $groups[] = $membership->getGroup();
            }
        }

        usort($groups, static fn(UserGroup $a, UserGroup $b): int => strcasecmp($a->getName(), $b->getName()));
        return $groups;
    }

    /** @return list<int> */
    private function visibleGroupIds(User $user, EntityManagerInterface $em): array
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return array_map(
                static fn(UserGroup $group): int => (int) $group->getId(),
                $em->getRepository(UserGroup::class)->findAll(),
            );
        }

        return array_map(
            static fn(GroupMember $membership): int => (int) $membership->getGroup()->getId(),
            $em->getRepository(GroupMember::class)->findBy(['user' => $user]),
        );
    }

    /**
     * @param list<UserGroup> $editableGroups
     * @return array{0:string,1:int}
     */
    private function resolveOwner(Request $request, User $user, array $editableGroups, ?Playlist $existing): array
    {
        if ((string) $request->request->get('owner_type', 'user') !== 'group') {
            if ($existing instanceof Playlist
                && $this->isGranted('ROLE_ADMIN')
                && $existing->getOwnerType() === 'user') {
                return ['user', $existing->getOwnerId()];
            }

            return ['user', (int) $user->getId()];
        }

        $candidate = (int) $request->request->get('group_id');
        foreach ($editableGroups as $group) {
            if ($group->getId() === $candidate) {
                return ['group', $candidate];
            }
        }

        throw $this->createAccessDeniedException();
    }
}
