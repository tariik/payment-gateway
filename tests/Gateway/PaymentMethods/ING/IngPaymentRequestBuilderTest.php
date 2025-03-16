<?php

declare(strict_types=1);

namespace App\Tests\Gateway\PaymentMethods\ING;

use App\Exception\BankingGatewayException;
use App\Gateway\PaymentMethods\ING\IngPaymentRequestBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class IngPaymentRequestBuilderTest extends TestCase
{
    private IngPaymentRequestBuilder $requestBuilder;
    
    protected function setUp(): void
    {
        $this->requestBuilder = new IngPaymentRequestBuilder();
    }

    /**
     * @test
     * @throws BankingGatewayException
     */
    public function buildPaymentRequest_WithValidParameters_ReturnsCorrectData(): void
    {
        // Act
        $result = $this->requestBuilder->buildPaymentRequest(
            100.00,
            'EUR',
            'Test payment',
            'https://example.com/return'
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals(100.00, $result['fixedAmount']['value']);
        $this->assertEquals('EUR', $result['fixedAmount']['currency']);
        $this->assertEquals('Test payment', $result['description']);
        $this->assertEquals('https://example.com/return', $result['returnUrl']);
        $this->assertEquals(1, $result['maximumAllowedPayments']);
        $this->assertArrayHasKey('purchaseId', $result);
        $this->assertArrayHasKey('validUntil', $result);
    }

    /**
     * @test
     * @dataProvider invalidPaymentParametersProvider
     * @throws ReflectionException
     */
    public function validatePaymentParameters_WithInvalidParameters_ThrowsBankingGatewayException(
        float $amount,
        string $currency,
        string $returnUrl,
        string $expectedException
    ): void {
        // Use reflection to access private method
        $reflectionClass = new ReflectionClass(IngPaymentRequestBuilder::class);
        $validateMethod = $reflectionClass->getMethod('validatePaymentParameters');

        $this->expectException(BankingGatewayException::class);
        $this->expectExceptionMessage($expectedException);
        
        // Act
        $validateMethod->invoke($this->requestBuilder, $amount, $currency, $returnUrl);
    }
    
    public function invalidPaymentParametersProvider(): array
    {
        return [
            'negative amount' => [
                -10.00, 'EUR', 'https://example.com/return', 'Payment amount must be a positive number'
            ],
            'zero amount' => [
                0, 'EUR', 'https://example.com/return', 'Payment amount must be a positive number'
            ],
            'invalid currency' => [
                100.00, 'EURO', 'https://example.com/return', 'Invalid currency code format'
            ],
            'empty return url' => [
                100.00, 'EUR', '', 'Invalid return URL'
            ],
            'invalid return url' => [
                100.00, 'EUR', 'not-a-url', 'Invalid return URL'
            ],
            'non-https return url' => [
                100.00, 'EUR', 'http://example.com/return', 'Return URL must use HTTPS protocol'
            ],
        ];
    }
}
