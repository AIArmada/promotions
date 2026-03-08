---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 12+
- aiarmada/commerce-support package

## Composer Installation

```bash
composer require aiarmada/promotions
```

## Migrations

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=promotions-migrations
php artisan migrate
```

This creates two tables:
- `promotions` — Main promotion records
- `promotionables` — Polymorphic pivot for promotion-model relationships

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=promotions-config
```

This creates `config/promotions.php`.

## Service Provider

The package auto-registers via Laravel's package discovery. For manual registration:

```php
// config/app.php
'providers' => [
    // ...
    AIArmada\Promotions\PromotionsServiceProvider::class,
],
```

## Verifying Installation

Check the promotion model is accessible:

```php
use AIArmada\Promotions\Models\Promotion;

// Should return an empty collection
Promotion::all();
```

## Next Steps

- Configure the package: [Configuration](03-configuration.md)
- Start creating promotions: [Usage](04-usage.md)
