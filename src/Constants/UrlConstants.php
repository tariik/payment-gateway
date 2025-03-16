<?php

namespace App\Constants;

class UrlConstants
{
    /**
     * Base URLs for different environments
     */
    public const WEBSHOP_BASE_URL = 'https://www.webshop.com';

    /**
     * Return URL paths
     */
    public const PAYMENT_RETURN_PATH = '/return';
    
    /**
     * Gets the appropriate base URL based on the current environment
     */
    public static function getWebshopBaseUrl(): string
    {
        return self::WEBSHOP_BASE_URL;
    }
    
    /**
     * Builds the payment return URL with query parameters
     */
    public static function buildPaymentReturnUrl(string $purchaseId): string
    {
        return self::getWebshopBaseUrl() . self::PAYMENT_RETURN_PATH . "?purchaseId=$purchaseId";
    }
}