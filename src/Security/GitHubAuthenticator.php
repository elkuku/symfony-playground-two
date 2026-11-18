<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class GitHubAuthenticator extends AbstractOAuthAuthenticator
{
    #[\Override]
    protected function getClientName(): string
    {
        return 'github';
    }

    #[\Override]
    protected function getCheckPath(): string
    {
        return '/connect/check/github';
    }

    #[\Override]
    protected function resolveUser(ResourceOwnerInterface $resourceOwner): User
    {
        if (!$resourceOwner instanceof GithubResourceOwner) {
            throw new \UnexpectedValueException(\sprintf('Expected %s, got %s.', GithubResourceOwner::class, $resourceOwner::class));
        }

        return $this->userResolver->fromGitHub($resourceOwner);
    }
}
