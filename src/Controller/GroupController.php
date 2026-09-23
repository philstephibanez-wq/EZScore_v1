<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groups')]
final class GroupController extends AbstractController
{
    #[Route('', name: 'app_groups', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
            if ($this->isCsrfTokenValid('create_group', (string) $request->request->get('_token'))) {
                $name = trim((string) $request->request->get('name'));
                if ($name !== '') {
                    $group = (new UserGroup())->setName($name)->setDescription((string) $request->request->get('description'));
                    $em->persist($group);
                    $em->flush();
                    $this->addFlash('success', 'Groupe créé.');
                    return $this->redirectToRoute('app_groups');
                }
            }
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
        foreach ($members as $member) $byGroup[$member->getGroup()->getId()][] = $member;
        return $this->render('groups/index.html.twig', ['groups' => $groups, 'members_by_group' => $byGroup]);
    }

    #[Route('/{id}/members', name: 'app_group_member_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addMember(UserGroup $group, Request $request, UserRepository $users, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('group_'.$group->getId(), (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $email = mb_strtolower(trim((string) $request->request->get('email')));
        $user = $users->findOneBy(['email' => $email]);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_groups');
        }
        $repo = $em->getRepository(GroupMember::class);
        $member = $repo->findOneBy(['group' => $group, 'user' => $user]) ?? (new GroupMember())->setGroup($group)->setUser($user);
        $member->setRole((string) $request->request->get('role', 'member'));
        $em->persist($member);
        $em->flush();
        $this->addFlash('success', 'Membre enregistré.');
        return $this->redirectToRoute('app_groups');
    }
}
