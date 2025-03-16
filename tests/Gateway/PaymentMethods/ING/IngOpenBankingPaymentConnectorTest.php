<?php

declare(strict_types=1);

namespace App\Tests\Gateway\PaymentMethods\ING;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Gateway\PaymentMethods\ING\IngOpenBankingPaymentConnector;
use App\Gateway\PaymentMethods\ING\IngPaymentRequestBuilder;
use App\Gateway\PaymentMethods\ING\IngPaymentResponseProcessor;
use App\Gateway\PaymentMethods\ING\IngPaymentSender;
use App\Service\Logger\PaymentLoggerService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IngOpenBankingPaymentConnectorTest extends TestCase
{
    private PaymentLoggerService $paymentLoggerMock;
    private HttpClientInterface $httpClientMock;
    private IngPaymentRequestBuilder $requestBuilderMock;
    private IngPaymentSender $paymentSenderMock;
    private IngPaymentResponseProcessor $responseProcessorMock;
    private array $validConfig;
    
    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $this->paymentLoggerMock = $this->createMock(PaymentLoggerService::class);
        $this->httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->requestBuilderMock = $this->createMock(IngPaymentRequestBuilder::class);
        $this->paymentSenderMock = $this->createMock(IngPaymentSender::class);
        $this->responseProcessorMock = $this->createMock(IngPaymentResponseProcessor::class);
        
        $this->validConfig = [
            'host' => 'https://api.ing.com',
            'client_id' => 'test-client-id',
            'merchant_id' => 'test-merchant-id',
            'amount' => 100.00,
            'currency' => 'EUR',
            'return_url' => 'https://example.com/return',
            'cert_path' => '/path/to/cert.pem',
            'key_path' => '/path/to/key.pem',
            'description' => 'Test payment description'
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
            
        // Configure HTTP client mock to return our mock response for token request
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($tokenResponseMock);
            
        // Configure paymentLogger mock
        $this->paymentLoggerMock->expects($this->once())
            ->method('logStartPayment');
            
        $this->paymentLoggerMock->expects($this->once())
            ->method('logTokenSuccess');
        
        // Create connector with actual dependencies for constructor
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->paymentLoggerMock,
            $this->httpClientMock
        );
        
        // Set our mocks using reflection
        $reflectionClass = new ReflectionClass(IngOpenBankingPaymentConnector::class);
        
        $requestBuilderProperty = $reflectionClass->getProperty('requestBuilder');
        $requestBuilderProperty->setAccessible(true);
        $requestBuilderProperty->setValue($connector, $this->requestBuilderMock);
        
        $paymentSenderProperty = $reflectionClass->getProperty('paymentSender');
        $paymentSenderProperty->setAccessible(true);
        $paymentSenderProperty->setValue($connector, $this->paymentSenderMock);
        
        $responseProcessorProperty = $reflectionClass->getProperty('responseProcessor');
        $responseProcessorProperty->setAccessible(true);
        $responseProcessorProperty->setValue($connector, $this->responseProcessorMock);
        
        // Set up behavior for the mocked services
        $paymentData = [
            'fixedAmount' => ['value' => 100.00, 'currency' => 'EUR'],
            'description' => 'Test payment',
            'returnUrl' => 'https://example.com/return'
        ];
        
        $apiResponse = ['id' => 'payment-id-123', 'paymentInitiationUrl' => 'https://pay.ing.com/initiate/123'];
        $expectedResponse = new PaymentResponse('payment-id-123', 'https://pay.ing.com/initiate/123', $apiResponse);
        
        $this->requestBuilderMock->expects($this->once())
            ->method('buildPaymentRequest')
            ->with(100.00, 'EUR', 'Test payment description', 'https://example.com/return')
            ->willReturn($paymentData);
            
        $this->paymentSenderMock->expects($this->once())
            ->method('sendPaymentRequest')
            ->willReturn($apiResponse);
            
        $this->responseProcessorMock->expects($this->once())
            ->method('processPaymentResponse')
            ->with($apiResponse, $this->anything())
            ->willReturn($expectedResponse);
            
        // Act
        $result = $connector->makePayment();

        // Assert
        $this->assertSame($expectedResponse, $result);
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
        
        // Configure paymentLogger mock
        $this->paymentLoggerMock->expects($this->atLeastOnce())
            ->method('logToFile');
            
        $this->paymentLoggerMock->expects($this->once())
            ->method('logStartPayment');
            
        $this->paymentLoggerMock->expects($this->exactly(1))
            ->method('logTokenError')
            ->with(
                $this->anything(),
                'Missing access token in response',
                $this->anything()
            );
            
        $this->expectException(BankingGatewayException::class);
        
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->paymentLoggerMock,
            $this->httpClientMock
        );
        
        // Act
        $connector->makePayment();
    }
    
    /**
     * @test
     */
    public function makePayment_WhenPaymentCreationFails_ThrowsBankingGatewayException(): void
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
            
        // Configure HTTP client mock
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($tokenResponseMock);
            
        // Create connector with actual dependencies for constructor
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->paymentLoggerMock,
            $this->httpClientMock
        );
        
        // Set our mocks using reflection
        $reflectionClass = new ReflectionClass(IngOpenBankingPaymentConnector::class);
        
        $requestBuilderProperty = $reflectionClass->getProperty('requestBuilder');
        $requestBuilderProperty->setAccessible(true);
        $requestBuilderProperty->setValue($connector, $this->requestBuilderMock);
        
        $paymentSenderProperty = $reflectionClass->getProperty('paymentSender');
        $paymentSenderProperty->setAccessible(true);
        $paymentSenderProperty->setValue($connector, $this->paymentSenderMock);
        
        // Make the request builder throw an exception
        $this->requestBuilderMock->expects($this->once())
            ->method('buildPaymentRequest')
            ->willThrowException(new BankingGatewayException('Invalid payment parameters'));
            
        $this->expectException(BankingGatewayException::class);
        $this->expectExceptionMessage('Payment failed: Invalid payment parameters');
        
        // Act
        $connector->makePayment();
    }

    /**
     * @test
     */
    public function validateAccessToken_WhenTokenIsMissing_ThrowsBankingGatewayException(): void
    {
        // Arrange
        $connector = new IngOpenBankingPaymentConnector(
            $this->validConfig,
            $this->paymentLoggerMock,
            $this->httpClientMock
        );
        
        // Use reflection to access private method
        $reflectionClass = new ReflectionClass(IngOpenBankingPaymentConnector::class);
        $validateMethod = $reflectionClass->getMethod('validateAccessToken');
        $validateMethod->setAccessible(true);
        
        // Set access token to null
        $accessTokenProperty = $reflectionClass->getProperty('accessToken');
        $accessTokenProperty->setAccessible(true);
        $accessTokenProperty->setValue($connector, null);

        $this->expectException(BankingGatewayException::class);
        $this->expectExceptionMessage('Authentication required');
        
        // Act
        $validateMethod->invoke($connector);
    }
}
