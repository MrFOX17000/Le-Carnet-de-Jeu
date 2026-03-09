<?php

namespace App\Application\Auth\AuthenticateWithGoogle;

use App\Entity\User;
use App\Infrastructure\Security\OAuth\PasswordGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthenticateWithGoogleHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PasswordGenerator $passwordGenerator,
    ) {
    }

    public function handle(AuthenticateWithGoogleCommand $command): AuthenticateWithGoogleResult
    {
        $email = $command->getEmail();

        // Chercher user existant par email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user) {
            // User existe déjà → connecter via OAuth
            $isNewUser = false;
            
            // Mettre à jour googleId et oauthProvider s'il n'y a pas encore
            if (!$user->getGoogleId()) {
                $user->setGoogleId($command->getGoogleId());
                $user->setOauthProvider('google');
            }
        } else {
            // User n'existe pas → créer
            $isNewUser = true;
            $user = new User();
            $user->setEmail($email);
            $user->setGoogleId($command->getGoogleId());
            $user->setOauthProvider('google');
            
            // Pour OAuth, email est toujours vérifié (puisque Google l'a validé)
            $user->setIsVerified(true);
            
            // Générer un password aléatoire inutilisable (Option A MVP)
            $randomPassword = $this->passwordGenerator->generate();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $randomPassword);
            $user->setPassword($hashedPassword);
            
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        return new AuthenticateWithGoogleResult($user, $isNewUser);
    }
}
