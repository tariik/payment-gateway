<?php

declare(strict_types=1);

namespace App\Gateway\PaymentMethods\ING;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Service\Logger\PaymentLoggerService;

/**
 * Processes payment responses from the ING API
 */
class IngPaymentResponseProcessor
{
    /**
     * @var PaymentLoggerService Logger service for payment operations
     */
    private PaymentLoggerService $paymentLogger;

    /**
     * Constructor
     *
     * @param PaymentLoggerService $paymentLogger Logger service for payment operations
     */
    public function __construct(PaymentLoggerService $paymentLogger)
    {
        $this->paymentLogger = $paymentLogger;
    }

    /**
     * Processes the payment response from the ING API
     *
     * @param array $responseData API response data
     * @param string $transactionId Transaction identifier for logging
     * @throws BankingGatewayException If response is invalid
     * @return PaymentResponse Processed payment response
     */
    public function processPaymentResponse(array $responseData, string $transactionId): PaymentResponse
    {
        if (empty($responseData['id']) || empty($responseData['paymentInitiationUrl'])) {
            $this->paymentLogger->logPaymentError($transactionId, 'Invalid payment response structure', $responseData);
            throw new BankingGatewayException('Invalid payment response from ING');
        }

        $this->paymentLogger->logPaymentSuccess(
            $transactionId,
            $responseData['id'],
            $responseData['paymentInitiationUrl']
        );

        return new PaymentResponse(
            $responseData['id'],
            $responseData['paymentInitiationUrl'],
            $responseData
        );
    }
}
