---
title: Promotion Service
---

# Promotion Service

The `PromotionService` provides business logic for finding and applying promotions based on context.

## Overview

The service integrates with the targeting engine from commerce-support to evaluate promotion conditions against a given context.

## Interface

```php
namespace AIArmada\Promotions\Contracts;

interface PromotionServiceInterface
{
    /**
     * Get all applicable promotions for the given context.
     *
     * @param array<string, mixed> $context
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotions(array $context): Collection;

    /**
     * Get the best single promotion for the amount.
     *
     * @param array<string, mixed> $context
     * @param int $amount Amount in cents
     * @return Promotion|null
     */
    public function getBestPromotion(array $context, int $amount): ?Promotion;

    /**
     * Get stackable promotions sorted by best discount.
     *
     * @param array<string, mixed> $context
     * @param int $amount Amount in cents
     * @return Collection<int, Promotion>
     */
    public function getStackablePromotions(array $context, int $amount): Collection;

    /**
     * Calculate discounts for all applicable promotions.
     *
     * @param array<string, mixed> $context
     * @param int $amount Amount in cents
     * @return array<string, int> Promotion ID => discount amount
     */
    public function calculateDiscounts(array $context, int $amount): array;
}
```

## Context Array

The context array provides data for targeting conditions:

```php
$context = [
    // Customer info
    'customer_id' => '550e8400-e29b-41d4-a716-446655440000',
    'customer_group' => 'vip',
    'is_first_order' => true,

    // Cart info
    'cart_total' => 15000, // cents
    'item_count' => 3,
    'product_ids' => ['prod-1', 'prod-2'],
    'category_ids' => ['cat-electronics'],

    // Location
    'shipping_country' => 'US',
    'shipping_state' => 'CA',

    // Custom attributes
    'has_subscription' => true,
];
```

## Usage Examples

### Finding Applicable Promotions

```php
use AIArmada\Promotions\Services\PromotionService;

$service = app(PromotionService::class);

$context = [
    'cart_total' => 15000,
    'customer_id' => $customer->id,
];

// Get all promotions that match the context
$promotions = $service->getApplicablePromotions($context);

foreach ($promotions as $promotion) {
    echo $promotion->name . ': ' . $promotion->calculateDiscount(15000);
}
```

### Getting the Best Promotion

When only one promotion can apply:

```php
$best = $service->getBestPromotion($context, 15000);

if ($best) {
    $discount = $best->calculateDiscount(15000);
    echo "Best deal: {$best->name} saves {$discount} cents";
}
```

### Stacking Promotions

When multiple promotions can combine:

```php
$stackable = $service->getStackablePromotions($context, 15000);

$totalDiscount = 0;
foreach ($stackable as $promo) {
    $discount = $promo->calculateDiscount(15000 - $totalDiscount);
    $totalDiscount += $discount;
}

echo "Total savings: {$totalDiscount} cents";
```

### Calculating All Discounts

Get a breakdown of all applicable discounts:

```php
$discounts = $service->calculateDiscounts($context, 15000);

// Returns: ['promo-id-1' => 2000, 'promo-id-2' => 1000]
foreach ($discounts as $promoId => $discount) {
    $promo = Promotion::find($promoId);
    echo "{$promo->name}: {$discount} cents\n";
}
```

## Dependency Injection

Bind a custom implementation:

```php
// In a service provider
$this->app->bind(
    PromotionServiceInterface::class,
    CustomPromotionService::class
);
```

## Extending the Service

Create a custom service for specialized logic:

```php
namespace App\Services;

use AIArmada\Promotions\Services\PromotionService;

class CustomPromotionService extends PromotionService
{
    public function getApplicablePromotions(array $context): Collection
    {
        // Add custom pre-filtering
        $promotions = parent::getApplicablePromotions($context);

        // Add custom post-filtering
        return $promotions->filter(fn ($p) => $this->customCheck($p, $context));
    }

    private function customCheck(Promotion $promo, array $context): bool
    {
        // Your custom logic
        return true;
    }
}
```
