<?php

namespace App\Tests\Security;

use App\UI\Http\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testProdEnvironmentAddsEnforcedCspHeader(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $request = Request::create('/login', 'GET');
        $response = new Response('ok');

        $event = new ResponseEvent($this->createKernelStub(), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $headers = $response->headers;
        self::assertNotNull($headers->get('Content-Security-Policy'));
        self::assertNotNull($headers->get('Content-Security-Policy-Report-Only'));
        self::assertStringContainsString("default-src 'self'", (string) $headers->get('Content-Security-Policy'));
    }

    private function createKernelStub(): HttpKernelInterface
    {
        return new class () implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        };
    }
}
