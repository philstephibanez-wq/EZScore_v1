<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Event\Event;
use App\Domain\Event\EventMode;
use App\Domain\Event\EventParticipant;
use App\Domain\Event\EventParticipantStatus;
use App\Domain\Event\EventStatus;
use App\Domain\Group\GroupMember;
use App\Domain\Group\UserGroup;
use App\Domain\Playlist\Playlist;
use App\Domain\Playlist\PlaylistGroup;
use App\Domain\Playlist\PlaylistInvitation;
use App\Domain\Playlist\PlaylistInvitationStatus;
use App\Domain\User\User;
use App\Security\Acl\AclPrivilege;
use App\Service\EventMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/events')]
final class EventController extends AbstractController
{
    #[Route('', name: 'app_events', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->requireUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('event_create', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $title = trim((string) $request->request->get('title'));
            $startsAt = $this->parseDateTime((string) $request->request->get('starts_at'));

            if ($title === '' || $startsAt === null) {
                $this->addFlash('error', 'event.validation.required');
                return $this->redirectToRoute('app_events', ['_locale' => $request->getLocale()]);
            }

            $event = (new Event($user))
                ->setTitle($title)
                ->setDescription((string) $request->request->get('description'))
                ->setStartsAt($startsAt)
                ->setEndsAt($this->parseDateTime((string) $request->request->get('ends_at')))
                ->setMode(EventMode::tryFrom((string) $request->request->get('mode')) ?? EventMode::Onsite)
                ->setLocation((string) $request->request->get('location'))
                ->setRemoteUrl((string) $request->request->get('remote_url'))
                ->setStatus(EventStatus::Scheduled);

            $em->persist($event);
            $em->flush();

            $em->persist(new EventParticipant($event, $user, EventParticipantStatus::Accepted));
            $em->flush();

            $this->addFlash('success', 'event.created');
            return $this->redirectToRoute('app_event_show', [
                '_locale' => $request->getLocale(),
                'id' => $event->getId(),
            ]);
        }

        $visible = [];
        foreach ($em->getRepository(Event::class)->findBy([], ['startsAt' => 'ASC']) as $event) {
            if ($this->isGranted(AclPrivilege::EVENT_VIEW, $event)) {
                $visible[] = $event;
            }
        }

        $myInvitations = $em->getRepository(EventParticipant::class)->findBy(
            ['user' => $user, 'status' => EventParticipantStatus::Invited],
            ['createdAt' => 'DESC'],
        );

        return $this->render('events/index.html.twig', [
            'events' => $visible,
            'my_invitations' => $myInvitations,
        ]);
    }

    #[Route('/{id}', name: 'app_event_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_VIEW, $event);
        $user = $this->requireUser();

        $participants = $em->getRepository(EventParticipant::class)->findBy(
            ['event' => $event],
            ['status' => 'ASC', 'id' => 'ASC'],
        );

        $myParticipation = $em->getRepository(EventParticipant::class)->findOneBy([
            'event' => $event,
            'user' => $user,
        ]);

        return $this->render('events/show.html.twig', [
            'event' => $event,
            'participants' => $participants,
            'my_participation' => $myParticipation,
            'event_url' => $this->generateUrl(
                'app_event_show',
                ['_locale' => $request->getLocale(), 'id' => $event->getId()],
                0,
            ),
        ]);
    }

    #[Route('/{id}/update', name: 'app_event_update', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function update(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_MANAGE, $event);

        if (!$this->isCsrfTokenValid('event_update_'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $startsAt = $this->parseDateTime((string) $request->request->get('starts_at'));
        if ($startsAt === null) {
            $this->addFlash('error', 'event.validation.required');
            return $this->redirectToRoute('app_event_show', ['_locale' => $request->getLocale(), 'id' => $event->getId()]);
        }

        $event
            ->setTitle((string) $request->request->get('title'))
            ->setDescription((string) $request->request->get('description'))
            ->setStartsAt($startsAt)
            ->setEndsAt($this->parseDateTime((string) $request->request->get('ends_at')))
            ->setMode(EventMode::tryFrom((string) $request->request->get('mode')) ?? EventMode::Onsite)
            ->setLocation((string) $request->request->get('location'))
            ->setRemoteUrl((string) $request->request->get('remote_url'))
            ->setStatus(EventStatus::tryFrom((string) $request->request->get('status')) ?? EventStatus::Scheduled);

        $em->flush();
        $this->addFlash('success', 'event.updated');

        return $this->redirectToRoute('app_event_show', ['_locale' => $request->getLocale(), 'id' => $event->getId()]);
    }

    #[Route('/{id}/group', name: 'app_event_set_group', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function setGroup(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        EventMailer $mailer,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_MANAGE, $event);
        $this->validateAjaxCsrf('event_group_'.$event->getId(), $request);

        $ids = $this->ids($request);
        $group = isset($ids[0]) ? $em->getRepository(UserGroup::class)->find($ids[0]) : null;

        if (!$group instanceof UserGroup || !$this->isGranted(AclPrivilege::GROUP_EDIT, $group)) {
            return $this->json(['ok' => false, 'message' => 'invalid_group'], 422);
        }

        $event->setGroup($group);

        if ($event->getPlaylist() !== null
            && !($em->getRepository(PlaylistGroup::class)->findOneBy([
                'playlist' => $event->getPlaylist(),
                'group' => $group,
            ]) instanceof PlaylistGroup)
            && $this->isGranted(AclPrivilege::PLAYLIST_EDIT, $event->getPlaylist())
            && $this->isGranted(AclPrivilege::GROUP_MANAGE_PLAYLISTS, $group)) {
            $em->persist(new PlaylistGroup($event->getPlaylist(), $group, $this->requireUser()));
        }

        $newParticipants = [];
        foreach ($em->getRepository(GroupMember::class)->findBy(['group' => $group]) as $membership) {
            $member = $membership->getUser();
            if (!$member->isActive()) continue;

            if (!$em->getRepository(EventParticipant::class)->findOneBy(['event' => $event, 'user' => $member]) instanceof EventParticipant) {
                $participant = new EventParticipant(
                    $event,
                    $member,
                    $member->getId() === $event->getCreatedBy()->getId()
                        ? EventParticipantStatus::Accepted
                        : EventParticipantStatus::Invited,
                );
                $em->persist($participant);
                $newParticipants[] = $participant;
            }
        }

        $em->flush();

        foreach ($newParticipants as $participant) {
            if ($participant->getUser()->getId() !== $event->getCreatedBy()->getId()
                && $mailer->sendInvitation($event, $participant->getUser())) {
                $participant->markEmailNotified();
            }
        }
        $em->flush();

        return $this->json(['ok' => true, 'changed' => count($newParticipants)]);
    }

    #[Route('/{id}/playlist', name: 'app_event_set_playlist', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function setPlaylist(Event $event, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_MANAGE, $event);
        $this->validateAjaxCsrf('event_playlist_'.$event->getId(), $request);

        $ids = $this->ids($request);
        $playlist = isset($ids[0]) ? $em->getRepository(Playlist::class)->find($ids[0]) : null;

        if (!$playlist instanceof Playlist || !$this->isGranted(AclPrivilege::PLAYLIST_EDIT, $playlist)) {
            return $this->json(['ok' => false, 'message' => 'invalid_playlist'], 422);
        }

        $event->setPlaylist($playlist);

        if ($event->getGroup() !== null
            && !($em->getRepository(PlaylistGroup::class)->findOneBy([
                'playlist' => $playlist,
                'group' => $event->getGroup(),
            ]) instanceof PlaylistGroup)
            && $this->isGranted(AclPrivilege::GROUP_MANAGE_PLAYLISTS, $event->getGroup())) {
            $em->persist(new PlaylistGroup($playlist, $event->getGroup(), $this->requireUser()));
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => 1]);
    }

    #[Route('/{id}/participants/bulk-add', name: 'app_event_participants_bulk_add', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function addParticipants(
        Event $event,
        Request $request,
        EntityManagerInterface $em,
        EventMailer $mailer,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_INVITE, $event);
        $this->validateAjaxCsrf('event_participants_'.$event->getId(), $request);

        $changed = 0;
        foreach ($this->ids($request) as $id) {
            $user = $em->getRepository(User::class)->find($id);
            if (!$user instanceof User || !$user->isActive()) continue;
            if (!$this->eligibleParticipant($event, $user, $em)) continue;

            if ($em->getRepository(EventParticipant::class)->findOneBy(['event' => $event, 'user' => $user]) instanceof EventParticipant) {
                continue;
            }

            $participant = new EventParticipant($event, $user);
            $em->persist($participant);
            $em->flush();

            if ($mailer->sendInvitation($event, $user)) {
                $participant->markEmailNotified();
                $em->flush();
            }

            ++$changed;
        }

        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    #[Route('/{id}/participants/bulk-remove', name: 'app_event_participants_bulk_remove', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function removeParticipants(Event $event, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_INVITE, $event);
        $this->validateAjaxCsrf('event_participants_'.$event->getId(), $request);

        $changed = 0;
        foreach ($this->ids($request) as $id) {
            $user = $em->getRepository(User::class)->find($id);
            if (!$user instanceof User || $user->getId() === $event->getCreatedBy()->getId()) continue;

            $participant = $em->getRepository(EventParticipant::class)->findOneBy(['event' => $event, 'user' => $user]);
            if (!$participant instanceof EventParticipant) continue;

            $em->remove($participant);
            ++$changed;
        }

        $em->flush();
        return $this->json(['ok' => true, 'changed' => $changed]);
    }

    #[Route('/{id}/rsvp', name: 'app_event_rsvp', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function rsvp(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_VIEW, $event);
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('event_rsvp_'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $participant = $em->getRepository(EventParticipant::class)->findOneBy(['event' => $event, 'user' => $user]);
        if (!$participant instanceof EventParticipant) {
            throw $this->createAccessDeniedException();
        }

        $status = EventParticipantStatus::tryFrom((string) $request->request->get('status'));
        if ($status === null) {
            throw $this->createAccessDeniedException();
        }

        $participant->respond($status);
        $em->flush();

        return $this->redirectToRoute('app_event_show', ['_locale' => $request->getLocale(), 'id' => $event->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_event_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(AclPrivilege::EVENT_MANAGE, $event);

        if (!$this->isCsrfTokenValid('event_delete_'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $em->remove($event);
        $em->flush();

        $this->addFlash('success', 'event.deleted');
        return $this->redirectToRoute('app_events', ['_locale' => $request->getLocale()]);
    }

    private function eligibleParticipant(Event $event, User $user, EntityManagerInterface $em): bool
    {
        if ($event->getGroup() !== null) {
            return $em->getRepository(GroupMember::class)->findOneBy([
                'group' => $event->getGroup(),
                'user' => $user,
            ]) instanceof GroupMember;
        }

        $playlist = $event->getPlaylist();
        if ($playlist === null) return true;
        if ($playlist->isPublic() || $playlist->getOwnerUser()->getId() === $user->getId()) return true;

        if ($em->getRepository(PlaylistInvitation::class)->findOneBy([
            'playlist' => $playlist,
            'invitedUser' => $user,
            'status' => PlaylistInvitationStatus::Accepted,
        ]) instanceof PlaylistInvitation) return true;

        foreach ($em->getRepository(PlaylistGroup::class)->findBy(['playlist' => $playlist]) as $link) {
            if ($em->getRepository(GroupMember::class)->findOneBy([
                'group' => $link->getGroup(),
                'user' => $user,
            ]) instanceof GroupMember) return true;
        }

        return false;
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') return null;

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
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
