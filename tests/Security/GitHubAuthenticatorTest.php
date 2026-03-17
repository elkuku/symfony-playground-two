<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GitHubAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class GitHubAuthenticatorTest extends TestCase
{
    private GitHubAuthenticator $authenticator;

    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->authenticator = new GitHubAuthenticator(
            $this->createMock(ClientRegistry::class),
            $this->entityManager,
            $this->userRepository,
            $this->urlGenerator,
        );
    }

    public function testSupportsGitHubCheckPath(): void
    {
        self::assertTrue($this->authenticator->supports(Request::create('/connect/check/github')));
    }

    public function testDoesNotSupportOtherPaths(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/')));
        self::assertFalse($this->authenticator->supports(Request::create('/connect/google/check')));
    }

    public function testGetUserReturnsExistingUserByGitHubId(): void
    {
        $existingUser = new User()->setIdentifier('githubuser')->setGitHubId(42);

        $this->userRepository->method('findOneBy')
            ->with(['gitHubId' => 42])
            ->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $owner = $this->createMock(GithubResourceOwner::class);
        $owner->method('getId')->willReturn(42);

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $owner);

        self::assertSame($existingUser, $result);
    }

    public function testGetUserLinksExistingAccountByNickname(): void
    {
        $existingUser = new User()->setIdentifier('existinguser');

        $this->userRepository->method('findOneBy')
            ->willReturnMap([
                [['gitHubId' => 77], null],
                [['identifier' => 'existinguser'], $existingUser],
            ]);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $owner = $this->createMock(GithubResourceOwner::class);
        $owner->method('getId')->willReturn(77);
        $owner->method('getNickname')->willReturn('existinguser');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $owner);

        self::assertSame($existingUser, $result);
        self::assertSame(77, $result->getGitHubId());
    }

    public function testGetUserCreatesNewUserWhenNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $owner = $this->createMock(GithubResourceOwner::class);
        $owner->method('getId')->willReturn(77);
        $owner->method('getNickname')->willReturn('newgithubuser');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $owner);

        self::assertSame('newgithubuser', $result->getIdentifier());
        self::assertSame(77, $result->getGitHubId());
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/dashboard');

        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertSame('/dashboard', $response->getTargetUrl());
    }

    public function testOnAuthenticationSuccessRedirectsToDefault(): void
    {
        $this->urlGenerator->method('generate')->with('app_default')->willReturn('/');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertSame('/', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureAddsFlashAndRedirects(): void
    {
        $this->urlGenerator->method('generate')->with('app_login')->willReturn('/login');

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException('Bad credentials.')
        );

        self::assertSame('/login', $response->getTargetUrl());
        self::assertNotEmpty($session->getFlashBag()->get('danger'));
    }
}
