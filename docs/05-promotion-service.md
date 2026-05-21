---
title: Promotion Service
---

# Promotion Service

`PromotionService` finds and evaluates automatic promotions against a `TargetingContext`.

## Interface summary

```php
public function getApplicablePromotions(TargetingContext $context): Collection;
public function getBestPromotion(TargetingContext $context): ?Promotion;
public function getStackablePromotions(TargetingContext $context): Collection;
public function calculateDiscounts(TargetingContext $context, int $subtotalInCents): array;
```

## Basic usage

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
```

## Calculate discount result

```php
$result = $service->calculateDiscounts($context, $subtotalInCents);

$discount = $result['discount'];
$applied = $result['applied']; // Collection<Promotion>
```

## Notes

- The service reads `Promotion::active()->automatic()->forOwner()`.
- Promotion `conditions` are evaluated by the commerce-support targeting engine.
- If a conditions payload is invalid, it is rejected at write-time before service evaluation.
