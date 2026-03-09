<?php

namespace App\Application\Auth\AuthenticateWithGoogle;

use App\Entity\User;

class AuthenticateWithGoogleResult
{
    public function __construct(
        private readonly User $user,
        private readonly bool $isNewUser,
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isNewUser(): bool
    {
        return $this->isNewUser;
    }
}
