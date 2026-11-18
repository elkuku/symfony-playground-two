<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginPageTest extends WebTestCase
{
    public function testShowsLoginOptionsWhenLoggedOut(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card h1', 'Sign in');
        self::assertSelectorExists('a[href="/connect/google"]');
        self::assertSelectorExists('a[href="/connect/github"]');
        self::assertSelectorExists('a[href="/connect/gitlab"]');
    }

    public function testRedirectsToHomepageWhenLoggedIn(): void
    {
        $client = static::createClient();

        /** @var UserRepository $repository */
        $repository = static::getContainer()->get(UserRepository::class);
        $user = $repository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/login');

        self::assertResponseRedirects('/');
    }
}
