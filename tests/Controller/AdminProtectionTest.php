<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminProtectionTest extends WebTestCase
{
    public function testAdminRoutesRequireHttpBasicAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(401);
        self::assertTrue($client->getResponse()->headers->has('www-authenticate'));

        $client->request('GET', '/admin', [], [], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'change-me',
        ]);

        self::assertResponseRedirects('/admin/chat-session');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('AgentTag', (string) $client->getResponse()->getContent());
    }
}
