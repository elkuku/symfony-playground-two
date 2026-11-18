<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\GoogleUser;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Finds or creates the local user for a social login.
 *
 * Existing users are matched by the provider's account ID. Never match on a
 * name the user can choose freely (GitHub/GitLab usernames): anyone could
 * register an account with an existing user's name and take that user over.
 */
class SocialUserResolver
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function fromGitHub(GithubResourceOwner $owner): User
    {
        $id = (int) $owner->getId();

        return $this->userRepository->findOneBy(['gitHubId' => $id])
            ?? $this->save($this->createUser((string) $owner->getNickname(), 'github-'.$id)->setGitHubId($id));
    }

    public function fromGitLab(GitlabResourceOwner $owner): User
    {
        $id = $owner->getId();

        return $this->userRepository->findOneBy(['gitLabId' => $id])
            ?? $this->save($this->createUser($owner->getUsername(), 'gitlab-'.$id)->setGitLabId($id));
    }

    public function fromGoogle(GoogleUser $googleUser): User
    {
        $googleId = (string) $googleUser->getId();

        $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

        if (null !== $user) {
            return $user;
        }

        $email = (string) $googleUser->getEmail();

        if ('' === $email) {
            throw new CustomUserMessageAuthenticationException('Google did not provide an email address.');
        }

        // Only link to an existing account when Google vouches for the email address.
        // @see https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
        $user = $googleUser->isEmailTrustworthy()
            ? $this->userRepository->findOneBy(['identifier' => $email])
            : null;

        $user ??= $this->createUser($email, $email);

        return $this->save($user->setGoogleId($googleId));
    }

    private function createUser(string $preferredIdentifier, string $fallback): User
    {
        $base = '' === $preferredIdentifier ? $fallback : $preferredIdentifier;

        return new User()->setIdentifier($this->userRepository->getAvailableIdentifier($base));
    }

    private function save(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
