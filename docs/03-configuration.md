---
title: Configuration
---

# Configuration

The promotions configuration file controls database settings, feature flags, and targeting behavior.

## Full Configuration

```php
// config/promotions.php
return [

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'database' => [
        'table_prefix' => '',
        'tables' => [
            'promotions' => 'promotions',
            'promotionables' => 'promotionables',
        ],
        'json_column_type' => 'json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'owner' => [
            'enabled' => false,
            'include_global' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Targeting
    |--------------------------------------------------------------------------
    */

    'targeting' => [
        'cache_ttl' => 3600,
    ],

];
```

## Database Configuration

### Table Prefix

Add a prefix to all promotions tables:

```php
'table_prefix' => 'promo_',
// Results in: promo_promotions, promo_promotionables
```

### Custom Table Names

Override individual table names:

```php
'tables' => [
    'promotions' => 'my_promotions',
    'promotionables' => 'my_promotionables',
],
```

### JSON Column Type

Configure the JSON column type for your database:

```php
'json_column_type' => 'json',     // MySQL 5.7+, PostgreSQL
'json_column_type' => 'jsonb',    // PostgreSQL (indexed)
'json_column_type' => 'text',     // SQLite (fallback)
```

## Feature Configuration

### Owner Scoping (Multi-tenancy)

Enable owner scoping for multi-tenant applications:

```php
'features' => [
    'owner' => [
        'enabled' => true,        // Enable owner scoping
        'include_global' => true, // Include promotions with owner=null
    ],
],
```

When enabled:
- Promotions are automatically scoped to the current owner
- Use `Promotion::forOwner($owner)` for explicit scoping
- Global promotions (owner=null) can be included or excluded

## Targeting Configuration

### Cache TTL

Control how long targeting conditions are cached:

```php
'targeting' => [
    'cache_ttl' => 3600, // 1 hour in seconds
],
```

Set to `0` to disable caching.

## Environment Variables

For sensitive or deploy-time values, use environment variables:

```php
'features' => [
    'owner' => [
        'enabled' => env('PROMOTIONS_OWNER_ENABLED', false),
    ],
],
```

## Configuration Access

Access configuration values in code:

```php
// Get table name
$table = config('promotions.database.tables.promotions');

// Check if owner scoping is enabled
$ownerEnabled = config('promotions.features.owner.enabled');

// Get targeting cache TTL
$ttl = config('promotions.targeting.cache_ttl');
```
