<?php

namespace App\Exception;

use RuntimeException;

/**
 * PaymentProcessingException
 * 
 * This exception is thrown when an error occurs during payment processing.
 * It can represent various payment failures such as:
 * - Gateway communication errors
 * - Payment rejection by the processor
 * - Invalid payment details
 * - Transaction failures
 * 
 * @package App\Exception
 */
class PaymentProcessingException extends RuntimeException
{
}