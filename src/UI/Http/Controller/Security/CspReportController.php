<?php

namespace App\UI\Http\Controller\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CspReportController
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/csp-report', name: 'security_csp_report', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $rawBody = trim($request->getContent());

        if ($rawBody !== '') {
            try {
                $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $payload = ['raw' => $rawBody];
            }

            if (is_array($payload)) {
                $this->logger->warning('CSP violation report received', [
                    'report' => $payload,
                    'user_agent' => $request->headers->get('User-Agent'),
                    'ip' => $request->getClientIp(),
                ]);
            }
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
