<?php

namespace App\Controller\Security;

use Symfony\Component\Routing\Attribute\Route;

#[Route('/logout', name: 'app_logout', methods: ['GET'])]
class Logout
{
    public function __invoke(): void
    {
    }
}
