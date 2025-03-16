# Payment Gateway

PHP Payment Gateway Integration System using Symfony 6.4 API with Docker

This app offers secure, efficient payment processing via robust API integration with banks.

The system supports multiple banking providers (currently featuring ING Open Banking integration).

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
├── docker/                           # Docker configuration
│   ├── docker-compose.yml            # Docker services definition
│   ├── nginx/
│   │   └── default.conf              # Nginx server configuration
│   └── php/
│       └── Dockerfile                # PHP container configuration
├── src/
│   ├── Controller/
│   │   └── PaymentController.php     # Payment API endpoints
│   ├── DTO/
│   │   └── PaymentResponse.php       # Data transfer objects for payment responses
│   ├── Exception/
│   │   └── BankingGatewayException.php  # Gateway specific exceptions
│   ├── Gateway/
│   │   ├── BankingGatewayLauncher.php   # Gateway factory/launcher
│   │   ├── PaymentGatewayInterface.php  # Gateway contract
│   │   └── PaymentMethods/
│   │       └── ING/
│   │           └── IngOpenBankingPaymentConnector.php  # ING Open Banking integration
├── tests/
│   ├── Gateway/
│   │   └── PaymentMethods/
│   │       └── ING/
│   │           └── IngOpenBankingPaymentConnectorTest.php  # Unit tests for ING connector
└── .env                                                    # Environment configuration
```

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


