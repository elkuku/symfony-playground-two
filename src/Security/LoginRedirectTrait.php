<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Shared success/failure handling for the social login authenticators.
 *
 * @property UrlGeneratorInterface $urlGenerator
 */
trait LoginRedirectTrait
{
    use TargetPathTrait;

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($targetPath ?? $this->urlGenerator->generate('app_default'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('danger', \strtr($exception->getMessageKey(), $exception->getMessageData()));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
