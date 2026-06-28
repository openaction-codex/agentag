<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'agentag_admin_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(['status' => 'admin_protected']);
    }
}
