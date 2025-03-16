<?php

declare(strict_types=1);

namespace App\Gateway\PaymentMethods\ING;

use App\Exception\BankingGatewayException;
use DateTime;

/**
 * Builds payment request data according to ING API specifications
 */
class IngPaymentRequestBuilder
{
    private const LOG_DATE_FORMAT = 'Y-m-d\TH:i:s.000P';
    
    /**
     * Build a payment request for the ING API
     *
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string $description Payment description
     * @param string $returnUrl URL to redirect after payment
     * @return array Payment request data
     * @throws BankingGatewayException If payment parameters are invalid
     */
    public function buildPaymentRequest(
        float $amount,
        string $currency,
        string $description,
        string $returnUrl
    ): array {
        $this->validatePaymentParameters($amount, $currency, $returnUrl);
        $purchaseId = uniqid('purchase_', true);

        return [
            'fixedAmount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'validUntil' => (new DateTime('+1 day'))->format(self::LOG_DATE_FORMAT),
            'maximumAllowedPayments' => 1,
            'maximumReceivableAmount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'purchaseId' => $purchaseId,
            'description' => $description,
            'returnUrl' => $returnUrl
        ];
    }
    
    /**
     * Validates payment parameters before sending to the bank API
     *
     * @param float $amount Payment amount
     * @param string $currency Currency code
     * @param string $returnUrl URL to redirect after payment
     * @throws BankingGatewayException If any validation fails
     * @return void
     */
    private function validatePaymentParameters(float $amount, string $currency, string $returnUrl): void
    {
        // Validate amount
        if (!is_numeric($amount) || $amount <= 0) {
            throw new BankingGatewayException('Payment amount must be a positive number');
        }

        // Validate currency (ISO 4217 format)
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new BankingGatewayException('Invalid currency code format');
        }

        // Validate return URL
        if (empty($returnUrl) || !filter_var($returnUrl, FILTER_VALIDATE_URL)) {
            throw new BankingGatewayException('Invalid return URL');
        }

        // Validate URL scheme (must be HTTPS for security)
        $urlParts = parse_url($returnUrl);
        if (!isset($urlParts['scheme']) || strtolower($urlParts['scheme']) !== 'https') {
            throw new BankingGatewayException('Return URL must use HTTPS protocol');
        }
    }
}
