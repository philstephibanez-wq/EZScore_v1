<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/groups')]
final class GroupController extends AbstractController
{
    #[Route('', name: 'app_groups', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

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

            $em->persist($group);
            $em->flush();

            $this->addFlash('success', 'groups.created');
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $groups = $em->getRepository(UserGroup::class)->findBy([], ['name' => 'ASC']);
        } else {
            $memberships = $em->getRepository(GroupMember::class)->findBy(['user' => $this->getUser()]);
            $groups = array_map(static fn(GroupMember $m): UserGroup => $m->getGroup(), $memberships);
            usort($groups, static fn(UserGroup $a, UserGroup $b): int => strcasecmp($a->getName(), $b->getName()));
        }

        $members = $groups === [] ? [] : $em->getRepository(GroupMember::class)->findBy(['group' => $groups]);
        $byGroup = [];
        foreach ($members as $member) {
            $byGroup[$member->getGroup()->getId()][] = $member;
        }

        $editable = [];
        $deletable = [];
        foreach ($groups as $group) {
            $editable[(int) $group->getId()] = $this->canEditGroup($group, $em);
            $deletable[(int) $group->getId()] = $this->canDeleteGroup($group, $em);
        }

        return $this->render('groups/index.html.twig', [
            'groups' => $groups,
            'members_by_group' => $byGroup,
            'editable_groups' => $editable,
            'deletable_groups' => $deletable,
        ]);
    }

    #[Route('/{id}/update', name: 'app_group_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(UserGroup $group, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        if (!$this->canEditGroup($group, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('group_update_'.$group->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = trim((string) $request->request->get('name'));
        if ($name === '') {
            $this->addFlash('error', 'groups.validation.name');
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        $group
            ->setName($name)
            ->setDescription((string) $request->request->get('description'));

        $em->flush();
        $this->addFlash('success', $translator->trans('groups.updated', [], 'management'));

        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/delete', name: 'app_group_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(UserGroup $group, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        if (!$this->canDeleteGroup($group, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('group_delete_'.$group->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        foreach ($em->getRepository(Playlist::class)->findBy([
            'ownerType' => 'group',
            'ownerId' => (int) $group->getId(),
        ]) as $playlist) {
            $em->remove($playlist);
        }

        foreach ($em->getRepository(GroupMember::class)->findBy(['group' => $group]) as $membership) {
            $em->remove($membership);
        }

        $em->remove($group);
        $em->flush();

        $this->addFlash('success', $translator->trans('groups.deleted', [], 'management'));
        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/members', name: 'app_group_member_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addMember(
        UserGroup $group,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->canEditGroup($group, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('group_'.$group->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $user = $users->findOneBy(['email' => $email]);

        if ($user === null) {
            $this->addFlash('error', 'groups.member.not_found');
            return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
        }

        $repo = $em->getRepository(GroupMember::class);
        $member = $repo->findOneBy(['group' => $group, 'user' => $user])
            ?? (new GroupMember())->setGroup($group)->setUser($user);

        $requestedRole = (string) $request->request->get('role', 'member');
        if (!$this->isGranted('ROLE_ADMIN')
            && $requestedRole === 'owner'
            && !$this->currentUserIsGroupOwner($group, $em)) {
            throw $this->createAccessDeniedException();
        }

        $member->setRole($requestedRole);
        $em->persist($member);
        $em->flush();

        $this->addFlash('success', 'groups.member.saved');
        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
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

        if (!$group instanceof UserGroup
            || !$member instanceof GroupMember
            || $member->getGroup()->getId() !== $group->getId()) {
            throw $this->createNotFoundException();
        }

        if (!$this->canEditGroup($group, $em)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('group_member_delete_'.$member->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isGranted('ROLE_ADMIN')
            && $member->getRole() === 'owner'
            && !$this->currentUserIsGroupOwner($group, $em)) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($member);
        $em->flush();

        $this->addFlash('success', $translator->trans('groups.member.deleted', [], 'management'));
        return $this->redirectToRoute('app_groups', ['_locale' => $request->getLocale()]);
    }

    private function canEditGroup(UserGroup $group, EntityManagerInterface $em): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        return $membership instanceof GroupMember
            && in_array($membership->getRole(), ['owner', 'manager'], true);
    }

    private function canDeleteGroup(UserGroup $group, EntityManagerInterface $em): bool
    {
        return $this->isGranted('ROLE_ADMIN') || $this->currentUserIsGroupOwner($group, $em);
    }

    private function currentUserIsGroupOwner(UserGroup $group, EntityManagerInterface $em): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $em->getRepository(GroupMember::class)->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        return $membership instanceof GroupMember && $membership->getRole() === 'owner';
    }
}
