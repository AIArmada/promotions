---
title: Multi-tenancy
---

# Multi-tenancy

Promotions are owner-aware via `commerce-support`.

## Default posture

Owner mode is **off by default** and, unlike most packages, has no env override — `enabled` is
hardcoded to `false` in `config/promotions.php`:

```php
'features' => [
    'owner' => [
        'enabled' => false,
        'include_global' => false,
        'auto_assign_on_create' => true,
    ],
],
```

Set `'enabled' => true` in `config/promotions.php` (or publish the config and edit it) to turn
it on. The package's only env var is `PROMOTIONS_JSON_COLUMN_TYPE`.

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

// Global-only rows
$platformPromotions = Promotion::query()->globalOnly()->get();

// Cross-tenant read (privileged)
$everything = Promotion::query()->withoutOwnerScope()->get();
```

`owner = null` means global-only, never "all owners" — global rows appear in an owner-scoped
query only when `includeGlobal: true` is passed.

For global-only operations, enter explicit global context:

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

$global = OwnerContext::withOwner(null, fn () =>
    Promotion::query()->forOwner()->get()
);
```

## Filament integration

`filament-promotions` scopes list/query surfaces through
`AIArmada\CommerceSupport\Support\Filament\OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false)`
in `PromotionResource::getEloquentQuery()`, and its bulk actions re-resolve the promotion with
`OwnerWriteGuard::findOrFailForOwner()` before issuing vouchers.
