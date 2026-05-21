---
title: Troubleshooting
---

# Troubleshooting

## Promotion not applying

Check these first:

1. `is_active` is true.
2. `starts_at` / `ends_at` window is currently valid.
3. usage limits are not exhausted.
4. owner scope matches current owner context.
5. targeting conditions validate and match the runtime context.

## Invalid targeting conditions error on save

Conditions are validated at write-time.

- Use `null` for no conditions.
- Use targeting engine shape (`mode` + `rules`, or `mode=custom` + `expression`).
- Empty arrays are normalized to `null`.

## Owner-scope surprises

Inspect current owner settings:

```php
dump([
    'enabled' => config('promotions.features.owner.enabled'),
    'include_global' => config('promotions.features.owner.include_global'),
]);
```

## Verify owner assignment

```php
$promotion = Promotion::find($id);

dump([
    'owner_type' => $promotion?->owner_type,
    'owner_id' => $promotion?->owner_id,
]);
```

## Scope debugging

```php
$query = Promotion::query()->forOwner($tenant);
dump($query->toSql(), $query->getBindings());
```

## Discount math debugging

```php
$discount = $promotion->calculateDiscount($subtotalInCents);
```

`discount_value` and method inputs are in minor units for fixed discounts.
