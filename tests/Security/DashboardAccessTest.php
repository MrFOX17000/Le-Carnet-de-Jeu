<?php

namespace App\Tests\Security;

use App\Tests\DbWebTestCase;

final class DashboardAccessTest extends DbWebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }
}