# Payment Gateway

PHP Payment Gateway Integration System using Symfony 6.4 API with Docker

## Requirements

- Docker
- Docker Compose

## Setup

1. Clone this repository
2. Make sure the `docker/php` directory exists
3. Run `docker-compose up -d` to start the containers
4. Access the PHP container: `docker exec -it payment_php bash`
5. Install dependencies: `composer install`
6. Access the API at http://localhost:8080/api

## Testing API with Command Line

You can test the API endpoints using command-line tools like curl:

### Process a payment
```bash
curl -X GET http://localhost:8080/api/payment/process
```

## API Endpoints

- GET `/api/payment/process` - Process a payment

## Development

- Symfony 6.4 framework
- RESTful API architecture
- Docker containerization

## Troubleshooting


