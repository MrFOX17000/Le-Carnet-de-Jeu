<?php

namespace App\Security;

use App\Application\Auth\AuthenticateWithGoogle\AuthenticateWithGoogleCommand;
use App\Application\Auth\AuthenticateWithGoogle\AuthenticateWithGoogleHandler;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleOAuthAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private UrlGeneratorInterface $urlGenerator,
        private AuthenticateWithGoogleHandler $authenticateWithGoogleHandler,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Continue with OAuth flow only if we're on the callback route
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function() use ($client, $accessToken) {
                $resourceOwner = $client->fetchUserFromToken($accessToken);

                // Extract Google user data
                $googleId = $resourceOwner->getId();
                $email = $resourceOwner->getEmail();
                $name = $resourceOwner->getName();
                $picture = $resourceOwner->getAvatar();

                // Pass to our application handler
                $command = new AuthenticateWithGoogleCommand(
                    email: $email,
                    googleId: $googleId,
                    name: $name,
                    avatarUrl: $picture,
                );

                $result = $this->authenticateWithGoogleHandler->handle($command);
                
                return $result->getUser();
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Redirect to dashboard
        return new RedirectResponse($this->urlGenerator->generate('dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        
        // Redirect back to login with error
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
