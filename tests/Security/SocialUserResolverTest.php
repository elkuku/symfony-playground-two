<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\SocialUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AllowMockObjectsWithoutExpectations]
class SocialUserResolverTest extends TestCase
{
    private UserRepository&MockObject $userRepository;

    private EntityManagerInterface&MockObject $entityManager;

    private SocialUserResolver $resolver;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->resolver = new SocialUserResolver($this->userRepository, $this->entityManager);
    }

    public function testGitHubReturnsExistingUserById(): void
    {
        $existingUser = new User()->setIdentifier('someone')->setGitHubId(42);

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['gitHubId' => 42])
            ->willReturn($existingUser);
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($existingUser, $this->resolver->fromGitHub($this->gitHubOwner(42, 'someone')));
    }

    public function testGitHubNeverLinksExistingAccountByUsername(): void
    {
        // A user "admin" exists, and someone logs in with a GitHub account named "admin".
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->expects(self::once())->method('getAvailableIdentifier')
            ->with('admin')
            ->willReturn('admin-2');
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->resolver->fromGitHub($this->gitHubOwner(77, 'admin'));

        self::assertSame('admin-2', $user->getIdentifier());
        self::assertSame(77, $user->getGitHubId());
    }

    public function testGitHubFallsBackToIdWithoutUsername(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->expects(self::once())->method('getAvailableIdentifier')
            ->with('github-77')
            ->willReturnArgument(0);

        self::assertSame('github-77', $this->resolver->fromGitHub($this->gitHubOwner(77, ''))->getIdentifier());
    }

    public function testGitLabReturnsExistingUserById(): void
    {
        $existingUser = new User()->setIdentifier('someone')->setGitLabId(42);

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['gitLabId' => 42])
            ->willReturn($existingUser);
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($existingUser, $this->resolver->fromGitLab($this->gitLabOwner(42, 'someone')));
    }

    public function testGitLabNeverLinksExistingAccountByUsername(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->expects(self::once())->method('getAvailableIdentifier')
            ->with('admin')
            ->willReturn('admin-2');
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->resolver->fromGitLab($this->gitLabOwner(77, 'admin'));

        self::assertSame('admin-2', $user->getIdentifier());
        self::assertSame(77, $user->getGitLabId());
    }

    public function testGoogleReturnsExistingUserById(): void
    {
        $existingUser = new User()->setIdentifier('test@gmail.com')->setGoogleId('g-1');

        $this->userRepository->expects(self::once())->method('findOneBy')
            ->with(['googleId' => 'g-1'])
            ->willReturn($existingUser);
        $this->entityManager->expects(self::never())->method('flush');

        self::assertSame($existingUser, $this->resolver->fromGoogle($this->googleUser('g-1', 'test@gmail.com')));
    }

    public function testGoogleLinksExistingAccountByTrustworthyEmail(): void
    {
        $existingUser = new User()->setIdentifier('shared@gmail.com');

        $this->userRepository->method('findOneBy')->willReturnMap([
            [['googleId' => 'g-2'], null],
            [['identifier' => 'shared@gmail.com'], $existingUser],
        ]);
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->resolver->fromGoogle($this->googleUser('g-2', 'shared@gmail.com'));

        self::assertSame($existingUser, $user);
        self::assertSame('g-2', $user->getGoogleId());
    }

    public function testGoogleDoesNotLinkByUntrustworthyEmail(): void
    {
        $existingUser = new User()->setIdentifier('shared@example.com');

        $this->userRepository->method('findOneBy')->willReturnMap([
            [['googleId' => 'g-3'], null],
            [['identifier' => 'shared@example.com'], $existingUser],
        ]);
        $this->userRepository->expects(self::once())->method('getAvailableIdentifier')
            ->with('shared@example.com')
            ->willReturn('shared@example.com-2');

        // Not a gmail.com address and no verified Workspace domain.
        $user = $this->resolver->fromGoogle($this->googleUser('g-3', 'shared@example.com'));

        self::assertNotSame($existingUser, $user);
        self::assertNull($existingUser->getGoogleId());
        self::assertSame('shared@example.com-2', $user->getIdentifier());
        self::assertSame('g-3', $user->getGoogleId());
    }

    public function testGoogleCreatesNewUser(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->userRepository->method('getAvailableIdentifier')->willReturnArgument(0);
        $this->entityManager->expects(self::once())->method('persist');
        $this->entityManager->expects(self::once())->method('flush');

        $user = $this->resolver->fromGoogle($this->googleUser('g-4', 'new@gmail.com'));

        self::assertSame('new@gmail.com', $user->getIdentifier());
        self::assertSame('g-4', $user->getGoogleId());
    }

    public function testGoogleWithoutEmailIsRejected(): void
    {
        $this->userRepository->method('findOneBy')->willReturn(null);
        $this->entityManager->expects(self::never())->method('persist');

        $this->expectException(AuthenticationException::class);
        $this->resolver->fromGoogle($this->googleUser('g-5', null));
    }

    private function gitHubOwner(int $id, string $login): GithubResourceOwner
    {
        return new GithubResourceOwner(['id' => $id, 'login' => $login]);
    }

    private function gitLabOwner(int $id, string $username): GitlabResourceOwner
    {
        return new GitlabResourceOwner(['id' => $id, 'is_admin' => false, 'name' => $username, 'username' => $username, 'email' => '', 'avatar_url' => '', 'web_url' => '', 'state' => 'active', 'external' => false], new AccessToken(['access_token' => 'token']));
    }

    private function googleUser(string $id, ?string $email): GoogleUser
    {
        return new GoogleUser(['sub' => $id, 'email' => $email]);
    }
}
