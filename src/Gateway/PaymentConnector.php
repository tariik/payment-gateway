<?php

namespace App\Gateway;

use App\DTO\PaymentResponse;
use App\Exception\BankingGatewayException;

interface PaymentConnector
{
     /**
     * Process a payment transaction
     * 
     * @throws BankingGatewayException
     */
    public function makePayment(): PaymentResponse;
}