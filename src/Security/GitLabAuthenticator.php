<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;

class GitLabAuthenticator extends AbstractOAuthAuthenticator
{
    #[\Override]
    protected function getClientName(): string
    {
        return 'gitlab';
    }

    #[\Override]
    protected function getCheckPath(): string
    {
        return '/connect/check/gitlab';
    }

    #[\Override]
    protected function resolveUser(ResourceOwnerInterface $resourceOwner): User
    {
        if (!$resourceOwner instanceof GitlabResourceOwner) {
            throw new \UnexpectedValueException(\sprintf('Expected %s, got %s.', GitlabResourceOwner::class, $resourceOwner::class));
        }

        return $this->userResolver->fromGitLab($resourceOwner);
    }
}
