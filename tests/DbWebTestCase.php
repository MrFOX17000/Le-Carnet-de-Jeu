<?php

namespace App\Tests;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class DbWebTestCase extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $testDbPath = $projectDir . '/var/test.db';

        if (file_exists($testDbPath)) {
            @unlink($testDbPath);
        }

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--env' => 'test',
            '-n' => true,
        ]));

        self::ensureKernelShutdown();
    }

    /**
     * Helper method to create a test user with an email and password
     */
    protected function createTestUser(string $email, string $password = 'password123'): User
    {
        $user = new User();
        $user->setEmail($email);

        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }
}