<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/payment')]
class PaymentController extends AbstractController
{
    #[Route('/process', name: 'payment_process', methods: ['GET'])]
    public function process(): JsonResponse
    {
        return $this->json([
            'status' => 'active',
            'message' => 'Payment gateway is operational',
            'timestamp' => new \DateTime(),
        ]);
    }
}
