---
title: Promotion Service
---

# Promotion Service

The Actions in `AIArmada\Promotions\Actions` are the preferred orchestration API. `PromotionService` remains available as a service API for direct evaluation and reporting use cases.

`PromotionService` finds and evaluates automatic promotions against a `TargetingContext`.

## Interface summary

```php
public function getApplicablePromotions(TargetingContext $context): Collection;
public function getApplicablePromotionsAsOf(TargetingContext $context, CarbonImmutable $asOf): Collection;
public function getBestPromotion(TargetingContext $context): ?Promotion;
public function findApplicableCodePromotion(string $code, TargetingContext $context): ?Promotion;
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

- `getApplicablePromotions()` delegates to `getApplicablePromotionsAsOf($context, CarbonImmutable::now())`.
- The query chain is `Promotion::query()->activeAt($asOf)->automatic()->forOwner()`, further narrowed by
  `min_purchase_amount` and `min_quantity` when the cart value/quantity is greater than zero.
- `getStackablePromotions()` filters `getApplicablePromotions()` on `is_stackable`.
- Code-based promotions need `findApplicableCodePromotion()` — `automatic()` only returns rows
  with a `null` `code`.
- Promotion `conditions` are evaluated by the commerce-support targeting engine.
- If a conditions payload is invalid, it is rejected at write-time before service evaluation.
- `calculateDiscounts()` returns `array{discount: int, applied: Collection<int, Promotion>}`;
  `discount` is in minor units.
