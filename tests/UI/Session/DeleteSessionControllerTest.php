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

final class DeleteSessionControllerTest extends DbWebTestCase
{
    public function testOwnerCanDeleteSession(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session] = $this->createOwnedSession($em, 'session-delete-owner@test.local');

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId().'/sessions/'.$session->getId());
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/sessions/'.$session->getId().'/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/sessions/'.$session->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId());
        self::assertNull($em->getRepository(Session::class)->find($session->getId()));
    }

    public function testMemberCannotDeleteSession(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session] = $this->createOwnedSession($em, 'session-delete-protected@test.local');

        $member = new User();
        $member->setEmail('session-delete-member@test.local');
        $member->setPassword('dummy');
        $member->setCreatedAt(new \DateTimeImmutable());
        $em->persist($member);

        $membership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($membership);
        $member->addGroupMember($membership);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($member);
        $client->request('POST', '/groups/'.$group->getId().'/sessions/'.$session->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($em->getRepository(Session::class)->find($session->getId()));
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Session}
     */
    private function createOwnedSession(EntityManagerInterface $em, string $ownerEmail): array
    {
        $owner = new User();
        $owner->setEmail($ownerEmail);
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Delete Session Group');
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
        $em->persist($activity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setTitle('Session à supprimer');
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-09 20:00:00'));
        $session->setCreatedBy($owner);
        $group->addSession($session);
        $activity->addSession($session);
        $em->persist($session);
        $em->flush();

        return [$owner, $group, $session];
    }
}