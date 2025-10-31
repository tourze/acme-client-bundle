# ACME Client Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)
[![License](https://img.shields.io/packagist/l/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/acme-client-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/acme-client-bundle)

A Symfony bundle that provides a complete ACME client implementation for automatic SSL/TLS certificate management.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Console Commands](#console-commands)
- [Services](#services)
- [Database Entities](#database-entities)
- [Exception Handling](#exception-handling)
- [Advanced Usage](#advanced-usage)
- [Security](#security)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Features

- **Complete ACME Protocol Support**: Full implementation of ACME protocol for certificate automation
- **Account Management**: Register and manage ACME accounts with public/private key pairs
- **Certificate Lifecycle**: Automated certificate ordering, validation, and renewal
- **Challenge Support**: HTTP-01, DNS-01, and TLS-ALPN-01 challenge types
- **Operation Logging**: Comprehensive logging of all ACME operations and exceptions
- **Rate Limit Handling**: Built-in rate limit detection and retry mechanisms
- **Console Commands**: CLI tools for certificate management and monitoring

## Installation

```bash
composer require tourze/acme-client-bundle
```

### Register the Bundle

If you're not using Symfony Flex, add the bundle to your `config/bundles.php`:

```php
return [
    // ...
    Tourze\ACMEClientBundle\ACMEClientBundle::class => ['all' => true],
];
```

## Configuration

Create a configuration file `config/packages/acme_client.yaml`:

```yaml
acme_client:
    # ACME server directory URL
    # Production: https://acme-v02.api.letsencrypt.org/directory
    # Staging: https://acme-staging-v02.api.letsencrypt.org/directory
    directory_url: '%env(ACME_DIRECTORY_URL)%'
    
    # HTTP client configuration
    http_client:
        max_retries: 3
        retry_delay: 1000  # milliseconds
        timeout: 30  # seconds
    
    # Account configuration
    account:
        contact_email: '%env(ACME_CONTACT_EMAIL)%'
        key_size: 4096  # RSA key size in bits
        
    # Certificate configuration
    certificate:
        days_before_expiry: 30  # Days before expiry to start renewal
        auto_renewal: true  # Enable automatic renewal
        
    # Logging configuration
    logging:
        enabled: true
        level: info  # debug, info, warning, error
```

### Environment Variables

```bash
# .env.local
ACME_DIRECTORY_URL="https://acme-staging-v02.api.letsencrypt.org/directory"
ACME_CONTACT_EMAIL="admin@example.com"
```

## Quick Start

### Basic Usage

```php
<?php

use Tourze\ACMEClientBundle\Service\AccountService;
use Tourze\ACMEClientBundle\Service\OrderService;

// Register an account
$account = $accountService->registerAccount(
    contacts: ['mailto:admin@example.com'],
    termsOfServiceAgreed: true
);

// Order a certificate
$order = $orderService->createOrder(
    account: $account,
    domains: ['example.com', 'www.example.com']
);
```

## Console Commands

### 1. Register an ACME Account

```bash
# Register a new account with Let's Encrypt staging
php bin/console acme:account:register admin@example.com

# Register with production Let's Encrypt
php bin/console acme:account:register admin@example.com \
    --directory-url=https://acme-v02.api.letsencrypt.org/directory

# Register with custom key size
php bin/console acme:account:register admin@example.com \
    --key-size=4096 --agree-tos

# Register with existing private key
php bin/console acme:account:register admin@example.com \
    --key-file=/path/to/private.key --agree-tos
```

### 2. Create a Certificate Order

```bash
# Order a certificate for a single domain
php bin/console acme:order:create example.com

# Order a certificate for multiple domains
php bin/console acme:order:create example.com www.example.com api.example.com

# Order with specific account
php bin/console acme:order:create example.com --account-id=123

# Order with wildcard domain
php bin/console acme:order:create "*.example.com" example.com
```

### 3. Renew Certificates

```bash
# Renew all certificates expiring within 30 days
php bin/console acme:cert:renew

# Renew certificates expiring within custom days
php bin/console acme:cert:renew --days-before-expiry=60

# Renew specific certificate
php bin/console acme:cert:renew --certificate-id=123

# Dry run to see what would be renewed
php bin/console acme:cert:renew --dry-run
```

### 4. Query Operation Logs

```bash
# View recent ACME operations
php bin/console acme:log:query

# Filter by log level
php bin/console acme:log:query --level=error

# Filter by date range
php bin/console acme:log:query --start-date="2024-01-01" --end-date="2024-01-31"

# Filter by operation type
php bin/console acme:log:query --operation=order_create
```

## Services

### AccountService

Manages ACME accounts:

```php
use Tourze\ACMEClientBundle\Service\AccountService;

// Create a new account
$account = $accountService->registerAccount(
    contacts: ['mailto:admin@example.com'],
    termsOfServiceAgreed: true,
    privateKeyPem: null // Auto-generate if null
);

// Find account by email
$account = $accountService->findByEmail('admin@example.com');

// Update account contact
$accountService->updateContact($account, ['mailto:new@example.com']);

// Deactivate account
$accountService->deactivateAccount($account);
```

### OrderService

Manages certificate orders:

```php
use Tourze\ACMEClientBundle\Service\OrderService;

// Create a new order
$order = $orderService->createOrder(
    account: $account,
    domains: ['example.com', 'www.example.com']
);

// Process authorizations
foreach ($order->getAuthorizations() as $authorization) {
    $orderService->processAuthorization($authorization);
}

// Finalize order with CSR
$certificate = $orderService->finalizeOrder($order, $csrPem);

// Check order status
$status = $orderService->updateOrderStatus($order);
```

### CertificateService

Manages certificates:

```php
use Tourze\ACMEClientBundle\Service\CertificateService;

// Find certificates expiring soon
$expiring = $certificateService->findExpiringCertificates(days: 30);

// Download certificate
$pemChain = $certificateService->downloadCertificate($certificate);

// Revoke certificate
$certificateService->revokeCertificate($certificate, reason: 'keyCompromise');

// Check certificate validity
$isValid = $certificateService->isCertificateValid($certificate);
```

### ChallengeService

Handles ACME challenges:

```php
use Tourze\ACMEClientBundle\Service\ChallengeService;

// Get challenge by type
$challenge = $challengeService->getChallengeByType(
    authorization: $authorization,
    type: 'http-01'
);

// Prepare challenge response
$challengeService->prepareChallenge($challenge);

// Trigger validation
$challengeService->triggerValidation($challenge);

// Clean up after validation
$challengeService->cleanupChallenge($challenge);
```

## Database Entities

The bundle provides the following entities:

- **Account**: ACME account with contact information and key pair
- **Order**: Certificate order with associated authorizations
- **Authorization**: Domain authorization with challenges
- **Challenge**: Individual challenge (HTTP-01, DNS-01, etc.)
- **Certificate**: Issued certificate with expiration tracking
- **Identifier**: Domain identifier for orders
- **AcmeOperationLog**: Log of all ACME operations
- **AcmeExceptionLog**: Log of exceptions and errors

## Exception Handling

The bundle provides specific exception types:

```php
use Tourze\ACMEClientBundle\Exception\AcmeClientException;
use Tourze\ACMEClientBundle\Exception\AcmeServerException;
use Tourze\ACMEClientBundle\Exception\AcmeValidationException;
use Tourze\ACMEClientBundle\Exception\AcmeRateLimitException;

try {
    $order = $orderService->createOrder($account, $domains);
} catch (AcmeRateLimitException $e) {
    // Handle rate limit with retry after
    $retryAfter = $e->getRetryAfter();
    $this->logger->warning("Rate limit hit, retry after {$retryAfter} seconds");
} catch (AcmeValidationException $e) {
    // Handle validation errors
    $this->logger->error("Validation failed: " . $e->getMessage());
} catch (AcmeServerException $e) {
    // Handle server errors
    $this->logger->error("Server error: " . $e->getMessage());
} catch (AcmeClientException $e) {
    // Handle general ACME errors
    $this->logger->error("ACME error: " . $e->getMessage());
}
```

## Advanced Usage

### Custom Challenge Handlers

Implement custom challenge validation logic:

```php
use Tourze\ACMEClientBundle\Service\ChallengeService;
use Tourze\ACMEClientBundle\Entity\Challenge;

class CustomChallengeHandler
{
    public function handleDnsChallenge(Challenge $challenge): bool
    {
        // Implement DNS record creation
        $record = "_acme-challenge.{$challenge->getDomain()}";
        $value = $challenge->getKeyAuthorization();
        
        // Create DNS TXT record
        return $this->dnsProvider->createTxtRecord($record, $value);
    }
}
```

### Event Subscribers

Listen to ACME events:

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\ACMEClientBundle\Event\CertificateOrderedEvent;

class CertificateEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CertificateOrderedEvent::class => 'onCertificateOrdered',
        ];
    }
    
    public function onCertificateOrdered(CertificateOrderedEvent $event): void
    {
        // Send notification, update monitoring, etc.
        $certificate = $event->getCertificate();
        $this->notificationService->sendCertificateIssuedEmail($certificate);
    }
}
```

### Custom Logging

Configure detailed operation logging:

```yaml
acme_client:
    logging:
        enabled: true
        level: debug
        channels: ['acme']
        
monolog:
    channels: ['acme']
    handlers:
        acme_file:
            type: stream
            path: "%kernel.logs_dir%/acme.log"
            level: debug
            channels: ['acme']
```

### Automated Certificate Renewal

Set up a cron job for automatic certificate renewal:

```bash
# Crontab entry - run daily at 2 AM
0 2 * * * cd /path/to/project && php bin/console acme:cert:renew --quiet
```

## Security

### Key Management

- **Private keys** are automatically generated and stored securely
- Keys are encrypted when stored in the database
- Consider using environment variables for sensitive configuration

### Rate Limiting

The bundle handles ACME rate limits automatically:

- Detects rate limit responses (HTTP 429)
- Implements exponential backoff
- Logs rate limit encounters for monitoring

### Certificate Validation

- Certificates are validated before storage
- Expiration dates are tracked automatically
- Invalid certificates trigger appropriate exceptions

### Security Considerations

- Store private keys securely with restricted file permissions (0600)
- Use HTTPS for all ACME communications
- Monitor operation logs for security events
- Implement proper access controls for certificate management commands
- Regularly rotate account keys
- Use staging environment for testing to avoid rate limits

## Testing

Run the test suite:

```bash
# Run all tests
./vendor/bin/phpunit packages/acme-client-bundle/tests

# Run with coverage
./vendor/bin/phpunit packages/acme-client-bundle/tests --coverage-html coverage

# Run specific test suite
./vendor/bin/phpunit packages/acme-client-bundle/tests/Service

# Run with PHPStan
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/acme-client-bundle
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- All tests pass
- Code follows PSR-12 coding standards
- PHPStan analysis passes at level 8
- Documentation is updated

## License

This bundle is released under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/tourze/php-monorepo/issues)
- **Documentation**: [ACME Protocol RFC 8555](https://tools.ietf.org/html/rfc8555)
- **Let's Encrypt**: [Documentation](https://letsencrypt.org/docs/)