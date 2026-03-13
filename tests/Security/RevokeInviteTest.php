<?php

namespace App\Tests\Security;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Invite;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class RevokeInviteTest extends DbWebTestCase
{
    public function testOwnerCanRevokePendingInvite(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $invite] = $this->createGroupWithInvite($em, 'owner-revoke@test.local', 'invitee-revoke@test.local');

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId());
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/invites/'.$invite->getId().'/revoke"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/invites/'.$invite->getId().'/revoke', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId());
        self::assertNull($em->getRepository(Invite::class)->find($invite->getId()));
    }

    public function testMemberCannotRevokePendingInvite(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $invite] = $this->createGroupWithInvite($em, 'owner-revoke-forbidden@test.local', 'invitee-revoke-forbidden@test.local');

        $member = new User();
        $member->setEmail('member-revoke@test.local');
        $member->setPassword('dummy');
        $member->setCreatedAt(new \DateTimeImmutable());
        $em->persist($member);

        $membership = new GroupMember(GroupRole::MEMBER);
        $group->addGroupMember($membership);
        $member->addGroupMember($membership);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($member);
        $client->request('POST', '/groups/'.$group->getId().'/invites/'.$invite->getId().'/revoke', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($em->getRepository(Invite::class)->find($invite->getId()));
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Invite}
     */
    private function createGroupWithInvite(EntityManagerInterface $em, string $ownerEmail, string $inviteEmail): array
    {
        $owner = new User();
        $owner->setEmail($ownerEmail);
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Invite Revoke Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);

        $invite = new Invite(GroupRole::MEMBER);
        $invite->setEmail($inviteEmail);
        $invite->setToken(bin2hex(random_bytes(16)));
        $invite->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $invite->setGroup($group);
        $invite->setCreatedBy($owner);

        $group->addInvite($invite);
        $owner->addInvite($invite);

        $em->persist($invite);
        $em->flush();

        return [$owner, $group, $invite];
    }
}