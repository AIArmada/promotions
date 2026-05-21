---
title: Multi-tenancy
---

# Multi-tenancy

Promotions are owner-aware via `commerce-support`.

## Default posture

```php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
],
```

## Owner columns

Promotions migration includes:

```php
$table->nullableMorphs('owner');
```

## Safe create/update behavior

- If owner mode is enabled and an owner context exists, new promotions are auto-assigned (unless owner fields are explicitly set).
- Cross-owner writes are blocked.
- Owned writes without owner context are blocked.

## Querying patterns

```php
$owned = Promotion::query()->forOwner($tenant)->get();
$ownedAndGlobal = Promotion::query()->forOwner($tenant, includeGlobal: true)->get();
```

For global-only operations, enter explicit global context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

$global = OwnerContext::withOwner(null, fn () =>
    Promotion::query()->forOwner()->get()
);
```

## Filament integration

`filament-promotions` scopes list/query surfaces through `PromotionsOwnerScope::applyToOwnedQuery()` and re-checks destructive actions against the current owner.
