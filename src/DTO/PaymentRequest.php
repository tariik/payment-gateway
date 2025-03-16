<?php

namespace App\DTO;

class PaymentRequest
{

    private string $currency;

    private float $amount;
    

    private string $paymentMethod;
    
    private ?string $status = null;

    private ?string $returnUrl;

    private ?string $description = null;

    public function getCurrency(): string
    {
        return $this->currency;
    }
    
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }
    
    public function getAmount(): float
    {
        return $this->amount;
    }
    
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }
    
    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }
    
    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->status;
    }
    
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get the return URL for payment redirection
     *
     * @return string|null The URL to redirect to after payment processing
     */
    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    /**
     * Set the return URL for payment redirection
     *
     * @param string|null $returnUrl The URL to redirect to after payment processing
     * @return self Returns the current instance for method chaining
     */
    public function setReturnUrl(?string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

}