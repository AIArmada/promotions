---
title: Promotion-Voucher Integration Proposal
---

# Promotion-Voucher Integration Proposal

## Status: Draft

## 1. Current State

### Two independent discount systems

| Aspect | Promotions (`aiarmada/promotions`) | Vouchers (`aiarmada/vouchers`) |
|---|---|---|
| Purpose | Campaign-level discounts (automatic or code-based) | Individual discount instruments with rich lifecycle |
| DB link to the other | None | None |
| Code reference to the other | None | Documentation comments only |
| Checkout integration | `PromotionsAdapter` — automatic + code lookup | `VouchersAdapter` — validation + reservation + redemption |
| Code collision guard | `promoCodeResolvesToVoucher()` prevents double-application | N/A |

### What works well

- Each package owns a clear domain boundary with minimal external coupling.
- The checkout layer applies both independently and sums their discounts — correct behavior.
- The promo-code guard prevents a single code from being treated as both a promotion and a voucher.

### What's missing

1. **No traceability** — a promotion campaign cannot link to vouchers it created.
2. **No bulk issuance** — no "generate 500 vouchers from this Summer Sale campaign" action.
3. **No reporting** — cannot answer "how many vouchers from campaign X were redeemed?"
4. **No unified code resolution** — the guard in `PromotionsAdapter` is a workaround; there is no single "resolve this code" entry point that returns a tagged result (promotion vs. voucher).
5. **No promotion-aware voucher defaults** — vouchers generated from a promotion should automatically inherit its type, value, currency, owner, targeting conditions, and validity window without manual re-entry.

---

## 2. Design Goals

- **Minimal blast radius** — no changes to existing promotion or voucher models/interfaces unless necessary.
- **Opt-in** — the integration lives in a lightweight bridge package or optional provider. Packages remain independently installable.
- **Backwards compatible** — existing promotions, vouchers, and checkout flows continue working unchanged.
- **Traceable** — vouchers carry a `promotion_id` back to their source campaign.
- **Bulk-capable** — a single action to issue N vouchers from a promotion.
- **Reportable** — promotion model gains a `vouchers()` relation for usage analytics.
- **Unified code resolution** — a single service that checks promotion codes and voucher codes in order, returning a typed result.

---

## 3. Proposed Design

### 3.1 Database: Add `promotion_id` to vouchers table

A single nullable foreign key on the `vouchers` table:

```php
// New migration in aiarmada/vouchers
$table->foreignUuid('promotion_id')->nullable()->index();
```

No constraint, no cascade — consistent with the monorepo's DB convention.

This is the **only** schema change. The `Promotion` model stays untouched.

### 3.2 Voucher model: add relationship

```php
// In Voucher model
use AIArmada\Promotions\Models\Promotion;

public function promotion(): BelongsTo
{
    return $this->belongsTo(Promotion::class, 'promotion_id');
}
```

Conditional via `class_exists()` — the vouchers package does not require promotions.

### 3.3 Promotion model: add relationship

```php
// In Promotion model
use AIArmada\Vouchers\Models\Voucher;

public function vouchers(): HasMany
{
    return $this->hasMany(Voucher::class, 'promotion_id');
}
```

Conditional via `class_exists()` — the promotions package does not require vouchers.

### 3.4 New action: `IssueVouchersFromPromotion`

Located in a new bridge package (e.g. `aiarmada/promotion-voucher-bridge`) or directly in `aiarmada/promotions` with `class_exists()` guards.

```php
final class IssueVouchersFromPromotion
{
    use AsAction;

    /**
     * @param  array<int, array<string, mixed>>|int  $recipients  Count or list of recipient overrides
     */
    public function handle(
        Promotion $promotion,
        array|int $recipients,
        ?Closure $beforeCreate = null,
    ): Collection {
        $count = is_int($recipients) ? $recipients : count($recipients);

        return DB::transaction(function () use ($promotion, $recipients, $count): Collection {

            $vouchers = collect();
            $baseData = $this->baseVoucherData($promotion);

            for ($i = 0; $i < $count; $i++) {
                $data = $baseData;

                // Per-recipient overrides when recipients is an array
                if (is_array($recipients) && isset($recipients[$i])) {
                    $data = array_merge($data, $recipients[$i]);
                }

                // Hook for caller customization (e.g., assign to specific user)
                if ($beforeCreate !== null) {
                    $data = $beforeCreate($data, $i) ?? $data;
                }

                $vouchers->push(CreateVoucher::run($data));
            }

            return $vouchers;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function baseVoucherData(Promotion $promotion): array
    {
        return [
            'promotion_id' => $promotion->id,
            'name' => $promotion->name,
            'type' => $this->mapPromotionType($promotion->type),
            'value' => $this->mapDiscountValue($promotion),
            'currency' => config('promotions.defaults.currency', 'USD'),
            'usage_limit' => $promotion->usage_limit,
            'usage_limit_per_user' => $promotion->per_customer_limit,
            'min_cart_value' => $promotion->min_purchase_amount,
            'starts_at' => $promotion->starts_at,
            'expires_at' => $promotion->ends_at,
            'owner_type' => $promotion->owner_type,
            'owner_id' => $promotion->owner_id,
            'metadata' => [
                'source_promotion_id' => $promotion->id,
                'source_promotion_name' => $promotion->name,
            ],
            'target_definition' => $this->mapConditions($promotion->conditions),
        ];
    }

    private function mapPromotionType(PromotionType $type): string
    {
        return match ($type) {
            PromotionType::Percentage => 'percentage',
            PromotionType::Fixed => 'fixed',
            PromotionType::BuyXGetY => 'buy_x_get_y',
        };
    }

    private function mapDiscountValue(Promotion $promotion): int
    {
        return match ($promotion->type) {
            // Percentage: promotions store as percent (20 = 20%),
            // vouchers store as basis points (2000 = 20%)
            PromotionType::Percentage => $promotion->discount_value * 100,
            // Fixed: both store as cents
            PromotionType::Fixed => $promotion->discount_value,
            PromotionType::BuyXGetY => 0,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapConditions(?array $conditions): ?array
    {
        if ($conditions === null || $conditions === []) {
            return null;
        }

        // Promotion conditions use TargetingEngine format.
        // Voucher target_definition uses a compatible structure.
        // For now, pass through — the bridge can normalize if formats diverge.
        return $conditions;
    }
}
```

### 3.5 Reporting: Promotion gets voucher stats

```php
// Accessor or computed attribute on Promotion
public function getVoucherStatsAttribute(): ?array
{
    if (! class_exists(Voucher::class)) {
        return null;
    }

    return [
        'total' => $this->vouchers()->count(),
        'active' => $this->vouchers()->where('status', Active::class)->count(),
        'redeemed' => $this->vouchers()->whereHas('usages')->count(),
        'expired' => $this->vouchers()->where('expires_at', '<', now())->count(),
        'depleted' => $this->vouchers()->where('status', Depleted::class)->count(),
    ];
}
```

### 3.6 Unified code resolution

Replace the guard in `PromotionsAdapter` with a shared resolver service. This goes in the checkout package (or the bridge package) and provides a single entry point for any consumer:

```php
final class PromoCodeResolver
{
    public function __construct(
        private readonly ?VoucherServiceInterface $voucherService = null,
    ) {}

    /**
     * @return array{type: 'promotion'|'voucher'|'none', payload: array|null}
     */
    public function resolve(string $code, CheckoutSession $session): array
    {
        // 1. Check voucher first (vouchers are more specific instruments)
        if ($this->voucherService !== null) {
            $validation = $this->voucherService->validate($code, [
                'customer_id' => $session->customer_id,
                'subtotal' => $session->subtotal,
                'currency' => $session->currency,
            ]);

            $isValid = $validation instanceof VoucherValidationResult
                ? $validation->isValid
                : ($validation['valid'] ?? false);

            if ($isValid) {
                $voucher = $this->voucherService->find($code);

                return [
                    'type' => 'voucher',
                    'payload' => $voucher?->toArray(),
                ];
            }
        }

        // 2. Fall back to promotion code
        if (interface_exists(PromotionServiceInterface::class)) {
            /** @var Collection<int, Promotion> $promotions */
            $promotions = Promotion::query()
                ->active()
                ->withCode()
                ->forOwner()
                ->whereRaw('LOWER(code) = LOWER(?)', [$code])
                ->orderByDesc('priority')
                ->get();

            $promotion = $promotions->first(fn (Promotion $p) => $p->isActive());

            if ($promotion !== null) {
                return [
                    'type' => 'promotion',
                    'payload' => [
                        'promotion_id' => $promotion->id,
                        'name' => $promotion->name,
                        'code' => $promotion->code,
                        'type' => $promotion->type->value,
                        'discount_value' => $promotion->discount_value,
                    ],
                ];
            }
        }

        return ['type' => 'none', 'payload' => null];
    }
}
```

This eliminates the duplicate validation call in `promoCodeResolvesToVoucher()` + `resolveCodePromotion()` and gives any surface (checkout, API, admin panel) a single resolver.

### 3.7 Service provider auto-wiring (bridge package)

A new package `aiarmada/promotion-voucher-bridge` (or inline in `aiarmada/promotions` if preferred):

```php
class PromotionVoucherBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the unified resolver
        $this->app->singleton(PromoCodeResolver::class);
    }

    public function boot(): void
    {
        // Auto-register when both packages are installed
        if (! interface_exists(VoucherServiceInterface::class)) {
            return;
        }
        if (! class_exists(Promotion::class)) {
            return;
        }

        // Publish bridge migration (promotion_id column)
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

---

## 4. Migration Path

### Phase 1: Schema + relationships (safe, backwards-compatible)
1. Add migration in `aiarmada/vouchers` for `promotion_id` column.
2. Add conditional `promotion()` relation on Voucher.
3. Add conditional `vouchers()` relation on Promotion.

### Phase 2: Issue action + unified resolver
4. Add `IssueVouchersFromPromotion` action.
5. Add `PromoCodeResolver` in checkout or bridge package.
6. Replace the guard in `PromotionsAdapter` with the resolver.
7. Add `voucherStats` helper on Promotion.

### Phase 3: Filament surfaces (optional)
8. Show voucher stats widget on Promotion edit page in `aiarmada/filament-promotions`.
9. Add "Issue Vouchers" action button on Promotion resource.
10. Show `promotion_id` / promotion name on Voucher detail in `aiarmada/filament-vouchers`.

---

## 5. Edge Cases & Risk Mitigation

| Concern | Mitigation |
|---|---|
| **Promotion deleted, vouchers orphaned** | Vouchers keep `promotion_id` but don't cascade. A deleted promotion leaves the FK as a soft reference. The `vouchers()` relation may return empty if the promotion is deleted — the voucher still works independently. |
| **Promotion type mismatch** (e.g., BOGO mapped to voucher) | Compound types (BuyXGetY) map to voucher's `value_config`. The `IssueVouchersFromPromotion` action handles type mapping explicitly. |
| **Owner scope mismatch** | Vouchers inherit `owner_type`/`owner_id` from the source promotion. If owner scoping is disabled on vouchers but enabled on promotions, the issue action skips owner assignment. |
| **Bulk issuance performance** | Uses a single DB transaction. For very large batches (>10k), consider chunked jobs. The action accepts a `beforeCreate` hook for custom throttling. |
| **Existing promotions with codes won't automatically get vouchers** | Backwards compatible — no behavioral change for existing records. The bridge is opt-in: you call `IssueVouchersFromPromotion` explicitly. |

---

## 6. What Stays Unchanged

- **Promotion model** — no new columns, no new required fields, no behavioral changes to existing methods.
- **Voucher model** — one new nullable column only. All existing scopes, states, validation, and stacking logic are untouched.
- **Checkout discount calculation** — promotions and vouchers are still applied as two independent passes. The only change is the unified `PromoCodeResolver` for code input.
- **Package independence** — both packages remain installable without the other. All cross-package references use `class_exists()` / `interface_exists()` guards.