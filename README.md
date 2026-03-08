# Promotions

Automatic promotional discounts and campaigns for commerce applications.

## Features

- **Promotion Types** — Percentage off, fixed amount, and Buy X Get Y discounts
- **Automatic Promotions** — Code-free promotions that apply automatically
- **Promo Codes** — Optional code-based promotions
- **Usage Limits** — Total usage and per-customer limits
- **Scheduling** — Start and end dates for time-limited campaigns
- **Stacking** — Control whether promotions can stack with others
- **Priority** — Define which promotions take precedence
- **Targeting** — Apply conditions via commerce-support targeting engine
- **Multi-tenancy** — Owner scoping for multi-tenant applications
- **Activity Logging** — Track promotion changes via Spatie ActivityLog

## Requirements

- PHP 8.4+
- Laravel 12+
- commerce-support package

## Installation

```bash
composer require aiarmada/promotions
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag=promotions-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=promotions-config
```

## Quick Start

```php
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Enums\PromotionType;

// Create a 20% off promotion
$promotion = Promotion::create([
    'name' => 'Summer Sale',
    'type' => PromotionType::Percentage,
    'discount_value' => 20, // 20%
    'is_active' => true,
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
]);

// Create a promo code
$codePromo = Promotion::create([
    'name' => 'Welcome Discount',
    'code' => 'WELCOME10',
    'type' => PromotionType::Fixed,
    'discount_value' => 1000, // $10.00 in cents
    'usage_limit' => 100,
    'is_active' => true,
]);

// Calculate discount
$discount = $promotion->calculateDiscount(5000); // 1000 cents ($10)
```

## Configuration

```php
// config/promotions.php
return [
    'database' => [
        'table_prefix' => '',
        'tables' => [
            'promotions' => 'promotions',
            'promotionables' => 'promotionables',
        ],
        'json_column_type' => 'json',
    ],

    'features' => [
        'owner' => [
            'enabled' => false,
            'include_global' => true,
        ],
    ],

    'targeting' => [
        'cache_ttl' => 3600,
    ],
];
```

## Promotion Types

| Type | Description |
|------|-------------|
| `Percentage` | Percentage discount (e.g., 20% off) |
| `Fixed` | Fixed amount in cents (e.g., $10 off) |
| `BuyXGetY` | Buy X items, get Y free |

## License

MIT License. See [LICENSE](LICENSE) for details.
