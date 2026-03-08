---
title: Targeting
---

# Targeting

Promotions integrate with the targeting engine from commerce-support to apply complex conditions.

## Overview

The `conditions` JSON column stores targeting rules that are evaluated against a context array. This allows promotions to target specific customers, products, order values, and more.

## Condition Structure

Conditions follow the targeting engine format:

```php
$promotion = Promotion::create([
    'name' => 'VIP Customer Discount',
    'type' => PromotionType::Percentage,
    'discount_value' => 25,
    'conditions' => [
        'rules' => [
            [
                'field' => 'customer_group',
                'operator' => 'equals',
                'value' => 'vip',
            ],
        ],
        'match' => 'all', // 'all' or 'any'
    ],
    'is_active' => true,
]);
```

## Available Operators

| Operator | Description |
|----------|-------------|
| `equals` | Exact match |
| `not_equals` | Not equal |
| `greater_than` | Numeric comparison |
| `less_than` | Numeric comparison |
| `in` | Value in array |
| `not_in` | Value not in array |
| `contains` | String/array contains |
| `starts_with` | String prefix |
| `ends_with` | String suffix |

## Common Targeting Examples

### Minimum Order Value

```php
'conditions' => [
    'rules' => [
        [
            'field' => 'cart_total',
            'operator' => 'greater_than',
            'value' => 5000, // $50 minimum
        ],
    ],
],
```

### Specific Products

```php
'conditions' => [
    'rules' => [
        [
            'field' => 'product_ids',
            'operator' => 'contains',
            'value' => 'prod-123',
        ],
    ],
],
```

### Product Categories

```php
'conditions' => [
    'rules' => [
        [
            'field' => 'category_ids',
            'operator' => 'in',
            'value' => ['electronics', 'computers'],
        ],
    ],
],
```

### First-Time Customers

```php
'conditions' => [
    'rules' => [
        [
            'field' => 'is_first_order',
            'operator' => 'equals',
            'value' => true,
        ],
    ],
],
```

### Customer Segment

```php
'conditions' => [
    'rules' => [
        [
            'field' => 'customer_group',
            'operator' => 'in',
            'value' => ['vip', 'wholesale'],
        ],
    ],
],
```

### Geographic Targeting

```php
'conditions' => [
    'rules' => [
        [
            'field' => 'shipping_country',
            'operator' => 'in',
            'value' => ['US', 'CA', 'GB'],
        ],
    ],
],
```

## Complex Conditions

### All Rules Must Match

```php
'conditions' => [
    'match' => 'all',
    'rules' => [
        [
            'field' => 'customer_group',
            'operator' => 'equals',
            'value' => 'vip',
        ],
        [
            'field' => 'cart_total',
            'operator' => 'greater_than',
            'value' => 10000,
        ],
    ],
],
```

### Any Rule Can Match

```php
'conditions' => [
    'match' => 'any',
    'rules' => [
        [
            'field' => 'is_first_order',
            'operator' => 'equals',
            'value' => true,
        ],
        [
            'field' => 'customer_group',
            'operator' => 'equals',
            'value' => 'vip',
        ],
    ],
],
```

## Nested Groups

```php
'conditions' => [
    'match' => 'all',
    'rules' => [
        [
            'field' => 'cart_total',
            'operator' => 'greater_than',
            'value' => 5000,
        ],
    ],
    'groups' => [
        [
            'match' => 'any',
            'rules' => [
                [
                    'field' => 'category_ids',
                    'operator' => 'contains',
                    'value' => 'sale',
                ],
                [
                    'field' => 'has_subscription',
                    'operator' => 'equals',
                    'value' => true,
                ],
            ],
        ],
    ],
],
```

## Evaluating Conditions

The PromotionService evaluates conditions automatically:

```php
$context = [
    'customer_group' => 'vip',
    'cart_total' => 15000,
    'is_first_order' => false,
];

// Only returns promotions where conditions match
$applicable = $service->getApplicablePromotions($context);
```

## Caching

Targeting results can be cached via the config:

```php
// config/promotions.php
'targeting' => [
    'cache_ttl' => 3600, // Cache for 1 hour
],
```
