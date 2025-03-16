<?php

declare(strict_types=1);

namespace App\Tests\Gateway\PaymentMethods\ING;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Gateway\PaymentMethods\ING\IngOpenBankingPaymentConnector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IngOpenBankingPaymentConnectorTest extends TestCase
{
    private LoggerInterface $loggerMock;
    private HttpClientInterface $httpClientMock;
    private array $validConfig;
    
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        
        $this->validConfig = [
            'host' => 'https://api.ing.com',
            'client_id' => 'test-client-id',
            'merchant_id' => 'test-merchant-id',
            'amount' => 100.00,
            'currency' => 'EUR',
            'return_url' => 'https://example.com/return',
            'cert_path' => '/path/to/cert.pem',
            'key_path' => '/path/to/key.pem',
            'description' => 'Test payment description' // added field
        ];
    }
    
    /**
     * @test
     */
    public function makePayment_WithValidParameters_ReturnsPaymentResponse(): void
    {
        // Arrange
        // Mock token response
        $tokenResponseMock = $this->createMock(ResponseInterface::class);
        $tokenResponseMock->method('getContent')
            ->willReturn(json_encode([
                'access_token' => 'test-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]));
            
        // Mock payment response
        $paymentResponseMock = $this->createMock(ResponseInterface::class);
        $paymentResponseMock->method('getContent')
            ->willReturn(json_encode([
                'id' => 'payment-id-123',
                'paymentInitiationUrl' => 'https://pay.ing.com/initiate/123'
            ]));
            
        // Configure HTTP client mock to return our mock responses
        $this->httpClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($tokenResponseMock, $paymentResponseMock);
            
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->loggerMock,
            $this->httpClientMock
        );
        
        // Act
        $result = $connector->makePayment();

        // Assert
        $this->assertInstanceOf(PaymentResponse::class, $result);
        $this->assertEquals('payment-id-123', $result->getTransactionId());
        $this->assertEquals('https://pay.ing.com/initiate/123', $result->getPaymentUrl());
    }
    
    /**
     * @test
     */
    public function makePayment_WhenTokenRequestFails_ThrowsBankingGatewayException(): void
    {
        // Arrange
        $tokenResponseMock = $this->createMock(ResponseInterface::class);
        $tokenResponseMock->method('getContent')
            ->willReturn(json_encode(['error' => 'invalid_client']));
            
        $this->httpClientMock->method('request')
            ->willReturn($tokenResponseMock);
            
        $this->expectException(BankingGatewayException::class);
        
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->loggerMock,
            $this->httpClientMock
        );
        
        // Act
        $connector->makePayment();
    }
    
    /**
     * @test
     */
    public function makePayment_WhenPaymentRequestFails_ThrowsBankingGatewayException(): void
    {
        // Arrange
        // First return valid token response
        $tokenResponseMock = $this->createMock(ResponseInterface::class);
        $tokenResponseMock->method('getContent')
            ->willReturn(json_encode([
                'access_token' => 'test-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ]));
        
        // Then return error on payment request
        $paymentResponseMock = $this->createMock(ResponseInterface::class);
        $paymentResponseMock->method('getContent')
            ->willReturn(json_encode(['error' => 'payment_failed']));
            
        $this->httpClientMock->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($tokenResponseMock, $paymentResponseMock);
            
        $this->expectException(BankingGatewayException::class);
        
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->loggerMock,
            $this->httpClientMock
        );
        
        // Act
        $connector->makePayment();
    }

    /**
     * @test
     * @dataProvider invalidPaymentParametersProvider
     */
    public function validatePaymentParameters_WithInvalidParameters_ThrowsBankingGatewayException(
        array $config, 
        string $expectedException
    ): void {
        // Arrange
        $connector = new IngOpenBankingPaymentConnector(
            $config,
            $this->loggerMock,
            $this->httpClientMock
        );
        
        // Use reflection to access private method
        $reflectionClass = new \ReflectionClass(IngOpenBankingPaymentConnector::class);
        $validateMethod = $reflectionClass->getMethod('validatePaymentParameters');
        $validateMethod->setAccessible(true);

        $this->expectException(BankingGatewayException::class);
        $this->expectExceptionMessage($expectedException);
        
        // Act
        $validateMethod->invoke($connector);
    }
    
    public function invalidPaymentParametersProvider(): array
    {
        $baseConfig = [
            'host' => 'https://api.ing.com',
            'client_id' => 'test-client-id',
            'merchant_id' => 'test-merchant-id',
            'amount' => 100.00,
            'currency' => 'EUR',
            'return_url' => 'https://example.com/return',
            'cert_path' => '/path/to/cert.pem',
            'key_path' => '/path/to/key.pem',
            'description' => 'Test payment description' // added field
        ];
        
        return [
            'negative amount' => [
                array_merge($baseConfig, ['amount' => -10.00]),
                'Payment amount must be a positive number'
            ],
            'zero amount' => [
                array_merge($baseConfig, ['amount' => 0]),
                'Payment amount must be a positive number'
            ],
            'invalid currency' => [
                array_merge($baseConfig, ['currency' => 'EURO']),
                'Invalid currency code format'
            ],
            'empty return url' => [
                array_merge($baseConfig, ['return_url' => '']),
                'Invalid return URL'
            ],
            'invalid return url' => [
                array_merge($baseConfig, ['return_url' => 'not-a-url']),
                'Invalid return URL'
            ],
            'non-https return url' => [
                array_merge($baseConfig, ['return_url' => 'http://example.com/return']),
                'Return URL must use HTTPS protocol'
            ],
        ];
    }

    /**
     * @test
     */
    public function maskSensitiveData_WithSensitiveInformation_ReturnsMaskedData(): void
    {
        // Arrange
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->loggerMock,
            $this->httpClientMock
        );
        
        $reflectionClass = new \ReflectionClass(IngOpenBankingPaymentConnector::class);
        $maskMethod = $reflectionClass->getMethod('maskSensitiveData');
        $maskMethod->setAccessible(true);

        $sensitiveData = [
            'access_token' => 'very-sensitive-token-value',
            'client_id' => 'sensitive-client-id',
            'Authorization' => 'Bearer very-sensitive-token',
            'Merchant-Id' => 'merchant-12345',
            'api_key' => 'secret-api-key',
            'public_data' => 'this is fine to show',
            'nested' => [
                'access_token' => 'nested-token',
                'public' => 'public-nested-data'
            ]
        ];
        
        // Act
        $result = $maskMethod->invoke($connector, $sensitiveData);
        
        // Assert
        $this->assertEquals('very-s******', $result['access_token']);
        $this->assertEquals('sens****', $result['client_id']);
        $this->assertEquals('Bearer *****', $result['Authorization']);
        $this->assertEquals('merc****', $result['Merchant-Id']);
        $this->assertEquals('secr****', $result['api_key']);
        $this->assertEquals('this is fine to show', $result['public_data']);
        $this->assertEquals('nested******', $result['nested']['access_token']);
        $this->assertEquals('public-nested-data', $result['nested']['public']);
    }
}
