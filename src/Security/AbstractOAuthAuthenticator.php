<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Base for the OAuth redirect logins (knpuniversity/oauth2-client-bundle).
 *
 * fetchAccessToken() turns a cancelled login, an invalid state or a provider
 * error into an AuthenticationException, so the user sees a message on the
 * login page instead of an error page.
 */
abstract class AbstractOAuthAuthenticator extends OAuth2Authenticator
{
    use LoginRedirectTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        protected readonly SocialUserResolver $userResolver,
        protected readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * The client name in config/packages/knpu_oauth2_client.yaml.
     */
    abstract protected function getClientName(): string;

    /**
     * The path of the client's redirect_route.
     */
    abstract protected function getCheckPath(): string;

    abstract protected function resolveUser(ResourceOwnerInterface $resourceOwner): User;

    #[\Override]
    public function supports(Request $request): bool
    {
        return $this->getCheckPath() === $request->getPathInfo();
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient($this->getClientName());

        $resourceOwner = $client->fetchUserFromToken($this->fetchAccessToken($client));

        $user = $this->resolveUser($resourceOwner);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
            [new RememberMeBadge()],
        );
    }
}
