<?php

declare(strict_types=1);

namespace App\Security;

use Google\Client;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * "Sign in with Google" (Google Identity Services): verifies the ID token posted by Google's button.
 */
class GoogleIdentityAuthenticator extends AbstractAuthenticator
{
    use LoginRedirectTrait;

    public function __construct(
        #[Autowire(env: 'OAUTH_GOOGLE_ID')] private readonly string $oauthGoogleId,
        private readonly SocialUserResolver $userResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return '/connect/google/verify' === $request->getPathInfo();
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $idToken = (string) $request->request->get('credential');

        if ('' === $idToken || '0' === $idToken) {
            throw new AuthenticationException('Missing credentials :(');
        }

        $payload = new Client(['client_id' => $this->oauthGoogleId])
            ->verifyIdToken($idToken);

        if (!$payload) {
            throw new AuthenticationException('Invalid ID token :(');
        }

        $user = $this->userResolver->fromGoogle(new GoogleUser($payload));

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
            [new RememberMeBadge()],
        );
    }
}
