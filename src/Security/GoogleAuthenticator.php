<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class GoogleAuthenticator extends AbstractOAuthAuthenticator
{
    #[\Override]
    protected function getClientName(): string
    {
        return 'google';
    }

    #[\Override]
    protected function getCheckPath(): string
    {
        return '/connect/google/check';
    }

    #[\Override]
    protected function resolveUser(ResourceOwnerInterface $resourceOwner): User
    {
        if (!$resourceOwner instanceof GoogleUser) {
            throw new \UnexpectedValueException(\sprintf('Expected %s, got %s.', GoogleUser::class, $resourceOwner::class));
        }

        return $this->userResolver->fromGoogle($resourceOwner);
    }
}
