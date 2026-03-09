<?php

namespace App\Application\Auth\AuthenticateWithGoogle;

class AuthenticateWithGoogleCommand
{
    public function __construct(
        private readonly string $email,
        private readonly string $googleId,
        private readonly ?string $name = null,
        private readonly ?string $avatarUrl = null,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getGoogleId(): string
    {
        return $this->googleId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }
}
