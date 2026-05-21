---
title: Usage
---

# Usage

This guide covers creating and applying promotions with the current model/service APIs.

## Create promotions

```php
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;

$promotion = Promotion::create([
    'name' => 'Summer Sale',
    'type' => PromotionType::Percentage,
    'discount_value' => 20,
    'is_active' => true,
]);
```

```php
$codePromo = Promotion::create([
    'name' => 'Welcome Discount',
    'code' => 'WELCOME10',
    'type' => PromotionType::Fixed,
    'discount_value' => 1000, // minor units
    'usage_limit' => 100,
    'per_customer_limit' => 1,
    'is_active' => true,
]);
```

## Use scopes

```php
$active = Promotion::query()->active()->get();
$automatic = Promotion::query()->active()->automatic()->get();
$coded = Promotion::query()->active()->withCode()->get();

$singleCode = Promotion::query()
    ->active()
    ->withCode()
    ->where('code', 'WELCOME10')
    ->first();
```

## Discounts

```php
$promotion->calculateDiscount(10_000); // cents in, cents out
$promotion->isActive();
$promotion->hasRemainingUsage();
$promotion->incrementUsage();
```

## Owner-aware querying

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

$ownerPromotions = Promotion::query()->forOwner($tenant)->get();
$ownerAndGlobal = Promotion::query()->forOwner($tenant, includeGlobal: true)->get();

$globalOnly = OwnerContext::withOwner(null, fn () =>
    Promotion::query()->forOwner()->get()
);
```

## Promotion service

```php
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Services\PromotionService;

$service = app(PromotionService::class);
$context = TargetingContext::fromCart($cart, [
    'channel' => 'web',
]);

$applicable = $service->getApplicablePromotions($context);
$best = $service->getBestPromotion($context);
$stackable = $service->getStackablePromotions($context);

$result = $service->calculateDiscounts($context, $subtotalInCents);
// ['discount' => int, 'applied' => Collection<Promotion>]
```

## Conditions payload shape

Promotion `conditions` are validated against the commerce-support targeting engine.

Empty conditions are treated as no conditions (`null`). Invalid payloads are rejected at write-time.
