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
        'json_column_type' => 'json',
    ],

    'features' => [
        'owner' => [
            'enabled' => true,
            'include_global' => false,
            'auto_assign_on_create' => true,
        ],
    ],
];
```

## Owner defaults

- `enabled = true` keeps promotion reads/writes owner-aware by default.
- `include_global = false` is fail-closed; owner-scoped queries do not include global rows unless explicitly requested.
- `auto_assign_on_create = true` assigns owner automatically when an owner context exists.

## Database settings

- `database.tables.*` overrides table names.
- `database.json_column_type` can be switched to `text` for engines without native JSON support.

## Environment overrides

If needed, override in your app-level published config. The package itself does not ship a `promotions.targeting.*` config section.

## Accessing config in code

```php
$table = config('promotions.database.tables.promotions');
$ownerEnabled = config('promotions.features.owner.enabled');
$includeGlobal = config('promotions.features.owner.include_global');
```
