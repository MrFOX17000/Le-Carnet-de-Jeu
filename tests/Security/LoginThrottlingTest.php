<?php

namespace App\Tests\Security;

use App\Tests\DbWebTestCase;

final class LoginThrottlingTest extends DbWebTestCase
{
    public function testLoginIsThrottledAfterTooManyFailedAttempts(): void
    {
        $client = static::createClient();
        $email = sprintf('throttle-%s@test.local', bin2hex(random_bytes(4)));

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            $crawler = $client->request('GET', '/login');
            self::assertResponseIsSuccessful();

            $form = $crawler->filter('form')->form([
                'email' => $email,
                'password' => 'wrong-password',
            ]);

            $client->submit($form);
            self::assertResponseRedirects('/login');
            $client->followRedirect();
            self::assertResponseIsSuccessful();
            $content = (string) $client->getResponse()->getContent();
            self::assertTrue(
                str_contains($content, 'Invalid credentials.') || str_contains($content, 'Identifiants invalides'),
                'Le message d\'identifiants invalides devrait être présent en FR ou EN.'
            );
        }

        $crawler = $client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form([
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/login');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertTrue(
            str_contains($content, 'Trop de tentatives échouées') || str_contains($content, 'Too many failed login attempts'),
            'Le message de limitation des tentatives devrait être présent en FR ou EN.'
        );
    }
}
