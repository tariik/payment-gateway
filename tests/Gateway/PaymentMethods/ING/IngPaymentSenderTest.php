<?php

declare(strict_types=1);

namespace App\Tests\Gateway\PaymentMethods\ING;

use App\Gateway\PaymentMethods\ING\IngPaymentSender;
use App\Service\Logger\PaymentLoggerService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IngPaymentSenderTest extends TestCase
{
    private HttpClientInterface $httpClientMock;
    private IngPaymentSender $paymentSender;
    
    protected function setUp(): void
    {
        $loggerMock = $this->createMock(PaymentLoggerService::class);
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->paymentSender = new IngPaymentSender($this->httpClientMock, $loggerMock);
    }
    
    /**
     * @test
     */
    public function sendPaymentRequest_ReturnsDecodedResponse(): void
    {
        // Arrange
        $paymentData = [
            'fixedAmount' => ['value' => 100.00, 'currency' => 'EUR'],
            'description' => 'Test payment',
            'returnUrl' => 'https://example.com/return'
        ];
        
        $host = 'https://api.ing.com';
        $accessToken = 'test-access-token';
        $certPath = '/path/to/cert.pem';
        $keyPath = '/path/to/key.pem';
        $transactionId = 'test-transaction-id';
        
        $responseContent = json_encode([
            'id' => 'payment-id-123',
            'paymentInitiationUrl' => 'https://pay.ing.com/initiate/123'
        ]);
        
        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getContent')->willReturn($responseContent);
        
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.ing.com/payment-requests',
                $this->callback(function($options) use ($accessToken) {
                    return isset($options['headers']['Authorization']) 
                        && $options['headers']['Authorization'] === 'Bearer ' . $accessToken;
                })
            )
            ->willReturn($responseMock);
            
        // Act
        try {
            $result = $this->paymentSender->sendPaymentRequest(
                $paymentData,
                $host,
                $accessToken,
                $certPath,
                $keyPath,
                $transactionId
            );
            // Assert
            $this->assertEquals('payment-id-123', $result['id']);
            $this->assertEquals('https://pay.ing.com/initiate/123', $result['paymentInitiationUrl']);
        } catch (ClientExceptionInterface|RedirectionExceptionInterface|
                 ServerExceptionInterface|TransportExceptionInterface) {
        }


    }
}
