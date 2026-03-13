<?php

namespace App\Tests\UI\Session;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class EditSessionControllerTest extends DbWebTestCase
{
    public function testOwnerCanEditSession(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session, $alternateActivity] = $this->createOwnedSession($em);

        $client->loginUser($owner);
        $client->request('POST', '/groups/'.$group->getId().'/sessions/'.$session->getId().'/edit', [
            'activityId' => $alternateActivity->getId(),
            'title' => 'Session modifiée',
            'playedAt' => '2026-03-10T21:15',
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId().'/sessions/'.$session->getId());

        $em->clear();
        $updatedSession = $em->getRepository(Session::class)->find($session->getId());

        self::assertSame('Session modifiée', $updatedSession?->getTitle());
        self::assertSame($alternateActivity->getId(), $updatedSession?->getActivity()?->getId());
        self::assertSame('2026-03-10 21:15', $updatedSession?->getPlayedAt()?->format('Y-m-d H:i'));
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Session, 3: Activity}
     */
    private function createOwnedSession(EntityManagerInterface $em): array
    {
        $owner = new User();
        $owner->setEmail('edit-session-owner@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Edit Session Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);

        $activity = new Activity();
        $activity->setName('Skyjo');
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $group->addActivity($activity);
        $em->persist($activity);

        $alternateActivity = new Activity();
        $alternateActivity->setName('Échecs');
        $alternateActivity->setGroup($group);
        $alternateActivity->setCreatedBy($owner);
        $group->addActivity($alternateActivity);
        $em->persist($alternateActivity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setTitle('Session initiale');
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-09 20:00:00'));
        $session->setCreatedBy($owner);
        $group->addSession($session);
        $activity->addSession($session);
        $em->persist($session);
        $em->flush();

        return [$owner, $group, $session, $alternateActivity];
    }
}