<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HelloControllerTest extends WebTestCase
{
    public function testHelloPageIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/hello');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Suivez vos sessions en groupe');
    }
}