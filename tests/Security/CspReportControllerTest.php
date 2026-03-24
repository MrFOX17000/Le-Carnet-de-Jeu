<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CspReportControllerTest extends WebTestCase
{
    public function testCspReportEndpointIsPublicAndReturnsNoContent(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/csp-report',
            server: [
                'CONTENT_TYPE' => 'application/csp-report',
            ],
            content: json_encode([
                'csp-report' => [
                    'document-uri' => 'http://localhost/login',
                    'violated-directive' => "script-src 'self'",
                    'blocked-uri' => 'https://example.com/evil.js',
                ],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(204);
        self::assertSame('', (string) $client->getResponse()->getContent());
    }
}
