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

- `cart_value`
- `cart_quantity`
- `product_in_cart`
- `category_in_cart`
- `user_segment`
- `first_purchase`
- `channel`
- `currency`
- `payment_method`

## Validation behavior

- `conditions = null` → no targeting restrictions.
- `conditions = []` → normalized to `null` on save.
- invalid conditions → rejected with validation/argument errors on write.

## Runtime evaluation

`PromotionService` evaluates stored conditions against a `TargetingContext` and returns only matching promotions.
