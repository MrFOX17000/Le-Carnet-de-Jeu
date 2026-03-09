<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class LoginUserTest extends DbWebTestCase
{
    public function testLoggedInUserCanAccessDashboard(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail('auth@test.local');
        $user->setPassword('dummy'); // pas utilisé par loginUser()
        $user->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Dashboard', $client->getResponse()->getContent());
    }
}