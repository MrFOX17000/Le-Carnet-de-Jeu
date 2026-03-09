<?php

namespace App\UI\Http\Controller\Session;

use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicSharedSessionController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessionRepository,
    ) {
    }

    #[Route('/s/{token}', name: 'public_shared_session', methods: ['GET'])]
    public function __invoke(string $token): Response
    {
        // Chercher la session par son token
        $session = $this->sessionRepository->findOneBy([
            'unlistedToken' => $token,
        ]);

        if (null === $session) {
            throw $this->createNotFoundException('Shared session not found.');
        }

        return $this->render('session/public_show.html.twig', [
            'session' => $session,
        ]);
    }
}
