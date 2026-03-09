<?php

namespace App\Infrastructure\Security\OAuth;

/**
 * Simulated user provider for Google OAuth.
 * In Phase 2, this will be replaced by actual HTTP calls to Google's OAuth endpoint.
 * For Phase 1 testing, this allows us to mock Google user data.
 */
class GoogleOAuthUserProvider
{
    /**
     * Represents data returned by Google OAuth provider
     */
    public static function forEmail(string $email): array
    {
        return [
            'email' => $email,
            'name' => null,
            'avatarUrl' => null,
        ];
    }

    /**
     * For Phase 2: parse OAuth token response from Google
     * @param array $tokenResponse Raw response from Google OAuth endpoint
     * @return array User data (email, name, avatarUrl, googleId)
     */
    public static function parseGoogleTokenResponse(array $tokenResponse): array
    {
        // This will be implemented in Phase 2
        // For now, it's a placeholder
        return [];
    }
}
