<?php

namespace App\Tests\Security;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteActivityTest extends DbWebTestCase
{
    public function testOwnerCanDeleteUnusedActivity(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $activity] = $this->createGroupWithActivity($em, 'activity-owner@test.local', false);

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId().'/activities');
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/activities/'.$activity->getId().'/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/activities/'.$activity->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId().'/activities');
        self::assertNull($em->getRepository(Activity::class)->find($activity->getId()));
    }

    public function testOwnerCannotDeleteActivityUsedBySession(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $activity] = $this->createGroupWithActivity($em, 'activity-owner-locked@test.local', true);

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId().'/activities');
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/activities/'.$activity->getId().'/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/activities/'.$activity->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId().'/activities');
        self::assertNotNull($em->getRepository(Activity::class)->find($activity->getId()));
    }

    public function testMemberCannotDeleteActivity(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $activity] = $this->createGroupWithActivity($em, 'activity-owner-forbidden@test.local', false);

        $member = new User();
        $member->setEmail('activity-member@test.local');
        $member->setPassword('dummy');
        $member->setCreatedAt(new \DateTimeImmutable());
        $em->persist($member);

        $membership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($membership);
        $member->addGroupMember($membership);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($member);
        $client->request('POST', '/groups/'.$group->getId().'/activities/'.$activity->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($em->getRepository(Activity::class)->find($activity->getId()));
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Activity}
     */
    private function createGroupWithActivity(EntityManagerInterface $em, string $ownerEmail, bool $attachSession): array
    {
        $owner = new User();
        $owner->setEmail($ownerEmail);
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Activity Delete Group');
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

        if ($attachSession) {
            $session = new Session();
            $session->setActivity($activity);
            $session->setGroup($group);
            $session->setTitle('Session verrouillante');
            $session->setPlayedAt(new \DateTimeImmutable('2026-03-10 20:00:00'));
            $session->setCreatedBy($owner);
            $group->addSession($session);
            $activity->addSession($session);
            $em->persist($session);
        }

        $em->flush();

        return [$owner, $group, $activity];
    }
}