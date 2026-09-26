---
title: Usage
---

# Usage

## Canonical API: Actions

These Action classes are the recommended entry points for promotion operations.

```php
use AIArmada\Promotions\Actions\CreatePromotion;
use AIArmada\Promotions\Actions\DeactivatePromotion;

// Create a promotion — CreatePromotion/DeactivatePromotion expose handle(),
// so resolve them from the container rather than calling ::run()
$promotion = app(CreatePromotion::class)->handle([
    'name' => 'Summer Sale',
    'type' => 'percentage',
    'discount_value' => 20,
    'is_active' => true,
]);

// Calculate the discount amount
$discountInCents = $promotion->calculateDiscount($subtotalInCents);

// Deactivate a promotion
app(DeactivatePromotion::class)->handle($promotion);
```

See `docs/05-promotion-service.md` for the service API.

This guide covers creating and applying promotions with the current model/service APIs.

## Create promotions

```php
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;

$promotion = Promotion::create([
    'name' => 'Summer Sale',
    'type' => PromotionType::Percentage,
    'discount_value' => 20,
    'is_active' => true,
]);
```

```php
$codePromo = Promotion::create([
    'name' => 'Welcome Discount',
    'code' => 'WELCOME10',
    'type' => PromotionType::Fixed,
    'discount_value' => 1000, // minor units
    'usage_limit' => 100,
    'per_customer_limit' => 1,
    'is_active' => true,
]);
```

## Use scopes

```php
$active = Promotion::query()->active()->get();
$automatic = Promotion::query()->active()->automatic()->get();
$coded = Promotion::query()->active()->withCode()->get();

$singleCode = Promotion::query()
    ->active()
    ->withCode()
    ->where('code', 'WELCOME10')
    ->first();
```

`active()` is a wall-clock alias for `activeAt(now)`. There are three activity scopes, and they
are not interchangeable:

| Scope | Checks | Use for |
|---|---|---|
| `activeAt($now)` | `is_active` + `starts_at` + `ends_at` + usage limit | The canonical scope. Anything that decides whether a promotion may apply |
| `active()` | same, at `CarbonImmutable::now()` | Wall-clock convenience alias |
| `currentlyActive($now = null)` | `is_active` + `ends_at` only | Dashboards and reporting |

```php
use Carbon\CarbonImmutable;

Promotion::query()->active()->get();                                  // == activeAt(now)
Promotion::query()->activeAt(CarbonImmutable::parse('2026-01-01'))->get();
Promotion::query()->currentlyActive()->get();                         // narrower
Promotion::query()->currentlyActive(CarbonImmutable::parse('2026-01-01'))->get();

$promotion->is_currently_active;                                      // bool accessor
$promotion->isActiveAt(CarbonImmutable::now());                       // per-instance check
```

`currentlyActive()` is deliberately **narrower** than `activeAt()`: it skips the `starts_at`
window and the usage-limit check, because a promotion sitting at its usage cap is still a
running promotion from an operator's point of view. Do not use it to decide whether a discount
applies — use `activeAt()`.

> **info**
> There is no scheduled sweep rewriting a stale `status` column, so a promotion whose `ends_at`
> has passed can still read as active in the raw column. `currentlyActive()` and
> `is_currently_active` derive the truth from the date, which is why the Filament resource
> binds its Active column and filter to `is_currently_active` rather than `is_active`.

## Discounts

```php
$promotion->calculateDiscount(10_000); // cents in, cents out
$promotion->isActive();
$promotion->hasRemainingUsage();
$promotion->incrementUsage();
```

## Limits, codes, and evaluation cost

- `per_customer_limit` is checked in `matchesContext()` via owner-scoped order history (`forOwner(OwnerContext::resolve(), false)`). History is scanned once per evaluation and shared across candidates. When the customer id is missing, `aiarmada/orders` is missing, or history is unreadable, the check logs at `debug` level and denies the promotion (fail closed).
- Usage increments are atomic: `tryIncrementUsage()` runs a single `whereColumn('usage_count', '<', 'usage_limit')->increment()` guarded by `forOwner($this->owner, false)`; `incrementUsage()` throws on exhaustion.
- Codes are normalized to trimmed-uppercase on save and looked up exactly (`where('code', mb_strtoupper(mb_trim($code)))`). Blank input resolves to `null` (automatic promotion).
- Evaluation applies cheap SQL pre-filters (`activeAt`, `min_purchase_amount`, `min_quantity`) then `chunkById(100)` with full `matchesContextAt()` per row.
- When `aiarmada/products` is absent, `products()`/`categories()` return an empty (`whereRaw('1 = 0')`) relation rather than throwing.

```php
use AIArmada\Promotions\Models\Promotion;

$promotion->tryIncrementUsage(); // bool; false when usage_limit reached
$found = $service->findApplicableCodePromotion('  welcome10 ', $context);
```

## Owner-aware querying

```php
use AIArmada\CommerceSupport\Support\OwnerContext;

$ownerPromotions = Promotion::query()->forOwner($tenant)->get();
$ownerAndGlobal = Promotion::query()->forOwner($tenant, includeGlobal: true)->get();

$globalOnly = OwnerContext::withOwner(null, fn () =>
    Promotion::query()->forOwner()->get()
);
```

## Promotion service

```php
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Services\PromotionService;

$service = app(PromotionService::class);
$context = TargetingContext::fromCart($cart, [
    'channel' => 'web',
]);

$applicable = $service->getApplicablePromotions($context);
$best = $service->getBestPromotion($context);
$stackable = $service->getStackablePromotions($context);

$result = $service->calculateDiscounts($context, $subtotalInCents);
// ['discount' => int, 'applied' => Collection<Promotion>]
```

For historical or as-of reporting, pass an explicit instant:

```php
$historical = $service->getApplicablePromotionsAsOf($context, $asOf);
```

Existing pricing callers continue using `getApplicablePromotions()` and `calculateDiscounts()`; both resolve through the same as-of core with the current instant.

## Issue one-time vouchers from a promotion

When the vouchers package is installed, promotions can generate one-time vouchers directly.

```php
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Actions\IssueVouchersFromPromotion;

$issued = IssueVouchersFromPromotion::run($promotion, 25, 'RECOVER');

// Global promotions require explicit global context
$issuedFromGlobal = OwnerContext::withOwner(null, fn () =>
    IssueVouchersFromPromotion::run($globalPromotion, 10, 'GLOBAL')
);
```

Issued vouchers inherit the promotion's schedule, minimum purchase amount, owner tuple, and targeting payload. The action also records `promotion_id` plus source-promotion metadata on each voucher so downstream checkout payloads and Filament reporting can trace voucher usage back to the originating promotion.

Defaults applied by the action:

- `usage_limit = 1`
- active status
- generated voucher codes using the promotion code/name or a custom prefix

## Conditions payload shape

Promotion `conditions` are validated against the commerce-support targeting engine.

Empty conditions are treated as no conditions (`null`). Invalid payloads are rejected at write-time.

## Per-customer limits fail closed

Promotions with a `per_customer_limit` only match contexts that carry a customer identity. Guest checkouts (no `customer_id` and no user) and unreadable order histories exclude the promotion instead of ignoring the limit. During a single evaluation the customer's order history is scanned once and shared across all candidates.

## Code uniqueness is per owner

Promotion codes are unique within an owner scope (`owner_type`, `owner_id`, `code`), so different owners may reuse the same code. `CreatePromotion` validates input (type, discount ranges, non-negative limits, `ends_at` after `starts_at`) and rejects duplicate codes inside the caller's scope with an `InvalidArgumentException`. When owner mode is enabled the action requires a resolved owner or explicit global context.

## Deactivation bookkeeping

`DeactivatePromotion` stamps `deactivated_at` alongside `is_active = false` and
dispatches `PromotionDeactivated`. It is invoked from the Filament edit page's
Deactivate action, which is visible whenever `is_currently_active` is false —
so an ended promotion can still be retired explicitly. There is no expiry sweep
command; promotion eligibility and admin display both derive from the date.

> **info**
> The package registers no listener for `PromotionDeactivated`. It fires only when something
> calls the action — in practice the Filament Deactivate button. A promotion that simply passes
> its `ends_at` is never transitioned, which is why `currentlyActive()` and
> `is_currently_active` exist as the read-side answer.

To deactivate in bulk, iterate inside an explicit owner scope:

```php
use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Models\Promotion;

Promotion::query()
    ->currentlyActive()
    ->whereNotNull('ends_at')
    ->where('ends_at', '<=', now())
    ->get()
    ->each(fn (Promotion $promotion) => app(DeactivatePromotion::class)->handle($promotion));
```
