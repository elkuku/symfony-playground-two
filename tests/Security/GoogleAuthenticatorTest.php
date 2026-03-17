<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\GoogleAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class GoogleAuthenticatorTest extends TestCase
{
    private GoogleAuthenticator $authenticator;

    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private UrlGeneratorInterface&MockObject $urlGenerator;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->authenticator = new GoogleAuthenticator(
            $this->createMock(ClientRegistry::class),
            $this->entityManager,
            $this->userRepository,
            $this->urlGenerator,
        );
    }

    public function testSupportsGoogleCheckPath(): void
    {
        $request = Request::create('/connect/google/check');
        self::assertTrue($this->authenticator->supports($request));
    }

    public function testDoesNotSupportOtherPaths(): void
    {
        self::assertFalse($this->authenticator->supports(Request::create('/')));
        self::assertFalse($this->authenticator->supports(Request::create('/connect/google/verify')));
    }

    public function testGetUserReturnsExistingUserByGoogleId(): void
    {
        $existingUser = new User()->setIdentifier('test@example.com')->setGoogleId('google-123');

        $this->userRepository->method('findOneBy')
            ->with(['googleId' => 'google-123'])
            ->willReturn($existingUser);

        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        $googleUser = $this->createMock(GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-123');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame($existingUser, $result);
    }

    public function testGetUserCreatesNewUserWhenNotFound(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $googleUser = $this->createMock(GoogleUser::class);
        $googleUser->method('getId')->willReturn('google-456');
        $googleUser->method('getEmail')->willReturn('new@example.com');

        $method = new \ReflectionMethod($this->authenticator, 'getUser');
        $result = $method->invoke($this->authenticator, $googleUser);

        self::assertSame('new@example.com', $result->getIdentifier());
        self::assertSame('google-456', $result->getGoogleId());
    }

    public function testOnAuthenticationSuccessRedirectsToTargetPath(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.main.target_path', '/admin');

        $request = new Request();
        $request->setSession($session);

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $this->createMock(TokenInterface::class),
            'main'
        );

        self::assertSame('/admin', $response->getTargetUrl());
    }

    public function testOnAuthenticationFailureAddsFlashAndRedirects(): void
    {
        $this->urlGenerator->method('generate')->willReturn('/login');

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $this->authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException('Bad credentials.')
        );

        self::assertSame('/login', $response->getTargetUrl());
    }
}
