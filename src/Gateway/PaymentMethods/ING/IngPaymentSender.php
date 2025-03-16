<?php

declare(strict_types=1);

namespace App\Gateway\PaymentMethods\ING;

use App\Service\Logger\PaymentLoggerService;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles sending payment requests to the ING API
 */
class IngPaymentSender
{
    private const PAYMENTS_PATH = '/payment-requests';

    /**
     * @var HttpClientInterface HTTP client for API requests
     */
    private HttpClientInterface $httpClient;

    /**
     * @var PaymentLoggerService Logger service for payment operations
     */
    private PaymentLoggerService $paymentLogger;

    /**
     * Constructor
     *
     * @param HttpClientInterface $httpClient HTTP client for API requests
     * @param PaymentLoggerService $paymentLogger Logger service for payment operations
     */
    public function __construct(
        HttpClientInterface $httpClient,
        PaymentLoggerService $paymentLogger
    ) {
        $this->httpClient = $httpClient;
        $this->paymentLogger = $paymentLogger;
    }

    /**
     * Sends a payment request to the ING API
     *
     * @param array $paymentData Payment request data
     * @param string $host API host
     * @param string $accessToken OAuth access token
     * @param string $certPath Path to SSL certificate
     * @param string $keyPath Path to private key
     * @param string $transactionId Transaction identifier for logging
     * @throws ClientExceptionInterface If there's an HTTP client error
     * @throws RedirectionExceptionInterface If there's an HTTP redirection error
     * @throws ServerExceptionInterface If there's an HTTP server error
     * @throws TransportExceptionInterface If there's an HTTP transport error
     * @return array API response data
     */
    public function sendPaymentRequest(
        array $paymentData,
        string $host,
        string $accessToken,
        string $certPath,
        string $keyPath,
        string $transactionId
    ): array {
        $paymentUrl = $host . self::PAYMENTS_PATH;
        $jsonBody = json_encode($paymentData);

        $this->paymentLogger->logToFile(
            $transactionId,
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
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => $jsonBody,
            'local_cert' => $certPath,
            'local_pk' => $keyPath,
        ]);

        $responseContent = $response->getContent();
        $this->paymentLogger->logToFile(
            $transactionId,
            'PAYMENT_RESPONSE',
            $paymentUrl,
            $responseContent,
            'application/json'
        );

        return json_decode($responseContent, true);
    }
}
