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
        'json_column_type' => env('PROMOTIONS_JSON_COLUMN_TYPE', 'jsonb'),
        'tables' => [
            'promotions' => 'promotions',
            'promotionables' => 'promotionables',
        ],
    ],

    'defaults' => [
        'promotion_type' => 'percentage',
        'currency' => 'MYR',
    ],

    'features' => [
        'evaluate_deleted_products' => env('PROMOTIONS_EVALUATE_DELETED_PRODUCTS', false),
        'stack_evaluation' => env('PROMOTIONS_STACK_EVALUATION', 'best-only'),
        'promotion_limit' => env('PROMOTIONS_PROMOTION_LIMIT', 200),
    ],

    'owner' => [
        'enabled' => false,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
];
```

## Owner defaults

- The package ships with `enabled = false`; set `'owner.enabled' => true` in the published config for owner-scoped promotions. Once enabled, reads and writes are owner-aware.
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
$ownerEnabled = config('promotions.owner.enabled');
$includeGlobal = config('promotions.owner.include_global');
```

## Evaluation time and usage limits

`PromotionService::getApplicablePromotions()` delegates to the canonical `getApplicablePromotionsAsOf()` with the current instant, so current checkout totals are preserved through the single implementation. Use `getApplicablePromotionsAsOf()` directly for reports and historical evaluation. Per-customer limits are checked against owner-scoped order discount allocations when the Orders package is available. If Orders is not installed, the service logs a skip reason and continues without throwing.

Usage redemption uses an atomic increment-or-reject update. Call `tryIncrementUsage()` when the caller needs a boolean result; `incrementUsage()` throws a `LogicException` when the configured usage limit has already been reached.
