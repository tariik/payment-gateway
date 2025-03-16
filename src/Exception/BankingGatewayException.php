<?php

namespace App\Exception;

use Exception;

/**
 * BankingGatewayException
 * 
 * Exception thrown when there is an error communicating with the banking gateway
 * or when the gateway returns an error response.
 */

class BankingGatewayException extends Exception
{

}