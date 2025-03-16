<?php

namespace App\Service;

use App\DTO\PaymentRequest;
use App\Entity\Payment;
use App\Exception\PaymentProcessingException;
use App\Gateway\BankingGatewayLauncher;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Payment Service
 * 
 * Core service responsible for managing payment processing logic,
 * interfacing with payment gateways, and maintaining payment state.
 */
class PaymentService
{
    /**
     * PaymentService constructor
     * 
     * @param LoggerInterface $logger For logging payment operations
     * @param BankingGatewayLauncher $bankingGatewayLauncher Gateway for processing bank transactions
     */
    public function __construct(
        private readonly LoggerInterface        $logger,
        private readonly BankingGatewayLauncher $bankingGatewayLauncher
    ) {
    }

    /**
     * Process a payment through the appropriate banking gateway
     * 
     * Handles the complete payment flow:
     * 1. Creates a payment entity record
     * 2. Passes the request to the banking gateway
     * 3. Updates payment with gateway response data
     * 
     * @param PaymentRequest $paymentRequest The payment request with all required details
     * @return Payment The processed payment entity with transaction details
     * @throws PaymentProcessingException If payment processing fails
     */
    public function processPayment(PaymentRequest $paymentRequest): Payment
    {
        try {
            // Initialize payment entity with request data
            $payment = new Payment();
            $payment->setCurrency($paymentRequest->getCurrency());
            $payment->setAmount($paymentRequest->getAmount());
            $payment->setPaymentMethod($paymentRequest->getPaymentMethod());
            $payment->setStatus('processing');
            
            // Send to banking gateway and receive response
            $paymentResponse = $this->bankingGatewayLauncher->processTransaction($paymentRequest);
            
            // Update payment with transaction details from gateway
            $transactionId =  $paymentResponse->getTransactionId();
            $paymentUrl =  $paymentResponse->getPaymentUrl();
            $payment->setPaymentUrl($paymentUrl);
            $payment->setDescription($paymentRequest->getDescription());
            $payment->setTransactionId($transactionId);
            $payment->setStatus('completed');
      
            // Enhancement opportunity: Add user data, transaction metadata, etc.
            // Persistence logic would go here:
            // $this->entityManager->persist($payment);
            // $this->entityManager->flush();
           
            return $payment;
        } catch (Exception $e) {
            // Log error details for troubleshooting
            $this->logger->error('Payment processing failed', [
                'error' => $e->getMessage(),
                'currency' => $paymentRequest->getCurrency(),
                'amount' => $paymentRequest->getAmount()
            ]);
            
            // Rethrow as domain-specific exception
            throw new PaymentProcessingException('Payment processing failed: ' . $e->getMessage());
        }
    }
}