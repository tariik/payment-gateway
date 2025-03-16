<?php

declare(strict_types=1);

namespace App\Gateway\PaymentMethods\ING;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Gateway\PaymentConnector;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ING Open Banking Payment Connector
 * 
 * Implements payment processing through ING Open Banking API.
 * Handles authentication, payment request creation, and response processing
 * using client certificate authentication and OAuth2 token-based authorization.
 */
class IngOpenBankingPaymentConnector implements PaymentConnector
{
    private const LOG_DATE_FORMAT = 'Y-m-d\TH:i:s.000P';
    private const TOKEN_PATH = '/oauth2/token';
    private const PAYMENTS_PATH = '/payment-requests'; 

    /**
     * Base URL of the ING Open Banking API
     * @var string
     */
    private string $host;
    
    /**
     * OAuth2 client ID for authentication
     * @var string
     */
    private string $clientId;
    
    /**
     * Merchant identifier
     * @var string
     */
    private string $merchantId;
    
    /**
     * Payment amount
     * @var float
     */
    private float $amount;

    /**
     * Payment description
     * @var string
     */
    private string $description;
    
    /**
     * Currency code in ISO 4217 format
     * @var string
     */
    private string $currency;
    
    /**
     * URL where user is redirected after payment
     * @var string
     */
    private string $returnUrl;
    
    /**
     * OAuth2 access token
     * @var string|null
     */
    private ?string $accessToken = null;
    
    /**
     * Logger instance
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    
    /**
     * HTTP client for API requests
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;
    
    /**
     * Unique transaction identifier
     * @var string
     */
    private string $transactionId;
    
    /**
     * Path to SSL certificate
     * @var mixed
     */
    private mixed $certPath;
    
    /**
     * Path to private key
     * @var mixed
     */
    private mixed $keyPath;

    /**
     * Initialize the ING Open Banking payment connector
     *
     * @param array $params Configuration parameters including:
     *                     - host: API base URL
     *                     - client_id: OAuth client ID
     *                     - merchant_id: Merchant identifier
     *                     - amount: Payment amount
     *                     - currency: ISO 4217 currency code
     *                     - return_url: Redirect URL after payment
     *                     - cert_path: Path to SSL certificate
     *                     - key_path: Path to private key
     * @param LoggerInterface $logger Logger for recording payment operations
     * @param HttpClientInterface $httpClient HTTP client for API requests
     */
    public function __construct(
        array $params,
        LoggerInterface $logger,
        HttpClientInterface $httpClient
    ) {
        
        $this->host = $params['host'];
        $this->clientId = $params['client_id'];
        $this->merchantId = $params['merchant_id'];
        $this->amount = $params['amount'];
        $this->currency = $params['currency'];
        $this->returnUrl = $params['return_url'];
        $this->certPath = $params['cert_path'];
        $this->keyPath = $params['key_path'];
        $this->logger = $logger;
        $this->description = $params['description'];
        $this->httpClient = $httpClient;
        $this->transactionId = uniqid('TRX_', true);
    }

    /**
     * Initiates a payment through the ING Open Banking API
     * 
     * This method orchestrates the entire payment flow:
     * 1. Logs the payment initiation
     * 2. Obtains an OAuth access token
     * 3. Creates a payment request
     *
     * @throws BankingGatewayException If the payment process fails
     * @throws ClientExceptionInterface If there's an HTTP client error
     * @throws RedirectionExceptionInterface If there's an HTTP redirection error
     * @throws ServerExceptionInterface If there's an HTTP server error
     * @throws TransportExceptionInterface If there's an HTTP transport error
     * @return PaymentResponse Payment response with redirect URL and payment ID
     */
    public function makePayment(): PaymentResponse
    {
        $this->logStartPayment();
        $this->getAccessToken();
        return $this->createPayment();
    }

    /**
     * Logs the start of a payment transaction
     *
     * @return void
     */
    private function logStartPayment(): void
    {
        $this->logger->info("Starting ING Open Banking payment", [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'merchant_id' => $this->merchantId
        ]);
    }

    /**
     * Obtains an OAuth2 access token from the ING API
     *
     * Uses client certificate authentication to request an access token
     * using the client credentials grant type.
     *
     * @throws BankingGatewayException If token retrieval fails
     * @throws ClientExceptionInterface If there's an HTTP client error
     * @throws RedirectionExceptionInterface If there's an HTTP redirection error
     * @throws ServerExceptionInterface If there's an HTTP server error
     * @throws TransportExceptionInterface If there's an HTTP transport error
     * @return void
     */
    private function getAccessToken(): void
    {
        $tokenUrl = $this->host . self::TOKEN_PATH;

        try {

            $sslOptions = [
                $this->certPath,
                $this->keyPath,
            ];

            $requestBody = http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId
            ]);

            $this->logToFile(
                'TOKEN_REQUEST',
                $tokenUrl,
                $requestBody,
                'application/x-www-form-urlencoded',
                $sslOptions
            );


            $response = $this->httpClient->request('POST', $tokenUrl, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $requestBody,
                'local_cert' => $this->certPath,
                'local_pk' => $this->keyPath,
            ]);

            $responseContent = $response->getContent();

            $this->logToFile('TOKEN_RESPONSE', $tokenUrl, $responseContent, 'application/json');

            $data = json_decode($responseContent, true);

            if (empty($data['access_token'])) {
                $this->handleTokenError('Missing access token in response', $data);
                throw new BankingGatewayException('Invalid token response');
            }

            $this->accessToken = $data['access_token'];

            $this->logTokenSuccess($data);
        } catch (Exception $e) {
            $this->logToFile('TOKEN_ERROR', $tokenUrl, $e->getMessage(), 'text/plain', [
                'trace' => $e->getTraceAsString()
            ]);
            $this->handleTokenError($e->getMessage());
            throw new BankingGatewayException("Token request failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Handles errors during token retrieval
     *
     * @param string $message Error message
     * @param array $context Additional context data (optional)
     * @return void
     */
    private function handleTokenError(string $message, array $context = []): void
    {
        $this->logger->error("ING Token Error: $message", [
            'transaction_id' => $this->transactionId,
            'context' => $this->maskSensitiveData($context)
        ]);
    }

    /**
     * Logs successful token retrieval
     *
     * @param array $data Token response data
     * @return void
     */
    private function logTokenSuccess(array $data): void
    {
        $this->logger->debug("ING Token obtained", [
            'transaction_id' => $this->transactionId,
            'token_expires' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null
        ]);
    }

    /**
     * Creates a payment request in the ING Open Banking API
     *
     * @throws BankingGatewayException If payment creation fails
     * @throws ClientExceptionInterface If there's an HTTP client error
     * @throws RedirectionExceptionInterface If there's an HTTP redirection error
     * @throws ServerExceptionInterface If there's an HTTP server error
     * @throws TransportExceptionInterface If there's an HTTP transport error
     * @return PaymentResponse Payment response with redirect URL and payment ID
     */
    private function createPayment(): PaymentResponse
    {
        $this->validateAccessToken();

        try {
            $paymentData = $this->buildPaymentRequest();
            $response = $this->sendPaymentRequest($paymentData);

            return $this->processPaymentResponse($response);
        } catch (Exception $e) {
            $this->logToFile('PAYMENT_ERROR', $this->host . self::PAYMENTS_PATH, $e->getMessage(), 'text/plain', [
                'trace' => $e->getTraceAsString()
            ]);
            $this->handlePaymentError($e->getMessage());
            throw new BankingGatewayException("Payment failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Builds payment request data according to ING API specifications
     *
     * @throws BankingGatewayException If payment parameters are invalid
     * @return array Payment request data
     */
    private function buildPaymentRequest(): array
    {
        $this->validatePaymentParameters();
        $purchaseId = uniqid('purchase_', true);

        return [
            'fixedAmount' => [
                'value' => $this->amount,
                'currency' => $this->currency
            ],
            'validUntil' => (new DateTime('+1 day'))->format(self::LOG_DATE_FORMAT),
            'maximumAllowedPayments' => 1,
            'maximumReceivableAmount' => [
                'value' => $this->amount,
                'currency' => $this->currency
            ],
            'purchaseId' => $purchaseId,
            'description' => $this->description,
            'returnUrl' => $this->returnUrl
        ];
    }

    /**
     * Sends the payment request to the ING API
     *
     * @param array $paymentData Payment request data
     * @throws TransportExceptionInterface If there's an HTTP transport error
     * @throws ServerExceptionInterface If there's an HTTP server error
     * @throws RedirectionExceptionInterface If there's an HTTP redirection error
     * @throws ClientExceptionInterface If there's an HTTP client error
     * @return array API response data
     */
    private function sendPaymentRequest(array $paymentData): array
    {
        $paymentUrl = $this->host . self::PAYMENTS_PATH;
        $jsonBody = json_encode($paymentData);

        $this->logToFile(
            'PAYMENT_REQUEST',
            $paymentUrl,
            $jsonBody,
            'application/json',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . '************',
                ]
            ]
        );


        $response = $this->httpClient->request('POST', $paymentUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ],
            'body' => $jsonBody,
            'local_cert' => $this->certPath,
            'local_pk' => $this->keyPath,
        ]);

        $responseContent = $response->getContent();
        $this->logToFile('PAYMENT_RESPONSE', $paymentUrl, $responseContent, 'application/json');

        return json_decode($responseContent, true);
    }

    /**
     * Processes the payment response from the ING API
     *
     * @param array $responseData API response data
     * @throws BankingGatewayException If response is invalid
     * @return PaymentResponse Processed payment response
     */
    private function processPaymentResponse(array $responseData): PaymentResponse
    {
        if (empty($responseData['id']) || empty($responseData['paymentInitiationUrl'])) {
            $this->handlePaymentError('Invalid payment response structure', $responseData);
            throw new BankingGatewayException('Invalid payment response from ING');
        }

        $this->logger->info("ING Payment successful", [
            'transaction_id' => $this->transactionId,
            'payment_id' => $responseData['id'],
            'initiation_url' => $responseData['paymentInitiationUrl']
        ]);

        return new PaymentResponse(
            $responseData['id'],
            $responseData['paymentInitiationUrl'],
            $responseData
        );
    }

    /**
     * Validates that the access token is available
     *
     * @throws BankingGatewayException If access token is missing
     * @return void
     */
    private function validateAccessToken(): void
    {
        if (empty($this->accessToken)) {
            $this->logger->critical("Missing access token for payment");
            throw new BankingGatewayException("Authentication required");
        }
    }

    /**
     * Handles payment processing errors
     *
     * @param string $message Error message
     * @param array $context Additional context data (optional)
     * @return void
     */
    private function handlePaymentError(string $message, array $context = []): void
    {
        $this->logger->error("ING Payment Error: $message", [
            'transaction_id' => $this->transactionId,
            'context' => $this->maskSensitiveData($context)
        ]);
    }

    /**
     * Masks sensitive data for logging purposes
     *
     * @param array $data Data containing potentially sensitive information
     * @return array Data with sensitive information masked
     */
    private function maskSensitiveData(array $data): array
    {
        $maskRules = [
            'access_token' => fn($v) => substr($v, 0, 6) . '******',
            'client_id' => fn($v) => substr($v, 0, 4) . '****',
            'Authorization' => fn($v) => 'Bearer *****',
            'Merchant-Id' => fn($v) => substr($v, 0, 4) . '****',
            'api_key' => fn($v) => substr($v, 0, 4) . '****'
        ];

        array_walk($data, function (&$value, $key) use ($maskRules) {
            if (is_array($value)) {
                $value = $this->maskSensitiveData($value);
            } elseif (isset($maskRules[$key])) {
                $value = $maskRules[$key]($value);
            }
        });

        return $data;
    }

    /**
     * Logs API requests and responses to a file
     *
     * @param string $action Action identifier (e.g., TOKEN_REQUEST, PAYMENT_RESPONSE)
     * @param string $url API endpoint URL
     * @param string $rawData Raw request or response data
     * @param string $contentType MIME content type of the data
     * @param array $additionalData Additional context data (optional)
     * @return void
     */
    private function logToFile(
        string $action,
        string $url,
        string $rawData,
        string $contentType,
        array $additionalData = []
    ): void {
        try {
            $logDir = __DIR__ . '/../../../../var/log/payments/ing/';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }

            $logFile = $logDir . 'payment_ing_' . date('Y-m-d') . '.log';

            $processedData = $this->processRawData($rawData, $contentType);
            $maskedData = array_merge($additionalData, [
                'body' => $processedData
            ]);

            $logContent = sprintf(
                "[%s] [%s] [TRX: %s]\nURL: %s\n%s\n\n",
                date('Y-m-d H:i:s'),
                $action,
                $this->transactionId,
                $url,
                json_encode($maskedData, JSON_PRETTY_PRINT)
            );

            file_put_contents($logFile, $logContent, FILE_APPEND);
        } catch (Exception $e) {
            $this->logger->error("Failed to write log file: " . $e->getMessage());
        }
    }

    /**
     * Processes raw data based on content type
     *
     * @param string $rawData Raw data to process
     * @param string $contentType MIME content type of the data
     * @return array Processed data
     */
    private function processRawData(string $rawData, string $contentType): array
    {
        try {
            $parsedData = match ($contentType) {
                'application/json' => json_decode($rawData, true),
                'application/x-www-form-urlencoded' => parse_str($rawData, $result) ? $result : [],
                default => []
            };

            return $this->maskSensitiveData((array)$parsedData);
        } catch (Exception $e) {
            return ['error' => 'Failed to parse data: ' . $e->getMessage()];
        }
    }

    /**
     * Validates payment parameters before sending to the bank API
     *
     * @throws BankingGatewayException If any validation fails
     * @return void
     */
    private function validatePaymentParameters(): void
    {
        // Validate amount
        if (!is_numeric($this->amount) || $this->amount <= 0) {
            throw new BankingGatewayException('Payment amount must be a positive number');
        }

        // Validate currency (ISO 4217 format)
        if (!preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new BankingGatewayException('Invalid currency code format');
        }

        // Validate return URL
        if (empty($this->returnUrl) || !filter_var($this->returnUrl, FILTER_VALIDATE_URL)) {
            throw new BankingGatewayException('Invalid return URL');
        }

        // Validate URL scheme (must be HTTPS for security)
        $urlParts = parse_url($this->returnUrl);
        if (!isset($urlParts['scheme']) || strtolower($urlParts['scheme']) !== 'https') {
            throw new BankingGatewayException('Return URL must use HTTPS protocol');
        }
    }
}
