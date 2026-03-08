---
title: Troubleshooting
---

# Troubleshooting

Common issues and solutions when working with the promotions package.

## Promotions Not Applying

### Check Active Status

```php
$promotion = Promotion::find($id);

// Is it active?
dump($promotion->is_active); // Should be true

// Is it within the date range?
$now = now();
dump($promotion->starts_at?->isPast()); // Should be true or null
dump($promotion->ends_at?->isFuture()); // Should be true or null
```

### Check Query Scopes

```php
// Compare raw vs scoped query
$allPromos = Promotion::all();
$activePromos = Promotion::active()->get();

dump($allPromos->count(), $activePromos->count());
```

### Check Conditions

```php
// View the conditions
dump($promotion->conditions);

// Test condition evaluation manually
$context = ['customer_group' => 'vip', 'cart_total' => 5000];
$applicable = app(PromotionService::class)
    ->getApplicablePromotions($context);

dump($applicable->pluck('name'));
```

## Wrong Discount Amount

### Verify Calculation

```php
$promotion = Promotion::find($id);
$amount = 10000; // $100 in cents

// Manual calculation
$discount = $promotion->calculateDiscount($amount);

dump([
    'type' => $promotion->type->value,
    'value' => $promotion->discount_value,
    'amount' => $amount,
    'discount' => $discount,
    'max_discount' => $promotion->max_discount,
]);
```

### Check Max Discount Cap

```php
// If max_discount is set, percentage discounts are capped
$promo = Promotion::create([
    'type' => PromotionType::Percentage,
    'discount_value' => 50, // 50%
    'max_discount' => 2500, // Max $25
]);

$promo->calculateDiscount(10000); // Returns 2500, not 5000
```

## Owner Scoping Issues

### Verify Configuration

```php
dump([
    'enabled' => config('promotions.features.owner.enabled'),
    'include_global' => config('promotions.features.owner.include_global'),
]);
```

### Check Owner Assignment

```php
$promotion = Promotion::find($id);

dump([
    'owner_type' => $promotion->owner_type,
    'owner_id' => $promotion->owner_id,
    'owner' => $promotion->owner,
]);
```

### Debug Scope Query

```php
$query = Promotion::forOwner($tenant);
dump($query->toSql(), $query->getBindings());
```

## Migration Issues

### Table Already Exists

```bash
# Check if tables exist
php artisan tinker
>>> Schema::hasTable('promotions')
>>> Schema::hasTable('promotionables')
```

### Column Type Mismatch

```php
// Ensure json_column_type matches your database
// config/promotions.php
'database' => [
    'json_column_type' => 'json', // or 'jsonb' for PostgreSQL
],
```

## Service Resolution

### Interface Not Bound

```php
// Check binding
dump(app()->bound(PromotionServiceInterface::class));

// Manual resolution
$service = app(PromotionService::class);
```

### Custom Implementation Not Used

```php
// Verify your binding runs after the package provider
// In your AppServiceProvider::register()
$this->app->bind(
    PromotionServiceInterface::class,
    CustomPromotionService::class
);
```

## Activity Logging

### Logs Not Recording

```php
// Check if activity logging is configured
dump(config('activitylog'));

// Verify the model uses the trait
$promotion = new Promotion();
dump(method_exists($promotion, 'activities'));
```

### View Activity Log

```php
$promotion = Promotion::find($id);
$activities = $promotion->activities()->latest()->get();

foreach ($activities as $activity) {
    dump([
        'description' => $activity->description,
        'properties' => $activity->properties,
        'causer' => $activity->causer,
    ]);
}
```

## Performance Issues

### Slow Queries

```php
// Add indexes if missing
Schema::table('promotions', function (Blueprint $table) {
    $table->index(['is_active', 'starts_at', 'ends_at']);
    $table->index(['owner_type', 'owner_id']);
    $table->index('code');
});
```

### Cache Targeting Results

```php
// Increase cache TTL
// config/promotions.php
'targeting' => [
    'cache_ttl' => 7200, // 2 hours
],
```

## Getting Help

1. Check the [documentation](01-overview.md)
2. Review the [configuration options](03-configuration.md)
3. Search existing issues on GitHub
4. Open a new issue with:
   - PHP and Laravel versions
   - Package version
   - Minimal reproduction steps
   - Expected vs actual behavior
