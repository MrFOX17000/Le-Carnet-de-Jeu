<?php

namespace App\Tests\UI\Group;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteGroupControllerTest extends DbWebTestCase
{
    public function testOwnerCanDeleteGroup(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group] = $this->createOwnedGroup($em, 'delete-owner@test.local', 'Delete Group');

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId());
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/delete"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/delete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups');
        self::assertNull($em->getRepository(GameGroup::class)->find($group->getId()));
    }

    public function testMemberCannotDeleteGroup(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group] = $this->createOwnedGroup($em, 'owner-delete-forbidden@test.local', 'Protected Group');

        $member = new User();
        $member->setEmail('member-delete-forbidden@test.local');
        $member->setPassword('dummy');
        $member->setCreatedAt(new \DateTimeImmutable());
        $em->persist($member);

        $membership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($membership);
        $member->addGroupMember($membership);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($member);
        $client->request('POST', '/groups/'.$group->getId().'/delete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($em->getRepository(GameGroup::class)->find($group->getId()));
    }

    /**
     * @return array{0: User, 1: GameGroup}
     */
    private function createOwnedGroup(EntityManagerInterface $em, string $email, string $groupName): array
    {
        $owner = new User();
        $owner->setEmail($email);
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName($groupName);
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);
        $em->flush();

        return [$owner, $group];
    }
}