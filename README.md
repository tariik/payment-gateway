# Payment Gateway

A flexible payment gateway integration system that supports multiple payment providers with a clean, extensible architecture.

## Requirements

- Docker
- Docker Compose

## Setup

1. Clone this repository
2. Make sure the `docker/php` directory exists
3. Copy the `.env.example` file to `.env` and update the values as needed
4. Run `docker-compose up -d` to start the containers
5. Access the PHP container: `docker exec -it payment_php bash`
6. Install dependencies: `composer install`
7. Access the API at http://localhost:8080/api/payment/process

## Project Structure

```
payment-gateway/
├── config/
│   └── certificates/            # SSL certificates for API authentication
│       ├── example_client_tls.cer
│       └── example_client_tls.key
├── src/
│   ├── DTO/                     # Data Transfer Objects
│   │   ├── PaymentRequest.php
│   │   └── PaymentResponse.php
│   ├── Exception/               # Custom exception classes
│   │   └── BankingGatewayException.php
│   ├── Gateway/                 # Payment gateway implementation
│   │   ├── BankingGatewayLauncher.php
│   │   ├── PaymentConnector.php
│   │   ├── PaymentMethodIDs.php
│   │   └── PaymentMethods/      # Specific payment method implementations
│   │       └── ING/             # ING Open Banking implementation
│   │           ├── IngOpenBankingPaymentConnector.php
│   │           ├── IngPaymentRequestBuilder.php
│   │           ├── IngPaymentSender.php
│   │           └── IngPaymentResponseProcessor.php
│   └── Service/                 # Service classes
│       └── Logger/              # Logging services
│           └── PaymentLoggerService.php
├── tests/                       # Test suite (mirrors src structure)
│   └── Gateway/
│       └── PaymentMethods/
│           └── ING/
│               ├── IngOpenBankingPaymentConnectorTest.php
│               ├── IngPaymentRequestBuilderTest.php
│               ├── IngPaymentSenderTest.php
│               └── IngPaymentResponseProcessorTest.php
└── var/                         # Runtime files
    └── log/                     # Log files
        └── payments/
            └── ing/             # ING payment logs
```

## Architecture

The payment gateway system follows a modular, layered architecture:

1. **Launcher Layer**: `BankingGatewayLauncher` serves as the entry point, handling payment requests and selecting the appropriate connector.

2. **Connector Layer**: Connectors like `IngOpenBankingPaymentConnector` implement the `PaymentConnector` interface and orchestrate the payment flow for specific payment providers.

3. **Processing Layer**: Each connector delegates to specialized components:
   - `IngPaymentRequestBuilder`: Builds payment requests according to API specifications
   - `IngPaymentSender`: Handles API communication with the payment provider
   - `IngPaymentResponseProcessor`: Processes API responses into standardized `PaymentResponse` objects

4. **Support Services**: 
   - `PaymentLoggerService`: Provides specialized logging for payment transactions

## Adding a New Payment Method

To add a new payment method:

1. Create a new folder under `src/Gateway/PaymentMethods/` for your provider
2. Create implementation classes following the Single Responsibility Principle:
   - A main connector class implementing `PaymentConnector`
   - Helper classes for request building, API communication, and response processing
3. Add the payment method ID to `PaymentMethodIDs.php`
4. Update `BankingGatewayLauncher.php` to handle the new payment method

## Testing API with Command Line

You can test the API endpoints using command-line tools like curl:

### Process a payment
```bash
curl -X GET http://localhost:8080/api/payment/process
```

## Running Tests

### Via Docker

```bash
# Execute tests from outside the container
docker exec payment_php bin/phpunit

# Or access the container first
docker exec -it payment_php bash
bin/phpunit
```

## API Endpoints

- GET `/api/payment/process` - Process a payment

## Banking Methods

Designed for multi-method banking integration; at the moment, only the ING method is implemented.

## Development

- Symfony 6.4 framework
- RESTful API architecture
- Docker containerization


