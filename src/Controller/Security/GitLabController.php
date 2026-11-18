<?php

declare(strict_types=1);

namespace App\Controller\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class GitLabController extends AbstractController
{
    /**
     * Link to this controller to start the "connect" process.
     */
    #[Route(path: '/connect/gitlab', name: 'connect_gitlab_start', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('gitlab')
            // Only read the user's profile; the provider's default scope ("api") grants full API access.
            ->redirect(['read_user'], []);
    }

    /**
     * After going to GitLab, you're redirected back here
     * because this is the "redirect_route" you configured
     * in config/packages/knpu_oauth2_client.yaml.
     */
    #[Route(path: '/connect/check/gitlab', name: 'connect_gitlab_check', methods: ['GET'])]
    public function connectCheck(): RedirectResponse
    {
        return $this->redirectToRoute('app_default');
    }
}
