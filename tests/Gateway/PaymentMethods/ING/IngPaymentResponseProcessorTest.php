<?php

declare(strict_types=1);

namespace App\Tests\Gateway\PaymentMethods\ING;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;
use App\Gateway\PaymentMethods\ING\IngPaymentResponseProcessor;
use App\Service\Logger\PaymentLoggerService;
use PHPUnit\Framework\TestCase;

class IngPaymentResponseProcessorTest extends TestCase
{
    private PaymentLoggerService $loggerMock;
    private IngPaymentResponseProcessor $responseProcessor;
    
    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(PaymentLoggerService::class);
        $this->responseProcessor = new IngPaymentResponseProcessor($this->loggerMock);
    }

    /**
     * @test
     * @throws BankingGatewayException
     */
    public function processPaymentResponse_WithValidData_ReturnsPaymentResponse(): void
    {
        // Arrange
        $responseData = [
            'id' => 'payment-id-123',
            'paymentInitiationUrl' => 'https://pay.ing.com/initiate/123',
            'additionalData' => 'some-extra-info'
        ];
        
        $transactionId = 'test-transaction-id';
        
        $this->loggerMock->expects($this->once())
            ->method('logPaymentSuccess')
            ->with($transactionId, 'payment-id-123', 'https://pay.ing.com/initiate/123');
            
        // Act
        $result = $this->responseProcessor->processPaymentResponse($responseData, $transactionId);
        
        // Assert
        $this->assertInstanceOf(PaymentResponse::class, $result);
        $this->assertEquals('payment-id-123', $result->getTransactionId());
        $this->assertEquals('https://pay.ing.com/initiate/123', $result->getPaymentUrl());
        $this->assertSame($responseData, $result->getRawData());
    }
    
    /**
     * @test
     * @dataProvider invalidResponseDataProvider
     */
    public function processPaymentResponse_WithInvalidData_ThrowsBankingGatewayException(array $responseData): void
    {
        // Arrange
        $transactionId = 'test-transaction-id';
        
        $this->loggerMock->expects($this->once())
            ->method('logPaymentError')
            ->with($transactionId, 'Invalid payment response structure', $responseData);
            
        $this->expectException(BankingGatewayException::class);
        $this->expectExceptionMessage('Invalid payment response from ING');
        
        // Act
        $this->responseProcessor->processPaymentResponse($responseData, $transactionId);
    }
    
    public function invalidResponseDataProvider(): array
    {
        return [
            'missing id' => [['paymentInitiationUrl' => 'https://pay.ing.com/initiate/123']],
            'missing url' => [['id' => 'payment-id-123']],
            'empty array' => [[]],
            'null values' => [['id' => null, 'paymentInitiationUrl' => null]],
        ];
    }
}
