<?php

namespace App\DTO;

/**
 * PaymentResponse DTO
 * 
 * This class represents a response from a payment gateway transaction.
 * It encapsulates transaction identifiers, payment URLs, and the raw response data.
 */
class PaymentResponse
{
    /**
     * The unique transaction identifier from the payment gateway
     * 
     * @var string
     */
    private string $transactionId;
    
    /**
     * The URL where the user can complete the payment
     * 
     * @var string
     */
    private string $paymentUrl;
    
    /**
     * The complete raw response data from the payment gateway
     * 
     * @var array
     */
    private array $rawResponse;

    /**
     * Creates a new PaymentResponse instance
     * 
     * @param string $transactionId The unique transaction identifier
     * @param string $paymentUrl The URL for completing the payment
     * @param array $rawResponse The complete response data from the payment gateway
     */
    public function __construct(string $transactionId, string $paymentUrl, array $rawResponse = [])
    {
        $this->transactionId = $transactionId;
        $this->paymentUrl = $paymentUrl;
        $this->rawResponse = $rawResponse;
    }

    /**
     * Returns the transaction identifier
     * 
     * @return string The unique transaction identifier
     */
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Returns the payment URL
     * 
     * @return string The URL where the user can complete the payment
     */
    public function getPaymentUrl(): string
    {
        return $this->paymentUrl;
    }

    /**
     * Returns the raw response data from the payment gateway
     * 
     * @return array The complete response array
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }
}