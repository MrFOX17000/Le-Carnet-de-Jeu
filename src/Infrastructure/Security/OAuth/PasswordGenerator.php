<?php

namespace App\Infrastructure\Security\OAuth;

use Symfony\Component\String\Slugger\SluggerInterface;

class PasswordGenerator
{
    public function __construct(
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Generate a random, unusable password for OAuth users.
     * This password should never be used for login, only hashed and stored.
     */
    public function generate(): string
    {
        // Créer un password aléatoire très long et impossible à retenir
        // Format: oauth_<timestamp>_<random_hash>
        return sprintf(
            'oauth_%s_%s',
            time(),
            bin2hex(random_bytes(32))
        );
    }
}
