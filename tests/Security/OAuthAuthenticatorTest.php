<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\AbstractOAuthAuthenticator;
use App\Security\GitHubAuthenticator;
use App\Security\GitLabAuthenticator;
use App\Security\GoogleAuthenticator;
use App\Security\SocialUserResolver;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Exception\MissingAuthorizationCodeException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

#[AllowMockObjectsWithoutExpectations]
class OAuthAuthenticatorTest extends TestCase
{
    private ClientRegistry&MockObject $clientRegistry;

    private SocialUserResolver&MockObject $userResolver;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->clientRegistry = $this->createMock(ClientRegistry::class);
        $this->userResolver = $this->createMock(SocialUserResolver::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    }

    /**
     * @return iterable<string, array{class-string<AbstractOAuthAuthenticator>, string, string, ResourceOwnerInterface, non-empty-string}>
     */
    public static function providers(): iterable
    {
        yield 'GitHub' => [GitHubAuthenticator::class, 'github', '/connect/check/github', new GithubResourceOwner(['id' => 1, 'login' => 'octocat']), 'fromGitHub'];
        yield 'GitLab' => [GitLabAuthenticator::class, 'gitlab', '/connect/check/gitlab', new GitlabResourceOwner(['id' => 1, 'is_admin' => false, 'name' => 'Tanuki', 'username' => 'tanuki', 'email' => '', 'avatar_url' => '', 'web_url' => '', 'state' => 'active', 'external' => false], new AccessToken(['access_token' => 'token'])), 'fromGitLab'];
        yield 'Google' => [GoogleAuthenticator::class, 'google', '/connect/google/check', new GoogleUser(['sub' => '1', 'email' => 'someone@gmail.com']), 'fromGoogle'];
    }

    /**
     * @return iterable<string, array{class-string<AbstractOAuthAuthenticator>, string, string}>
     */
    public static function checkPaths(): iterable
    {
        foreach (self::providers() as $name => [$class, $clientName, $checkPath]) {
            yield $name => [$class, $clientName, $checkPath];
        }
    }

    /**
     * @param class-string<AbstractOAuthAuthenticator> $class
     */
    #[DataProvider('checkPaths')]
    public function testSupportsOnlyItsCheckPath(string $class, string $clientName, string $checkPath): void
    {
        $authenticator = $this->create($class);

        self::assertTrue($authenticator->supports(Request::create($checkPath)));
        self::assertFalse($authenticator->supports(Request::create('/')));
        self::assertFalse($authenticator->supports(Request::create('/connect/google/verify')));
    }

    /**
     * @param class-string<AbstractOAuthAuthenticator> $class
     * @param non-empty-string                         $resolverMethod
     */
    #[DataProvider('providers')]
    public function testAuthenticateResolvesTheUser(string $class, string $clientName, string $checkPath, ResourceOwnerInterface $owner, string $resolverMethod): void
    {
        $accessToken = new AccessToken(['access_token' => 'token']);

        $client = $this->createMock(OAuth2ClientInterface::class);
        $client->expects(self::once())->method('getAccessToken')->willReturn($accessToken);
        $client->expects(self::once())->method('fetchUserFromToken')->with($accessToken)->willReturn($owner);
        $this->clientRegistry->expects(self::once())->method('getClient')->with($clientName)->willReturn($client);

        $this->userResolver->expects(self::once())->method($resolverMethod)->with($owner)
            ->willReturn(new User()->setIdentifier('resolved'));

        $passport = $this->create($class)->authenticate(Request::create($checkPath));

        self::assertSame('resolved', $passport->getBadge(UserBadge::class)?->getUserIdentifier());
    }

    /**
     * @param class-string<AbstractOAuthAuthenticator> $class
     */
    #[DataProvider('checkPaths')]
    public function testCancelledLoginIsAnAuthenticationFailure(string $class, string $clientName, string $checkPath): void
    {
        $client = $this->createStub(OAuth2ClientInterface::class);
        $client->method('getAccessToken')->willThrowException(new MissingAuthorizationCodeException('No code'));
        $this->clientRegistry->method('getClient')->willReturn($client);

        $this->expectException(AuthenticationException::class);
        $this->create($class)->authenticate(Request::create($checkPath));
    }

    /**
     * @param class-string<AbstractOAuthAuthenticator> $class
     */
    #[DataProvider('checkPaths')]
    public function testProviderErrorIsAnAuthenticationFailure(string $class, string $clientName, string $checkPath): void
    {
        $client = $this->createStub(OAuth2ClientInterface::class);
        $client->method('getAccessToken')->willThrowException(new IdentityProviderException('Bad request', 400, []));
        $this->clientRegistry->method('getClient')->willReturn($client);

        $this->expectException(AuthenticationException::class);
        $this->create($class)->authenticate(Request::create($checkPath));
    }

    /**
     * @param class-string<AbstractOAuthAuthenticator> $class
     */
    #[DataProvider('checkPaths')]
    public function testUnexpectedResourceOwnerIsRejected(string $class, string $clientName, string $checkPath): void
    {
        $client = $this->createStub(OAuth2ClientInterface::class);
        $client->method('getAccessToken')->willReturn(new AccessToken(['access_token' => 'token']));
        $client->method('fetchUserFromToken')->willReturn($this->createStub(ResourceOwnerInterface::class));
        $this->clientRegistry->method('getClient')->willReturn($client);

        $this->expectException(\UnexpectedValueException::class);
        $this->create($class)->authenticate(Request::create($checkPath));
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/dashboard');

        $request = new Request();
        $request->setSession($session);

        $response = $this->create(GitHubAuthenticator::class)
            ->onAuthenticationSuccess($request, $this->createStub(TokenInterface::class), 'main');

        self::assertSame('/dashboard', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessRedirectsToDefault(): void
    {
        $this->urlGenerator->expects(self::once())->method('generate')->with('app_default')->willReturn('/');
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->create(GitHubAuthenticator::class)
            ->onAuthenticationSuccess($request, $this->createStub(TokenInterface::class), 'main');

        self::assertSame('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureAddsFlashAndRedirectsToLogin(): void
    {
        $this->urlGenerator->expects(self::once())->method('generate')->with('app_login')->willReturn('/login');
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $response = $this->create(GitHubAuthenticator::class)
            ->onAuthenticationFailure($request, new AuthenticationException('Bad credentials.'));

        self::assertSame('/login', $response->getTargetUrl());
        self::assertNotEmpty($session->getFlashBag()->get('danger'));
    }

    /**
     * @param class-string<AbstractOAuthAuthenticator> $class
     */
    private function create(string $class): AbstractOAuthAuthenticator
    {
        return new $class($this->clientRegistry, $this->userResolver, $this->urlGenerator);
    }
}
