---
title: Multi-tenancy
---

# Multi-tenancy

The promotions package supports multi-tenant applications through owner scoping.

## Enabling Owner Scoping

```php
// config/promotions.php
'features' => [
    'owner' => [
        'enabled' => true,
        'include_global' => true,
    ],
],
```

## Owner Relationship

Promotions use a polymorphic owner relationship:

```php
// Migration creates these columns:
$table->nullableMorphs('owner');
// Results in: owner_type, owner_id
```

## Creating Tenant Promotions

```php
use AIArmada\Promotions\Models\Promotion;

// Create promotion for specific tenant
$promotion = Promotion::create([
    'name' => 'Tenant Sale',
    'type' => PromotionType::Percentage,
    'discount_value' => 15,
    'owner_type' => Team::class,
    'owner_id' => $team->id,
    'is_active' => true,
]);

// Using the owner relation
$promotion = new Promotion([
    'name' => 'Store Promotion',
    'type' => PromotionType::Fixed,
    'discount_value' => 500,
    'is_active' => true,
]);
$promotion->owner()->associate($store);
$promotion->save();
```

## Querying by Owner

### For Specific Owner

```php
// Get promotions for a specific owner
$promotions = Promotion::forOwner($tenant)->get();
```

### With Global Promotions

When `include_global` is enabled, queries include promotions where `owner_id` is null:

```php
// Returns tenant promotions + global promotions
$promotions = Promotion::forOwner($tenant)->get();
```

### Global Only

```php
// Get only promotions without an owner
$global = Promotion::whereNull('owner_id')->get();
```

## Owner Scope Helper

The `PromotionsOwnerScope` class provides configuration checks:

```php
use AIArmada\Promotions\Support\PromotionsOwnerScope;

// Check if owner scoping is enabled
if (PromotionsOwnerScope::isEnabled()) {
    // Owner scoping active
}

// Check if global promotions should be included
if (PromotionsOwnerScope::includeGlobal()) {
    // Include owner_id = null promotions
}
```

## Service Provider Integration

If using the commerce-support `OwnerResolverInterface`:

```php
// Bind the owner resolver
$this->app->bind(
    OwnerResolverInterface::class,
    fn () => new TenantOwnerResolver()
);
```

The Promotion model's `scopeForOwner` will respect this binding automatically.

## Security Considerations

When owner scoping is enabled:

1. **Always validate owner context** — Never trust client-provided owner IDs
2. **Use `forOwner()` scope** — Don't rely on UI filtering alone
3. **Validate in actions** — Re-verify owner in action handlers
4. **Cross-tenant operations** — Explicitly opt-out with `withoutOwnerScope()`

```php
// Safe pattern in controllers/actions
$promotion = Promotion::forOwner($currentTenant)
    ->findOrFail($promotionId);

// Unsafe - don't do this
$promotion = Promotion::findOrFail($promotionId);
```

## Filament Integration

When using filament-promotions with multi-tenancy:

1. Override `getEloquentQuery()` in the resource
2. Validate owner in form actions
3. Use owner-scoped relationship selects

See the filament-promotions documentation for detailed integration patterns.
