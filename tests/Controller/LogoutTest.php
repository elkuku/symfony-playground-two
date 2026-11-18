<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LogoutTest extends WebTestCase
{
    public function testLogoutWithoutCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser());

        $client->request('GET', '/logout');

        // A CSRF-less logout request must not end the session.
        $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
    }

    public function testLogoutLinkFromNavBarLogsOut(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUser());

        $crawler = $client->request('GET', '/');
        $logoutUrl = $crawler->filter('a[title="Logout"]')->attr('href');

        self::assertNotNull($logoutUrl);
        self::assertStringContainsString('_csrf_token=', $logoutUrl);

        // Simulate a click from our own site.
        $client->request('GET', $logoutUrl, server: ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        self::assertResponseRedirects('/');

        $client->request('GET', '/profile');
        self::assertResponseRedirects();
    }

    private function getUser(): \App\Entity\User
    {
        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);

        $user = $repository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        return $user;
    }
}
