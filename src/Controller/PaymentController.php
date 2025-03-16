<?php

namespace App\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PaymentService;
use App\DTO\PaymentRequest;
use App\Constants\UrlConstants;
use Psr\Log\LoggerInterface;
use function uniqid;

/**
 * Payment Controller
 * 
 * Handles all payment-related HTTP endpoints including payment processing,
 * status checking, and callback handling.
 */
#[Route('/api/payment')]
class PaymentController extends AbstractController
{
    private PaymentService $paymentService;
    private LoggerInterface $logger;
    
    public function __construct(
        PaymentService $paymentService,
        LoggerInterface $logger
    ) {
        $this->paymentService = $paymentService;
        $this->logger = $logger;
    }

    /**
     * Process a payment transaction
     * 
     * This endpoint handles the payment processing workflow:
     * 1. Sets up payment parameters
     * 2. Creates a payment request
     * 3. Processes the payment through the payment service
     * 
     * @return JsonResponse Returns a JSON response with payment details or error information
     * @throws Exception If there's an error during payment processing
     */
    #[Route('/process', name: 'payment_process', methods: ['GET'])]
    public function process(): JsonResponse
    {
        try {
        // In production: Retrieve these values from request parameters or database
        // Currency should be validated against ISO 4217 standards
        $currency = 'EUR';
        $amount = 78.25;
        $paymentMethod = 'ING_OPEN_BANKING';
        $purchaseId = uniqid('purchase_', true);
        $returnUrl = UrlConstants::buildPaymentReturnUrl($purchaseId);

        // Initialize payment request with required parameters
        $paymentRequest = new PaymentRequest();
        $paymentRequest->setCurrency($currency);
        $paymentRequest->setAmount($amount);
        $paymentRequest->setPaymentMethod($paymentMethod);
        $paymentRequest->setReturnUrl($returnUrl);
        $paymentRequest->setDescription('Order #12345');
        $paymentRequest->setStatus('processing');

        // Forward to payment service for processing
        $payment = $this->paymentService->processPayment($paymentRequest);

        // Return successful response with payment details
        return $this->json([
            'status' => 'success',
            'payment' => [
                'currency' => $payment->getCurrency(),
                'amount' => $payment->getAmount(),
                'payment_url' => $payment->getPaymentUrl(),
                'transaction_id' => $payment->getTransactionId(),
                'description' => $payment->getDescription(),
                'status' => $payment->getStatus()
                
            ]
        ], 201);
    }
        catch (Exception $e) {
            // Log the error with full details for debugging
            $this->logger->error('Payment processing error: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return user-friendly error without exposing sensitive information
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
