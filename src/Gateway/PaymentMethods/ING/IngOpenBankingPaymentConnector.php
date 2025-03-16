<?php

declare(strict_types=1);

namespace App\Gateway\PaymentMethods\ING;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Gateway\PaymentConnector;
use App\Service\Logger\PaymentLoggerService;
use Exception;
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
     * Payment logger service
     * @var PaymentLoggerService
     */
    private PaymentLoggerService $paymentLogger;
    
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
     * Payment request builder
     * @var IngPaymentRequestBuilder
     */
    private IngPaymentRequestBuilder $requestBuilder;
    
    /**
     * Payment sender
     * @var IngPaymentSender
     */
    private IngPaymentSender $paymentSender;
    
    /**
     * Response processor
     * @var IngPaymentResponseProcessor
     */
    private IngPaymentResponseProcessor $responseProcessor;

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
     * @param PaymentLoggerService $paymentLogger Logger service for payment operations
     * @param HttpClientInterface $httpClient HTTP client for API requests
     */
    public function __construct(
        array $params,
        PaymentLoggerService $paymentLogger,
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
        $this->paymentLogger = $paymentLogger;
        $this->description = $params['description'];
        $this->httpClient = $httpClient;
        $this->transactionId = uniqid('TRX_', true);
        
        // Initialize the helper classes
        $this->requestBuilder = new IngPaymentRequestBuilder();
        $this->paymentSender = new IngPaymentSender($httpClient, $paymentLogger);
        $this->responseProcessor = new IngPaymentResponseProcessor($paymentLogger);
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
        $this->paymentLogger->logStartPayment(
            $this->transactionId, 
            $this->amount, 
            $this->currency, 
            $this->merchantId
        );
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

            $this->paymentLogger->logToFile(
                $this->transactionId,
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

            $this->paymentLogger->logToFile(
                $this->transactionId,
                'TOKEN_RESPONSE',
                $tokenUrl,
                $responseContent,
                'application/json'
            );

            $data = json_decode($responseContent, true);

            if (empty($data['access_token'])) {
                $this->paymentLogger->logTokenError($this->transactionId, 'Missing access token in response', $data);
                throw new BankingGatewayException('Invalid token response');
            }

            $this->accessToken = $data['access_token'];

            $this->paymentLogger->logTokenSuccess($this->transactionId, $data);
        } catch (Exception $e) {
            $this->paymentLogger->logToFile(
                $this->transactionId,
                'TOKEN_ERROR',
                $tokenUrl,
                $e->getMessage(),
                'text/plain',
                ['trace' => $e->getTraceAsString()]
            );
            
            // Only log token error if it's not already a BankingGatewayException (which we've already logged)
            if (!($e instanceof BankingGatewayException)) {
                $this->paymentLogger->logTokenError($this->transactionId, $e->getMessage());
            }
            
            throw new BankingGatewayException("Token request failed: " . $e->getMessage(), 0, $e);
        }
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
            // Use the extracted services to handle the payment flow
            $paymentData = $this->requestBuilder->buildPaymentRequest(
                $this->amount,
                $this->currency,
                $this->description,
                $this->returnUrl
            );
            
            $response = $this->paymentSender->sendPaymentRequest(
                $paymentData,
                $this->host,
                $this->accessToken,
                $this->certPath,
                $this->keyPath,
                $this->transactionId
            );
            
            return $this->responseProcessor->processPaymentResponse(
                $response,
                $this->transactionId
            );
            
        } catch (Exception $e) {
            $this->paymentLogger->logToFile(
                $this->transactionId,
                'PAYMENT_ERROR',
                $this->host . self::PAYMENTS_PATH,
                $e->getMessage(),
                'text/plain',
                ['trace' => $e->getTraceAsString()]
            );
            
            // Only log payment error if it's not already a BankingGatewayException (which we've already logged)
            if (!($e instanceof BankingGatewayException)) {
                $this->paymentLogger->logPaymentError($this->transactionId, $e->getMessage());
            }
            
            throw new BankingGatewayException("Payment failed: " . $e->getMessage(), 0, $e);
        }
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
            $this->paymentLogger->logCriticalError("Missing access token for payment");
            throw new BankingGatewayException("Authentication required");
        }
    }
}
