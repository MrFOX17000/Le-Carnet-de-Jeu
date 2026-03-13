<?php

namespace App\Tests\Member;

use App\Domain\Group\GroupRole;
use App\Entity\Activity;
use App\Entity\GameGroup;
use App\Entity\GroupMember;
use App\Entity\Session;
use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MyProfileControllerTest extends DbWebTestCase
{
    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profil');

        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserSeesProfileOverview(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('profile-user@example.com');
        $user->setDisplayName('Renard Test');
        $user->setPassword('dummy');
        $user->setCreatedAt(new \DateTimeImmutable('2026-03-01 10:00:00'));

        $group = new GameGroup();
        $group->setName('Groupe Profil');
        $group->setCreatedBy($user);
        $group->setCreatedAt(new \DateTimeImmutable('2026-03-01 10:30:00'));

        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setGroup($group);
        $membership->setUser($user);

        $activity = new Activity();
        $activity->setName('Skyjo');
        $activity->setGroup($group);
        $activity->setCreatedBy($user);
        $activity->setCreatedAt(new \DateTimeImmutable('2026-03-02 14:00:00'));

        $session = new Session();
        $session->setActivity($activity);
        $session->setGroup($group);
        $session->setCreatedBy($user);
        $session->setTitle('Session Profil Test');
        $session->setPlayedAt(new \DateTimeImmutable('2026-03-03 20:00:00'));

        $em->persist($user);
        $em->persist($group);
        $em->persist($membership);
        $em->persist($activity);
        $em->persist($session);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/profil');

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Mon compte', $content);
        self::assertStringContainsString('Renard Test', $content);
        self::assertStringContainsString('profile-user@example.com', $content);
        self::assertStringContainsString('Groupe Profil', $content);
        self::assertStringContainsString('Session Profil Test', $content);
    }

    public function testUserCanUpdateDisplayNameFromProfile(): void
    {
        $client = static::createClient();

        $user = $this->createTestUser('display-profile@example.com', 'Secret123!');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil');
        $token = (string) $crawler->filterXPath('//form[input[@name="profile_action" and @value="update_display_name"]]/input[@name="_token"]')->attr('value');

        $client->request('POST', '/profil', [
            'profile_action' => 'update_display_name',
            '_token' => $token,
            'display_name' => 'Capitaine RL',
        ]);

        self::assertResponseRedirects('/profil');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $updated = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('Capitaine RL', $updated->getDisplayName());
    }

    public function testUserCanUpdateEmailFromProfile(): void
    {
        $client = static::createClient();

        $user = $this->createTestUser('before-profile@example.com', 'Secret123!');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil');
        $token = (string) $crawler->filterXPath('//form[input[@name="profile_action" and @value="update_email"]]/input[@name="_token"]')->attr('value');

        $client->request('POST', '/profil', [
            'profile_action' => 'update_email',
            '_token' => $token,
            'new_email' => 'after-profile@example.com',
            'current_password_for_email' => 'Secret123!',
        ]);

        self::assertResponseRedirects('/profil');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $updated = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $updated);
        self::assertSame('after-profile@example.com', $updated->getEmail());
    }

    public function testUserCanUpdatePasswordFromProfile(): void
    {
        $client = static::createClient();

        $user = $this->createTestUser('pwd-profile@example.com', 'Secret123!');
        $client->loginUser($user);

        $crawler = $client->request('GET', '/profil');
        $token = (string) $crawler->filterXPath('//form[input[@name="profile_action" and @value="update_password"]]/input[@name="_token"]')->attr('value');

        $client->request('POST', '/profil', [
            'profile_action' => 'update_password',
            '_token' => $token,
            'current_password' => 'Secret123!',
            'new_password' => 'NouveauPass456!',
            'confirm_password' => 'NouveauPass456!',
        ]);

        self::assertResponseRedirects('/profil');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $updated = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $updated);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($updated, 'NouveauPass456!'));
    }

    public function testAccountDeletionIsBlockedWhenUserStillHasData(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createTestUser('delete-blocked@example.com', 'Secret123!');

        $group = new GameGroup();
        $group->setName('Blocage suppression');
        $group->setCreatedBy($user);
        $group->setCreatedAt(new \DateTimeImmutable());

        $membership = new GroupMember(GroupRole::OWNER);
        $membership->setGroup($group);
        $membership->setUser($user);

        $em->persist($group);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/profil');

        self::assertResponseIsSuccessful();

        // Le formulaire de suppression ne doit PAS apparaître quand des données bloquent.
        $deleteForm = $crawler->filterXPath('//form[input[@name="profile_action" and @value="delete_account"]]');
        self::assertCount(0, $deleteForm);

        // La checklist doit contenir des éléments bloqués.
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('blocked', $content);

        // Même avec un POST direct, le CSRF invalide doit bloquer et l'utilisateur reste en base.
        $client->request('POST', '/profil', [
            'profile_action' => 'delete_account',
            '_token' => 'invalid-token',
            'delete_confirmation' => 'SUPPRIMER',
            'current_password_for_delete' => 'Secret123!',
        ]);

        self::assertResponseRedirects('/profil');

        $em->clear();
        $stillExists = $em->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $stillExists);
    }
}
