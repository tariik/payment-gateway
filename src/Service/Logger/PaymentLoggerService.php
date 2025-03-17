<?php

declare(strict_types=1);

namespace App\Service\Logger;

use Exception;
use Psr\Log\LoggerInterface;

class PaymentLoggerService
{
    private LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    public function logStartPayment(string $transactionId, float $amount, string $currency, string $merchantId): void
    {
        $this->logger->info("Starting ING Open Banking payment", [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'merchant_id' => $merchantId
        ]);
    }
    
    public function logTokenSuccess(string $transactionId, array $data): void
    {
        $this->logger->debug("ING Token obtained", [
            'transaction_id' => $transactionId,
            'token_expires' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null
        ]);
    }
    
    public function logTokenError(string $transactionId, string $message, array $context = []): void
    {
        $this->logger->error("ING Token Error: $message", [
            'transaction_id' => $transactionId,
            'context' => $this->maskSensitiveData($context)
        ]);
    }
    
    public function logPaymentSuccess(string $transactionId, string $paymentId, string $initiationUrl): void
    {
        $this->logger->info("ING Payment successful", [
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'initiation_url' => $initiationUrl
        ]);
    }
    
    public function logPaymentError(string $transactionId, string $message, array $context = []): void
    {
        $this->logger->error("ING Payment Error: $message", [
            'transaction_id' => $transactionId,
            'context' => $this->maskSensitiveData($context)
        ]);
    }
    
    public function logCriticalError(string $message): void
    {
        $this->logger->critical($message);
    }
    
    public function logToFile(
        string $transactionId,
        string $action,
        string $url,
        string $rawData,
        string $contentType,
        array $additionalData = []
    ): void {
        try {
            $logDir = __DIR__ . '/../../../var/log/payments/ing/';
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
                $transactionId,
                $url,
                json_encode($maskedData, JSON_PRETTY_PRINT)
            );

            file_put_contents($logFile, $logContent, FILE_APPEND);
        } catch (Exception $e) {
            $this->logger->error("Failed to write log file: " . $e->getMessage());
        }
    }
    
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
}
