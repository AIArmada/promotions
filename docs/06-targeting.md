---
title: Targeting
---

# Targeting

Promotions use the commerce-support targeting engine. The `conditions` column must follow targeting-engine format and is validated on save.

## Supported top-level formats

### Rule mode (`all` / `any`)

```php
'conditions' => [
    'mode' => 'all',
    'rules' => [
        [
            'type' => 'cart_value',
            'operator' => '>=',
            'value' => 5000,
        ],
    ],
],
```

### Custom boolean expression mode

```php
'conditions' => [
    'mode' => 'custom',
    'expression' => [
        'and' => [
            [
                'type' => 'cart_value',
                'operator' => '>=',
                'value' => 5000,
            ],
            [
                'or' => [
                    [
                        'type' => 'channel',
                        'operator' => '=',
                        'value' => 'web',
                    ],
                    [
                        'type' => 'user_segment',
                        'operator' => 'in',
                        'values' => ['vip'],
                    ],
                ],
            ],
        ],
    ],
],
```

## Common rule types

`AIArmada\CommerceSupport\Targeting\Enums\TargetingRuleType` is the full list. `mode` accepts
`all`, `any`, or `custom` (`TargetingMode`).

- User: `user_segment`, `user_attribute`, `first_purchase`, `clv`
- Cart: `cart_value`, `cart_quantity`, `product_in_cart`, `product_quantity`, `category_in_cart`,
  `metadata`, `item_attribute`, `item_constraint`, `coupon_usage_limit`, `payment_method`
- Time: `time_window`, `day_of_week`, `date_range`
- Context: `channel`, `device`, `geographic`, `referrer`, `referral_source`, `currency`

> **info**
> Rule types whose `requiresArrayValues()` is true — `user_segment`, `category_in_cart`,
> `product_in_cart`, `day_of_week`, `geographic` — must use `values => [...]`, not `value`.
> Use `TargetingRuleType::getOperators()` for the legal operator set per type: `cart_value`
> and `cart_quantity` accept `=`, `!=`, `>`, `>=`, `<`, `<=`, `between`; `user_segment`
> accepts `in`, `not_in`, `contains_any`, `contains_all`.

## Validation behavior

- `conditions = null` → no targeting restrictions.
- `conditions = []` → normalized to `null` on save.
- a non-array, non-null `conditions` → `InvalidArgumentException` on save.
- a structurally invalid payload → `InvalidArgumentException` listing
  `TargetingEngine::validate()` errors on save.

## Runtime evaluation

`PromotionService` evaluates stored conditions against a `TargetingContext` and returns only matching promotions.

For promotion eligibility checks, use `PromotionService::getApplicablePromotions($context)`; it applies the targeting rules while preserving the package's active-window and owner-scoping behavior.
