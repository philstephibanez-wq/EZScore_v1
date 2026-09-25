<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Security\Acl\AclPrivilege;
use App\Service\ListPagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/groups')]
final class GroupController extends AbstractController
{
    #[Route('', name: 'app_groups', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ListPagination $pagination,
    ): Response
    {
        $currentUser = $this->requireUser();
        $canCreateGroup = $this->isGranted(AclPrivilege::GROUP_CREATE);

        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted(AclPrivilege::GROUP_CREATE);

            if (!$this->isCsrfTokenValid('create_group', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim((string) $request->request->get('name'));
            if ($name === '') {
                $this->addFlash('error', 'groups.validation.name');
                return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
            }

            $group = (new UserGroup())
                ->setName($name)
                ->setDescription((string) $request->request->get('description'));

            $owner = (new GroupMember())
                ->setGroup($group)
                ->setUser($currentUser)
                ->setRole('owner');

            $em->persist($group);
            $em->persist($owner);
            $em->flush();

            $this->addFlash('success', 'groups.created');
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        $query = $pagination->query($request);
        $letter = $pagination->letter($request);
        $memberQuery = trim((string) $request->query->get('member', ''));
        $playlistQuery = trim((string) $request->query->get('playlist', ''));

        $qb = $em->getRepository(UserGroup::class)->createQueryBuilder('g')
            ->leftJoin(GroupMember::class, 'gmSearch', 'WITH', 'gmSearch.group = g')
            ->leftJoin('gmSearch.user', 'guSearch')
            ->leftJoin(PlaylistGroup::class, 'pgSearch', 'WITH', 'pgSearch.group = g')
            ->leftJoin('pgSearch.playlist', 'pSearch')
            ->distinct()
            ->orderBy('LOWER(g.name)', 'ASC');

        if (!$this->isGranted('ROLE_ADMIN')) {
            $qb->innerJoin(GroupMember::class, 'gmAccess', 'WITH', 'gmAccess.group = g AND gmAccess.user = :currentUser')
                ->setParameter('currentUser', $currentUser);
        }

        if ($query !== '') {
            $qb->andWhere(
                "(LOWER(g.name) LIKE :q
                  OR LOWER(COALESCE(g.description, '')) LIKE :q)"
            )->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        if ($memberQuery !== '') {
            $qb->andWhere(
                "(LOWER(COALESCE(guSearch.displayName, '')) LIKE :memberQ
                  OR LOWER(COALESCE(guSearch.email, '')) LIKE :memberQ)"
            )->setParameter('memberQ', '%'.mb_strtolower($memberQuery).'%');
        }

        if ($playlistQuery !== '') {
            $qb->andWhere(
                "(LOWER(COALESCE(pSearch.name, '')) LIKE :playlistQ
                  OR LOWER(COALESCE(pSearch.description, '')) LIKE :playlistQ)"
            )->setParameter('playlistQ', '%'.mb_strtolower($playlistQuery).'%');
        }

        if ($letter !== null) {
            $qb->andWhere('UPPER(SUBSTRING(g.name, 1, 1)) = :letter')
                ->setParameter('letter', $letter);
        }

        $pager = $pagination->paginate($qb, $request, 'g', 'page', 12);
        $groups = $pager['rows'];

        $membersByGroup = [];
        $playlistsByGroup = [];

        $memberCountsByGroup = [];
        $playlistCountsByGroup = [];

        foreach ($groups as $group) {
            $groupId = (int) $group->getId();
            $memberCountsByGroup[$groupId] = $em->getRepository(GroupMember::class)->count(['group' => $group]);
            $playlistCountsByGroup[$groupId] = $em->getRepository(PlaylistGroup::class)->count(['group' => $group]);

            $memberQb = $em->getRepository(GroupMember::class)->createQueryBuilder('gm')
                ->join('gm.user', 'u')
                ->addSelect('u')
                ->andWhere('gm.group = :group')
                ->setParameter('group', $group)
                ->orderBy('LOWER(u.displayName)', 'ASC');

            if ($memberQuery !== '') {
                $memberQb->andWhere('(LOWER(u.displayName) LIKE :mq OR LOWER(u.email) LIKE :mq)')
                    ->setParameter('mq', '%'.mb_strtolower($memberQuery).'%');
            } else {
                $memberQb->setMaxResults(8);
            }

            $membersByGroup[$groupId] = $memberQb->getQuery()->getResult();

            $playlistQb = $em->getRepository(PlaylistGroup::class)->createQueryBuilder('pg')
                ->join('pg.playlist', 'p')
                ->addSelect('p')
                ->andWhere('pg.group = :group')
                ->setParameter('group', $group)
                ->orderBy('LOWER(p.name)', 'ASC');

            if ($playlistQuery !== '') {
                $playlistQb->andWhere('(LOWER(p.name) LIKE :pq OR LOWER(COALESCE(p.description, \'\')) LIKE :pq)')
                    ->setParameter('pq', '%'.mb_strtolower($playlistQuery).'%');
            } else {
                $playlistQb->setMaxResults(8);
            }

            $links = $playlistQb->getQuery()->getResult();
            $playlistsByGroup[$groupId] = array_map(
                static fn(PlaylistGroup $link): Playlist => $link->getPlaylist(),
                $links,
            );
        }

        return $this->render('groups/index.html.twig', [
            'groups' => $groups,
            'members_by_group' => $membersByGroup,
            'playlists_by_group' => $playlistsByGroup,
            'member_counts_by_group' => $memberCountsByGroup,
            'playlist_counts_by_group' => $playlistCountsByGroup,
            'can_create_group' => $canCreateGroup,
            'pager' => $pager,
            'alphabet' => $pagination->alphabet(),
            'filters' => [
                'q' => $query,
                'letter' => $letter,
                'member' => $memberQuery,
                'playlist' => $playlistQuery,
            ],
        ]);
    }

    #[Route('/{id}/update', name: 'app_group_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(UserGroup $group, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_EDIT, $group);

        if (!$this->isCsrfTokenValid('group_update_'.$group->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'groups.validation.name');
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        $group->setName($name)->setDescription((string) $request->request->get('description'));
        $em->flush();

        $this->addFlash('success', $translator->trans('groups.updated', [], 'management'));
        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/delete', name: 'app_group_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(UserGroup $group, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_DELETE, $group);

        if (!$this->isCsrfTokenValid('group_delete_'.$group->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($group);
        $em->flush();

        $this->addFlash('success', $translator->trans('groups.deleted_unlinked', [], 'management'));
        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/members', name: 'app_group_member_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addMember(
        UserGroup $group,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_MEMBERS, $group);

        if (!$this->isCsrfTokenValid('group_'.$group->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $user = $users->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $this->addFlash('error', 'groups.member.not_found');
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        $requestedRole = (string) $request->request->get('role', 'member');
        $this->saveMembership($group, $user, $requestedRole, $em, $translator);

        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/members/bulk-add', name: 'app_group_members_bulk_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkAddMembers(UserGroup $group, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_MEMBERS, $group);
        $this->validateAjaxCsrf('group_members_bulk_'.$group->getId(), $request);

        $added = 0;
        foreach ($this->ids($request) as $id) {
            $user = $em->getRepository(User::class)->find($id);
            if (!$user instanceof User || !$user->isActive()) continue;

            if ($em->getRepository(GroupMember::class)->findOneBy(['group' => $group, 'user' => $user]) instanceof GroupMember) {
                continue;
            }

            $em->persist((new GroupMember())->setGroup($group)->setUser($user)->setRole('member'));
            ++$added;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $added]);
    }

    #[Route('/{id}/members/bulk-remove', name: 'app_group_members_bulk_remove', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkRemoveMembers(UserGroup $group, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_MEMBERS, $group);
        $this->validateAjaxCsrf('group_members_bulk_'.$group->getId(), $request);

        $memberships = [];
        $ownersSelected = 0;

        foreach ($this->ids($request) as $id) {
            $user = $em->getRepository(User::class)->find($id);
            if (!$user instanceof User) continue;

            $membership = $em->getRepository(GroupMember::class)->findOneBy(['group' => $group, 'user' => $user]);
            if (!$membership instanceof GroupMember) continue;

            if ($membership->getRole() === 'owner') ++$ownersSelected;
            $memberships[] = $membership;
        }

        if ($ownersSelected > 0) {
            $this->denyAccessUnlessGranted(AclPrivilege::GROUP_DELEGATE, $group);
            if ($this->countGroupOwners($group, $em) - $ownersSelected < 1) {
                return $this->json(['ok' => false, 'message' => 'last_owner'], 422);
            }
        }

        foreach ($memberships as $membership) $em->remove($membership);
        $em->flush();

        return $this->json(['ok' => true, 'changed' => count($memberships)]);
    }

    #[Route('/{groupId}/members/{memberId}/role', name: 'app_group_member_role', requirements: ['groupId' => '\\d+', 'memberId' => '\\d+'], methods: ['POST'])]
    public function updateMemberRole(
        int $groupId,
        int $memberId,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $group = $em->getRepository(UserGroup::class)->find($groupId);
        $membership = $em->getRepository(GroupMember::class)->find($memberId);

        if (!$group instanceof UserGroup || !$membership instanceof GroupMember || $membership->getGroup()->getId() !== $group->getId()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_MEMBERS, $group);

        if (!$this->isCsrfTokenValid('group_member_role_'.$membership->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $requestedRole = (string) $request->request->get('role', 'member');
        if (!in_array($requestedRole, ['owner', 'manager', 'member'], true)) {
            throw $this->createAccessDeniedException();
        }

        if ($requestedRole === 'owner' || $membership->getRole() === 'owner') {
            $this->denyAccessUnlessGranted(AclPrivilege::GROUP_DELEGATE, $group);
        }

        if ($membership->getRole() === 'owner' && $requestedRole !== 'owner' && $this->countGroupOwners($group, $em) <= 1) {
            $this->addFlash('error', $translator->trans('groups.member.last_owner', [], 'management'));
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        $membership->setRole($requestedRole);
        $em->flush();

        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/playlists/bulk-add', name: 'app_group_playlists_bulk_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkAddPlaylists(UserGroup $group, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_PLAYLISTS, $group);
        $this->validateAjaxCsrf('group_playlists_bulk_'.$group->getId(), $request);

        $user = $this->requireUser();
        $added = 0;

        foreach ($this->ids($request) as $id) {
            $playlist = $em->getRepository(Playlist::class)->find($id);
            if (!$playlist instanceof Playlist || !$this->isGranted(AclPrivilege::PLAYLIST_EDIT, $playlist)) continue;

            if ($em->getRepository(PlaylistGroup::class)->findOneBy(['group' => $group, 'playlist' => $playlist]) instanceof PlaylistGroup) {
                continue;
            }

            $em->persist(new PlaylistGroup($playlist, $group, $user));
            ++$added;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $added]);
    }

    #[Route('/{id}/playlists/bulk-remove', name: 'app_group_playlists_bulk_remove', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function bulkRemovePlaylists(UserGroup $group, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_PLAYLISTS, $group);
        $this->validateAjaxCsrf('group_playlists_bulk_'.$group->getId(), $request);

        $removed = 0;
        foreach ($this->ids($request) as $id) {
            $playlist = $em->getRepository(Playlist::class)->find($id);
            if (!$playlist instanceof Playlist) continue;

            $link = $em->getRepository(PlaylistGroup::class)->findOneBy(['group' => $group, 'playlist' => $playlist]);
            if (!$link instanceof PlaylistGroup) continue;

            $em->remove($link);
            ++$removed;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $removed]);
    }

    #[Route('/{groupId}/members/{memberId}/delete', name: 'app_group_member_delete', requirements: ['groupId' => '\\d+', 'memberId' => '\\d+'], methods: ['POST'])]
    public function deleteMember(
        int $groupId,
        int $memberId,
        Request $request,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        $group = $em->getRepository(UserGroup::class)->find($groupId);
        $member = $em->getRepository(GroupMember::class)->find($memberId);

        if (!$group instanceof UserGroup || !$member instanceof GroupMember || $member->getGroup()->getId() !== $group->getId()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(AclPrivilege::GROUP_MANAGE_MEMBERS, $group);

        if (!$this->isCsrfTokenValid('group_member_delete_'.$member->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($member->getRole() === 'owner') {
            $this->denyAccessUnlessGranted(AclPrivilege::GROUP_DELEGATE, $group);
            if ($this->countGroupOwners($group, $em) <= 1) {
                $this->addFlash('error', $translator->trans('groups.member.last_owner', [], 'management'));
                return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
            }
        }

        $em->remove($member);
        $em->flush();

        $this->addFlash('success', $translator->trans('groups.member.deleted', [], 'management'));
        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    private function saveMembership(UserGroup $group, User $user, string $requestedRole, EntityManagerInterface $em, TranslatorInterface $translator): void
    {
        if (!in_array($requestedRole, ['owner', 'manager', 'member'], true)) {
            $this->addFlash('error', $translator->trans('groups.member.invalid_role', [], 'management'));
            return;
        }

        $repo = $em->getRepository(GroupMember::class);
        $member = $repo->findOneBy(['group' => $group, 'user' => $user]) ?? (new GroupMember())->setGroup($group)->setUser($user);
        $memberIsOwner = $member->getId() !== null && $member->getRole() === 'owner';

        if ($requestedRole === 'owner' || $memberIsOwner) {
            $this->denyAccessUnlessGranted(AclPrivilege::GROUP_DELEGATE, $group);
        }

        if ($memberIsOwner && $requestedRole !== 'owner' && $this->countGroupOwners($group, $em) <= 1) {
            $this->addFlash('error', $translator->trans('groups.member.last_owner', [], 'management'));
            return;
        }

        $member->setRole($requestedRole);
        $em->persist($member);
        $em->flush();
        $this->addFlash('success', 'groups.member.saved');
    }

    private function countGroupOwners(UserGroup $group, EntityManagerInterface $em): int
    {
        return $em->getRepository(GroupMember::class)->count(['group' => $group, 'role' => 'owner']);
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $request->request->all('ids')),
            static fn(int $id): bool => $id > 0,
        )));
    }

    private function validateAjaxCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) throw $this->createAccessDeniedException();
        return $user;
    }
}
