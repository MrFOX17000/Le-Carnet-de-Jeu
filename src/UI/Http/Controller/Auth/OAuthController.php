<?php

namespace App\UI\Http\Controller\Auth;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OAuthController extends AbstractController
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
    ) {
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(): Response
    {
        // Will redirect to Google!
        return $this->clientRegistry
            ->getClient('google')
            ->redirect(
                ['openid', 'email', 'profile'], // the scopes you want to access
                [] // optional, the custom parameters to pass to the provider
            );
    }

    /**
     * Called by the route dispatch via GoogleOAuthAuthenticator
     * This route's name must match the redirect_route in knpu_oauth2_client.yaml
     */
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(): Response
    {
        // This method is intercepted by GoogleOAuthAuthenticator
        // It should never reach here in normal flow
        return $this->redirectToRoute('dashboard');
    }
}
