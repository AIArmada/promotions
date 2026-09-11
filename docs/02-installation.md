---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 13+
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

## Expiry hygiene

The package registers `promotions:deactivate-expired` but does not schedule it
automatically. Add it to the host application's scheduler, for example:

```php
Schedule::command('promotions:deactivate-expired')->daily();
```

The command is safe to run from the application scheduler because promotion
eligibility still uses its date window at read time.

## Next Steps

- Configure the package: [Configuration](03-configuration.md)
- Start creating promotions: [Usage](04-usage.md)
