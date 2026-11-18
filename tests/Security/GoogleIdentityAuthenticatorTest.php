<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\GoogleIdentityAuthenticator;
use App\Security\SocialUserResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AllowMockObjectsWithoutExpectations]
class GoogleIdentityAuthenticatorTest extends TestCase
{
    private GoogleIdentityAuthenticator $authenticator;

    private SocialUserResolver&\PHPUnit\Framework\MockObject\Stub $userResolver;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->userResolver = $this->createStub(SocialUserResolver::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->authenticator = new GoogleIdentityAuthenticator(
            'test-google-client-id',
            $this->userResolver,
            $this->urlGenerator,
        );
    }

    public function testSupportsGoogleVerifyPath(): void
    {
        self::assertTrue($this->authenticator->supports(Request::create('/connect/google/verify')));
    }

    public function testDoesNotSupportOtherPaths(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/')));
        self::assertFalse($this->authenticator->supports(Request::create('/connect/google/check')));
    }

    public function testAuthenticateThrowsOnEmptyCredential(): void
    {
        $request = new Request([], ['credential' => '']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessageIsOrContains('Missing credentials');
        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnZeroCredential(): void
    {
        $request = new Request([], ['credential' => '0']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessageIsOrContains('Missing credentials');
        $this->authenticator->authenticate($request);
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/profile');

        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main'
        );

        self::assertSame('/profile', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessRedirectsToDefault(): void
    {
        $this->urlGenerator->expects(self::once())->method('generate')->with('app_default')->willReturn('/');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createStub(TokenInterface::class),
            'main'
        );

        self::assertSame('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureAddsFlashAndRedirects(): void
    {
        $this->urlGenerator->expects(self::once())->method('generate')->with('app_login')->willReturn('/login');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException('Invalid token.')
        );

        self::assertSame('/login', $response->getTargetUrl());
    }
}
