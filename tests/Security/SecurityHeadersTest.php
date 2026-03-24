<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersTest extends WebTestCase
{
    public function testSecurityHeadersArePresentOnLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();

        $headers = $client->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=()', $headers->get('Permissions-Policy'));

        $cspReportOnly = $headers->get('Content-Security-Policy-Report-Only');
        self::assertNotNull($cspReportOnly);
        self::assertStringContainsString("default-src 'self'", $cspReportOnly);
        self::assertStringContainsString('report-uri /csp-report', $cspReportOnly);
    }
}
