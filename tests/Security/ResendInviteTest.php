<?php

namespace App\Tests\Security;

use App\Domain\Group\GroupRole;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Invite;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ResendInviteTest extends DbWebTestCase
{
    public function testOwnerCanResendInviteWithNewToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$owner, $group, $invite] = $this->createGroupWithInvite($em, 'invite-resend@test.local');
        $oldToken = $invite->getToken();

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/groups/'.$group->getId());
        $token = $crawler->filter('form[action="/groups/'.$group->getId().'/invites/'.$invite->getId().'/resend"] input[name="_token"]')->attr('value');

        $client->request('POST', '/groups/'.$group->getId().'/invites/'.$invite->getId().'/resend', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/groups/'.$group->getId());

        $em->clear();

        $invites = $em->getRepository(Invite::class)->findBy([
            'email' => 'invite-resend@test.local',
        ]);

        self::assertCount(2, $invites);

        $activeInvite = null;
        foreach ($invites as $candidate) {
            if ($candidate->getExpiresAt() > new \DateTimeImmutable()) {
                $activeInvite = $candidate;
                break;
            }
        }

        self::assertNotNull($activeInvite);
        self::assertNotSame($oldToken, $activeInvite->getToken());
    }

    /**
     * @return array{0: User, 1: GameGroup, 2: Invite}
     */
    private function createGroupWithInvite(EntityManagerInterface $em, string $inviteeEmail): array
    {
        $owner = new User();
        $owner->setEmail('owner-resend@test.local');
        $owner->setPassword('dummy');
        $owner->setCreatedAt(new \DateTimeImmutable());
        $em->persist($owner);

        $group = new GameGroup();
        $group->setName('Invite Resend Group');
        $group->setCreatedAt(new \DateTimeImmutable());
        $group->setCreatedBy($owner);
        $em->persist($group);

        $membership = new GroupMember(GroupRole::OWNER);
        $group->addGroupMember($membership);
        $owner->addGroupMember($membership);
        $em->persist($membership);

        $invite = new Invite(GroupRole::MEMBER);
        $invite->setEmail($inviteeEmail);
        $invite->setToken(bin2hex(random_bytes(16)));
        $invite->setExpiresAt(new \DateTimeImmutable('+3 days'));
        $group->addInvite($invite);
        $owner->addInvite($invite);
        $em->persist($invite);
        $em->flush();

        return [$owner, $group, $invite];
    }
}