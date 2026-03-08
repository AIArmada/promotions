---
title: Usage
---

# Usage

This guide covers creating, managing, and applying promotions.

## Creating Promotions

### Basic Promotion

```php
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\Enums\PromotionType;

$promotion = Promotion::create([
    'name' => 'Summer Sale',
    'type' => PromotionType::Percentage,
    'discount_value' => 20, // 20% off
    'is_active' => true,
]);
```

### Promo Code

```php
$codePromo = Promotion::create([
    'name' => 'Welcome Discount',
    'code' => 'WELCOME10',
    'type' => PromotionType::Fixed,
    'discount_value' => 1000, // $10.00 in cents
    'usage_limit' => 100,
    'is_active' => true,
]);
```

### Scheduled Campaign

```php
$campaign = Promotion::create([
    'name' => 'Black Friday',
    'type' => PromotionType::Percentage,
    'discount_value' => 30,
    'starts_at' => now()->setDate(2024, 11, 29),
    'ends_at' => now()->setDate(2024, 12, 2),
    'is_active' => true,
]);
```

### With Limits

```php
$limited = Promotion::create([
    'name' => 'VIP Discount',
    'type' => PromotionType::Percentage,
    'discount_value' => 25,
    'min_order_value' => 5000,     // Minimum $50 order
    'max_discount' => 2500,        // Cap at $25 discount
    'usage_limit' => 500,          // 500 total uses
    'usage_per_customer' => 1,     // Once per customer
    'is_active' => true,
]);
```

## Promotion Types

### Percentage Discount

```php
$promotion = Promotion::create([
    'name' => '20% Off',
    'type' => PromotionType::Percentage,
    'discount_value' => 20,
    'is_active' => true,
]);

// Calculate discount
$discount = $promotion->calculateDiscount(10000); // 2000 cents
```

### Fixed Amount

```php
$promotion = Promotion::create([
    'name' => '$10 Off',
    'type' => PromotionType::Fixed,
    'discount_value' => 1000, // cents
    'is_active' => true,
]);

// Calculate discount
$discount = $promotion->calculateDiscount(5000); // 1000 cents
$discount = $promotion->calculateDiscount(500);  // 500 cents (capped at order value)
```

### Buy X Get Y

```php
$promotion = Promotion::create([
    'name' => 'Buy 2 Get 1 Free',
    'type' => PromotionType::BuyXGetY,
    'discount_value' => 1, // Free items
    'conditions' => [
        'buy_quantity' => 2,
        'get_quantity' => 1,
    ],
    'is_active' => true,
]);
```

## Querying Promotions

### Active Promotions

```php
// Currently active
$active = Promotion::active()->get();

// Active automatic promotions (no code)
$automatic = Promotion::active()
    ->automatic()
    ->get();

// Active with specific code
$codePromo = Promotion::active()
    ->withCode('SUMMER20')
    ->first();
```

### By Priority

```php
// Highest priority first
$promotions = Promotion::active()
    ->orderByDesc('priority')
    ->get();
```

### Stackable Promotions

```php
$stackable = Promotion::active()
    ->where('is_stackable', true)
    ->get();
```

## Owner Scoping

When owner scoping is enabled:

```php
// Scope to specific owner
$promotions = Promotion::forOwner($tenant)->get();

// Query by owner type
$teamPromos = Promotion::where('owner_type', Team::class)
    ->where('owner_id', $team->id)
    ->get();
```

## Using the Promotion Service

### Get Applicable Promotions

```php
use AIArmada\Promotions\Services\PromotionService;

$service = app(PromotionService::class);

$context = [
    'cart_total' => 15000,
    'customer_id' => $customer->id,
    'product_ids' => [1, 2, 3],
];

$promotions = $service->getApplicablePromotions($context);
```

### Get Best Single Promotion

```php
$best = $service->getBestPromotion($context, 15000);
// Returns the promotion with highest discount
```

### Get Stackable Promotions

```php
$stackable = $service->getStackablePromotions($context, 15000);
// Returns promotions that can be combined
```

### Calculate All Discounts

```php
$discounts = $service->calculateDiscounts($context, 15000);
// Returns array of promotion => discount pairs
```

## Activity Logging

Promotions automatically log changes via Spatie ActivityLog:

```php
// View activity log
$promotion->activities()->get();

// Custom logging
activity()
    ->performedOn($promotion)
    ->causedBy($user)
    ->log('Promotion redeemed');
```
