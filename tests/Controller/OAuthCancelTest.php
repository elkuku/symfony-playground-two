<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OAuthCancelTest extends WebTestCase
{
    /**
     * Cancelling at the provider must lead back to the login page, not to an error page.
     */
    #[TestWith(['/connect/check/github'])]
    #[TestWith(['/connect/check/gitlab'])]
    #[TestWith(['/connect/google/check'])]
    public function testCancelledLoginRedirectsToLoginPage(string $checkPath): void
    {
        $client = static::createClient();

        $client->request('GET', $checkPath.'?error=access_denied');

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('.alert-danger[role="alert"]');
    }
}
