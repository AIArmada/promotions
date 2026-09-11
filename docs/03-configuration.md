---
title: Configuration
---

# Configuration

The promotions package exposes database and owner-scoping settings.

## Full configuration

```php
// config/promotions.php
return [
    'database' => [
        'tables' => [
            'promotions' => 'promotions',
            'promotionables' => 'promotionables',
        ],
    ],

    'features' => [
        'owner' => [
            'enabled' => env('PROMOTIONS_OWNER_ENABLED', false),
            'include_global' => env('PROMOTIONS_OWNER_INCLUDE_GLOBAL', false),
            'auto_assign_on_create' => env('PROMOTIONS_OWNER_AUTO_ASSIGN_ON_CREATE', true),
        ],
    ],
];
```

## Owner defaults

- The package ships with `enabled = false`; set `PROMOTIONS_OWNER_ENABLED=true` for owner-scoped promotions. Once enabled, reads and writes are owner-aware.
- `include_global = false` is fail-closed; owner-scoped queries do not include global rows unless explicitly requested.
- `auto_assign_on_create = true` assigns owner automatically when an owner context exists.

Promotion owner tuples are enforced by the model and by the order-redemption listener. Cross-owner administration must enter an explicit `OwnerContext::withOwner(...)` scope.

## Database settings

- `database.tables.*` overrides table names.

## Environment overrides

If needed, override in your app-level published config. The package itself does not ship a `promotions.targeting.*` config section.

## Accessing config in code

```php
$table = config('promotions.database.tables.promotions');
$ownerEnabled = config('promotions.features.owner.enabled');
$includeGlobal = config('promotions.features.owner.include_global');
```

## Evaluation time and usage limits

`PromotionService::getApplicablePromotions()` and the pricing bridge retain wall-clock behavior. The additive `getApplicablePromotionsAsOf()` method is for reports and historical evaluation; it does not change current checkout totals. Per-customer limits are checked against owner-scoped order discount allocations when the Orders package is available. If Orders is not installed, the service logs a skip reason and continues without throwing.

Usage redemption uses an atomic increment-or-reject update. Call `tryIncrementUsage()` when the caller needs a boolean result; `incrementUsage()` throws a `LogicException` when the configured usage limit has already been reached.
