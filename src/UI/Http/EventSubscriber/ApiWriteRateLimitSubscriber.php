<?php

namespace App\UI\Http\EventSubscriber;

use App\Entity\User;
use App\UI\Http\Response\JsonErrorResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ApiWriteRateLimitSubscriber implements EventSubscriberInterface
{
    private const API_WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        #[Autowire(service: 'limiter.api_write')]
        private readonly RateLimiterFactory $apiWriteLimiter,
        private readonly Security $security,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if (!in_array($request->getMethod(), self::API_WRITE_METHODS, true)) {
            return;
        }

        $limiter = $this->apiWriteLimiter->create($this->resolveLimiterKey($request));
        $limit = $limiter->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        $response = JsonErrorResponse::create('too_many_requests', 429);
        $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
        $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());

        $retryAfter = $limit->getRetryAfter();
        if ($retryAfter instanceof \DateTimeInterface) {
            $seconds = max(1, $retryAfter->getTimestamp() - time());
            $response->headers->set('Retry-After', (string) $seconds);
        }

        $event->setResponse($response);
    }

    private function resolveLimiterKey(Request $request): string
    {
        $user = $this->security->getUser();
        $actor = $user instanceof User ? 'user:' . $user->getId() : 'ip:' . ($request->getClientIp() ?? 'unknown');
        $route = (string) ($request->attributes->get('_route') ?? 'api_unknown_route');

        if ($this->environment === 'test') {
            $testKey = trim((string) $request->headers->get('X-RateLimit-Test-Key', ''));
            if ($testKey !== '') {
                return sprintf('%s|%s|test:%s', $actor, $route, $testKey);
            }
        }

        return sprintf('%s|%s', $actor, $route);
    }
}
