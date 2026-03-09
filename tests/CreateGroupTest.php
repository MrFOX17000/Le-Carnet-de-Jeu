<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Domain\Group\GroupRole;
use Doctrine\ORM\EntityManagerInterface;

final class CreateGroupTest extends DbWebTestCase{

    public function testCreateGroupCreatesOwnerMembership(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('test@test.local');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $client->request('POST', '/groups/create', [
            'name' => 'My Test Group',
            'creatorUserId' => $user->getId(),
        ]);

        $group = $em->getRepository(GameGroup::class)
            ->findOneBy(['name' => 'My Test Group']);

        self::assertNotNull($group);

        $member = $em->getRepository(GroupMember::class)
            ->findOneBy([
                'group' => $group,
                'user' => $user,
            ]);

        self::assertNotNull($member);
        self::assertEquals(GroupRole::OWNER, $member->getRole());
    }
}