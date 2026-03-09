<?php

namespace App\Tests\UI\Entry;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CreateMatchEntryControllerTest extends DbWebTestCase
{
    public function testOwnerCanCreateMatchEntry(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session] = $this->createGroupWithOwnerAndSession($em, 'owner-match-ui@test.com', 'UI Group Match');

        $client->loginUser($owner);

        $url = "/groups/{$group->getId()}/sessions/{$session->getId()}/entries/match/create";
        $client->request('POST', $url, [
            'label' => 'Finale',
            'homeName' => 'Dragons',
            'awayName' => 'Titans',
            'homeScore' => '4',
            'awayScore' => '2',
        ]);

        self::assertResponseRedirects();
    }

    public function testNonMemberCannotAccessMatchCreateForm(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $session] = $this->createGroupWithOwnerAndSession($em, 'owner-no-member@test.com', 'Group ACL Match');

        $nonMember = new User();
        $nonMember->setEmail('non-member-match@test.com');
        $nonMember->setPassword('hashed');
        $nonMember->setCreatedAt(new \DateTimeImmutable());
        $em->persist($nonMember);
        $em->flush();

        $client->loginUser($nonMember);
        $client->request('GET', "/groups/{$group->getId()}/sessions/{$session->getId()}/entries/match/create");

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Session}
     */
    private function createGroupWithOwnerAndSession(EntityManagerInterface $em, string $ownerEmail, string $groupName): array
    {
        $owner = new User();
        $owner->setEmail($ownerEmail);
        $owner->setPassword('hashed');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName($groupName);
        $group->setCreatedBy($owner);
        $group->setCreatedAt(new \DateTimeImmutable());
        $em->persist($group);

        $groupMember = new GroupMember(GroupRole::OWNER);
        $groupMember->setGroup($group);
        $groupMember->setUser($owner);
        $group->addGroupMember($groupMember);
        $owner->addGroupMember($groupMember);
        $em->persist($groupMember);

        $activity = new Activity();
        $activity->setName('Activity '.$groupName);
        $activity->setGroup($group);
        $activity->setCreatedBy($owner);
        $em->persist($activity);

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setPlayedAt(new \DateTimeImmutable());
        $session->setCreatedBy($owner);
        $em->persist($session);

        $em->flush();

        return [$owner, $group, $session];
    }
}
