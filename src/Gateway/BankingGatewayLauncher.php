<?php

namespace App\Gateway;

use App\DTO\PaymentRequest;
use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Gateway\PaymentMethods\ING\IngOpenBankingPaymentConnector;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * BankingGatewayLauncher Class
 * 
 * Responsible for processing payment transactions through various banking gateways.
 * Acts as a factory for payment connectors and handles the payment process flow
 * including retry logic for failed transactions.
 */
class BankingGatewayLauncher
{
    /**
     * Counter for payment processing attempts
     */
    private int $attempts = 0;

    /**
     * Constructor
     * 
     * @param LoggerInterface $logger Logger service for recording transaction events
     * @param HttpClientInterface $httpClient HTTP client for API communications
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient
    ) {}

    /**
     * Process a payment transaction using the appropriate payment connector
     * 
     * This method handles the payment request by:
     * 1. Building the appropriate payment connector based on the payment method
     * 2. Executing the payment transaction with automatic retry (once) on failure
     * 3. Returning the payment response or throwing an exception on failure
     * 
     * @param PaymentRequest $paymentRequest Payment request details
     * @return PaymentResponse Response from payment processing
     * @throws BankingGatewayException When payment processing fails
     */
    public function processTransaction(PaymentRequest $paymentRequest): PaymentResponse
    {
        try {
            $paymentConnector = $this->buildPaymentConnectorFromMethod($paymentRequest);
            try {
                $this->attempts++;
                return $paymentConnector->makePayment();
            } catch (Throwable $throwable) {
                // Automatic retry on first failure
                if ($this->attempts < 2) {
                    return $paymentConnector->makePayment();
                }
                throw new BankingGatewayException('Bank transaction failed: ' . $throwable->getMessage());
            }
        } catch (Exception $e) {
            throw new BankingGatewayException('Bank transaction failed: ' . $e->getMessage());
        }
    }

    /**
     * Creates the appropriate payment connector based on the payment method
     * 
     * Factory method that instantiates and configures the correct payment connector
     * for processing the transaction based on the payment method in the request.
     * 
     * @param PaymentRequest $paymentRequest Payment request containing the payment method
     * @return PaymentConnector The configured payment connector for the transaction
     * @throws InvalidArgumentException When the payment method is not supported
     */
    protected function buildPaymentConnectorFromMethod(PaymentRequest $paymentRequest): PaymentConnector
    {
        if ($paymentRequest->getPaymentMethod() === PaymentMethodIDs::ING_OPEN_BANKING) {
            // Configuration for ING Open Banking integration
            // TODO: Move certificates to a secure storage location outside of the codebase
            // TODO: Store banking credentials in environment variables or a secure credential store
            $params = [
                'client_id' => "e77d776b-90af-4684-bebc-521e5b2614dd",
                'host' => $_ENV['ING_API_HOST'],
                'cert_path' => __DIR__.'/../../config/certificates/example_client_tls.cer',
                'key_path' => __DIR__.'/../../config/certificates/example_client_tls.key',
                'merchant_id' => "e77d776b-90af-4684-bebc-521e5b2614dd",
                'amount' => $paymentRequest->getAmount(),
                'currency' => $paymentRequest->getCurrency(),
                'return_url' => $paymentRequest->getReturnUrl(),
                'description' => $paymentRequest->getDescription()
            ];

            return new IngOpenBankingPaymentConnector(
                $params,
                $this->logger,
                $this->httpClient
            );
        }

        throw new InvalidArgumentException('Unsupported payment method');
    }
}