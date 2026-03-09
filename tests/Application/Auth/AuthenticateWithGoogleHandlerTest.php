<?php

namespace App\Tests\Application\Auth;

use App\Application\Auth\AuthenticateWithGoogle\AuthenticateWithGoogleCommand;
use App\Application\Auth\AuthenticateWithGoogle\AuthenticateWithGoogleHandler;
use App\Entity\User;
use App\Infrastructure\Security\OAuth\PasswordGenerator;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthenticateWithGoogleHandlerTest extends KernelTestCase
{
    private AuthenticateWithGoogleHandler $handler;
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->handler = $container->get(AuthenticateWithGoogleHandler::class);
        $this->userRepository = $container->get(UserRepository::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    /**
     * Test: Create new user when Google email doesn't exist
     */
    public function testCreateNewUserForUnknownGoogleEmail(): void
    {
        $email = 'newuser-' . uniqid() . '@google.com';
        $googleId = 'google_' . uniqid();

        $command = new AuthenticateWithGoogleCommand(
            email: $email,
            googleId: $googleId,
            name: 'Test User',
            avatarUrl: 'https://example.com/avatar.jpg',
        );

        $result = $this->handler->handle($command);

        $this->assertTrue($result->isNewUser());
        $this->assertNotNull($result->getUser()->getId());
        
        $user = $result->getUser();
        $this->assertSame($email, $user->getEmail());
        $this->assertSame($googleId, $user->getGoogleId());
        $this->assertSame('google', $user->getOauthProvider());
        $this->assertTrue($user->isVerified());
        
        // Verify password is hashed
        $this->assertNotNull($user->getPassword());
        $this->assertNotSame('', $user->getPassword());
    }

    /**
     * Test: Connect existing user when email already exists
     */
    public function testConnectExistingUserWithSameEmail(): void
    {
        // Create initial user
        $email = 'existing-' . uniqid() . '@example.com';
        $existingUser = new User();
        $existingUser->setEmail($email);
        $existingUser->setPassword($this->passwordHasher->hashPassword($existingUser, 'password123'));
        $existingUser->setIsVerified(false);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($existingUser);
        $em->flush();
        $existingUserId = $existingUser->getId();

        // Now authenticate via Google with same email
        $googleId = 'google_' . uniqid();
        $command = new AuthenticateWithGoogleCommand(
            email: $email,
            googleId: $googleId,
        );

        $result = $this->handler->handle($command);

        $this->assertFalse($result->isNewUser());
        $this->assertSame($existingUserId, $result->getUser()->getId());
        $this->assertSame($googleId, $result->getUser()->getGoogleId());
        $this->assertSame('google', $result->getUser()->getOauthProvider());
    }

    /**
     * Test: No duplicate users - same email = same account
     */
    public function testNoDuplicateUsersForSameEmail(): void
    {
        $email = 'duplicate-test-' . uniqid() . '@example.com';
        $googleId1 = 'google_' . uniqid();
        $googleId2 = 'google_' . uniqid();

        // First OAuth attempt creates user
        $command1 = new AuthenticateWithGoogleCommand(
            email: $email,
            googleId: $googleId1,
        );
        $result1 = $this->handler->handle($command1);
        $userId1 = $result1->getUser()->getId();

        // Second OAuth attempt with same email but different googleId
        $command2 = new AuthenticateWithGoogleCommand(
            email: $email,
            googleId: $googleId2,
        );
        $result2 = $this->handler->handle($command2);

        $this->assertFalse($result2->isNewUser());
        $this->assertSame($userId1, $result2->getUser()->getId());
        // googleId should not have been updated (already set)
        $this->assertSame($googleId1, $result2->getUser()->getGoogleId());
        
        // Verify only one user in database for this email
        $usersByEmail = $this->userRepository->findBy(['email' => $email]);
        $this->assertCount(1, $usersByEmail);
    }

    /**
     * Test: OAuth user is marked as verified
     */
    public function testOAuthUserIsMarkedAsVerified(): void
    {
        $email = 'verified-' . uniqid() . '@google.com';
        $googleId = 'google_' . uniqid();

        $command = new AuthenticateWithGoogleCommand(
            email: $email,
            googleId: $googleId,
        );

        $result = $this->handler->handle($command);
        $user = $result->getUser();

        $this->assertTrue($user->isVerified());
    }

    /**
     * Test: Password generator creates truly random passwords
     */
    public function testPasswordGeneratorCreatesRandomPasswords(): void
    {
        $passwordGenerator = static::getContainer()->get(PasswordGenerator::class);
        
        $password1 = $passwordGenerator->generate();
        $password2 = $passwordGenerator->generate();
        
        $this->assertNotSame($password1, $password2);
        $this->assertStringStartsWith('oauth_', $password1);
        $this->assertStringStartsWith('oauth_', $password2);
    }
}
