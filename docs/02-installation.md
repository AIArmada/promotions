---
title: Installation
---

# Installation

## Requirements

- PHP 8.5+
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

No scheduling is required. Promotion eligibility is derived: `scopeActiveAt()`
(the canonical scope) and `isActiveAt()` both apply the date window at read
time, so a promotion that has ended stops discounting regardless of its stored
`is_active` flag. There is no expiry sweep command.

For dashboards and admin display, `scopeCurrentlyActive()` and
the `is_currently_active` attribute report the narrower "an operator still considers this
running" view — enabled and not past `ends_at`. Unlike the canonical scope they
ignore the usage limit, because a promotion at its cap is still a running
promotion for reporting. These back the Filament nav badge, the Active column,
and `PromotionPerformanceInsights`.

## Next Steps

- Configure the package: [Configuration](03-configuration.md)
- Start creating promotions: [Usage](04-usage.md)
