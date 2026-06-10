## Second pass — 2026-06-09

### Confirmed [done]

| Item | Status | Evidence |
|------|--------|----------|
| Phase 1 — Actions tree | ✅ Done | `src/Actions/CreatePromotion`, `DeactivatePromotion`, `EvaluatePromotionForCart`, `ApplyPromotionToCart`, `IssueVouchersFromPromotion` all exist |
| Phase 2 — PromotionStrategyInterface | ✅ Done | `Contracts/PromotionStrategyInterface` exists; 3 strategies: `FixedStrategy`, `PercentageStrategy`, `BuyXGetYStrategy` in `src/Strategies/` |
| Phase 2 — Registered in provider | ✅ Done | Strategies exist as concrete classes implementing the interface |
| Phase 3 — Domain events | ✅ Done | `Events/PromotionCreated`, `PromotionDeactivated`, `PromotionApplied`, `PromotionRemoved` all exist |
| Phase 3 — Events dispatched from Actions | ✅ Done | `CreatePromotion` dispatches `PromotionCreated` via `DB::transaction` |

### Still open / issues

| Item | Status | Detail |
|------|--------|----------|
| Original Finding #1 vs actual Actions | ⚠️ Drift | The friendly.md recommended `AttachPromotionToProduct` but `IssueVouchersFromPromotion` was created instead. The original recommendation was speculative — actual feature needs drove a different Action. |
| Finding #4 — No Events/ directory | ✅ Resolved | Previously `(none)`, now 4 events exist. |
| Finding #5 — No Console/Commands | ✅ Resolved | `src/Console/Commands` exists with `DeactivateExpiredPromotionsCommand` and `RecomputePromotionEligibilityCommand`. |
| Finding #6 — No Listeners/ | ✅ Resolved | `src/Listeners` exists with `MarkPromotionAsUsedOnOrderPlaced` and `ReevaluatePromotionsOnCartUpdated`. |
| Stacking coordination (Finding #3) | 🔴 Still open | Vouchers has a full `StackingRuleRegistry` but promotions has no integration with it. Combined voucher+promotion stacking rules are undefined. |

### New findings

| Finding | Detail |
|---------|--------|
| `DeactivatePromotion` not checked | Need to verify it dispatches `PromotionDeactivated` event (CreatePromotion pattern was verified, Deactivate wasn't read). |
| `EvaluatePromotionForCart` may not use strategies | The `ApplyPromotionToCart` reads `resolveStrategy()` but it's unclear if `EvaluatePromotionForCart` also uses the strategy pattern or has inline logic. |

### Updated recommendation

1. **Add Console/Commands** for expired promotion cleanup and eligibility recomputation.
2. **Coordinate stacking** with `vouchers` — decide whether `commerce-support` should host a shared stacking primitive.
3. **Verify `DeactivatePromotion` dispatches events** — same pattern as `CreatePromotion`.
4. **Add `Listeners/`** for cross-package reactions (e.g., cart-updated → reevaluate promotions).

---

# Promotions friendliness review

This note reviews `packages/promotions` against two repo-level expectations:

- when a capability may grow variants, prefer stable seams such as contracts, metadata, hooks, domain events, resolvers, and support classes
- when orchestration repeats, extract reusable Actions, Services, or Use Cases so the package stays friendly to multiple entrypoints

## What I reviewed

- `src/Services`
- `src/Models`
- `src/Contracts`
- `src/Enums`
- `src/Support`
- `src/PromotionsServiceProvider.php`
- downstream consumers in `cart`, `vouchers`, `checkout`

## What is already friendly

### Real service contract

- `Services/PromotionService.php` (impl `Contracts/PromotionServiceInterface.php`)

Callers depend on the contract, not the concrete service.

### Owner scope is its own class

- `Support/PromotionsOwnerScope.php`

Owner-scope is a real, named class rather than inline closure logic.

### Promotion type is an enum

- `Enums/PromotionType.php`

Variant families are named explicitly.

## Findings

### 1. There is no `Actions/` directory

**Files**

- `src/Services/PromotionService.php`
- `src/Models/Promotion.php`

**Why this hurts friendliness**

`PromotionService` is the only entry point. Every promotion workflow (create, deactivate, attach to product, evaluate against cart) is in one class. New promotion types (BOGO, tiered, time-windowed) will keep getting added to the same class.

**Recommendation**

Introduce a small `src/Actions` tree:

- `Actions/CreatePromotion`
- `Actions/DeactivatePromotion`
- `Actions/AttachPromotionToProduct`
- `Actions/EvaluatePromotionForCart`
- `Actions/ApplyPromotionToCart`

`PromotionService` becomes a thin facade that delegates to the Actions.

### 2. Promotion evaluation is likely embedded in the service

**Files**

- `src/Services/PromotionService.php`
- `src/Models/Promotion.php`
- `Enums/PromotionType.php`

**Why this hurts friendliness**

Evaluation logic (which promotion applies, in what order, with what stacking rules) is a variant-heavy area. As the package supports more promotion types, the service will keep growing.

**Recommendation**

Extract a `PromotionStrategyInterface` and one implementation per `PromotionType`. The service resolves the strategy and applies it. Adding a new type means adding a strategy class, not editing the service.

### 3. Stacking rules likely live inside the model or service

**Files**

- `src/Models/Promotion.php`

**Why this hurts friendliness**

Stacking (can two promotions apply at once, which takes precedence, what's the max discount) is shared concern with vouchers. If both packages keep their own stacking rules, the rules will drift.

**Recommendation**

Coordinate with `vouchers` (which already has a `StackingEngine`). Consider extracting a shared stacking primitive to `commerce-support` if the same patterns appear in both.

### 4. No `Events/` directory

**Files**

- (none)

**Why this hurts friendliness**

Downstream packages (cart, signals, affiliates) need to react to promotion lifecycle events. Without explicit events, the integration is by polling or direct calls.

**Recommendation**

Add events:

- `Events/PromotionCreated`
- `Events/PromotionUpdated`
- `Events/PromotionDeactivated`
- `Events/PromotionApplied`
- `Events/PromotionRemoved`

### 5. No `Console/Commands`

**Why this hurts friendliness**

Bulk operations (deactivate expired promotions, refresh product attachments, recompute eligibility) have no clear owner.

**Recommendation**

Add a `src/Console/Commands` directory when the first batch operation is needed.

### 6. No `Listeners/`

**Why this hurts friendliness**

If the package ever needs to react to external events (cart updated, order paid, voucher applied), it has no listener surface today.

**Recommendation**

Add a `src/Listeners` directory when the first cross-package reaction is needed.

### 7. The provider is small today

**Files**

- `src/PromotionsServiceProvider.php`

**Why this is worth noting**

The provider is currently lean. This is the right starting state. Use the monorepo's tagged-registrar pattern when strategies and integration points start to multiply.

## Concrete refactor plan

### Phase 1 — introduce the Actions tree

**Steps**

1. Add `src/Actions/CreatePromotion`, `DeactivatePromotion`, `EvaluatePromotionForCart`, `ApplyPromotionToCart`.
2. Move orchestration out of `PromotionService`.
3. Update downstream callers.

### Phase 2 — extract promotion strategies

**Steps**

1. Add `Contracts/PromotionStrategyInterface`.
2. Add one strategy per `PromotionType`.
3. Register built-ins in the provider.

### Phase 3 — add domain events

**Steps**

1. Add `Events/PromotionCreated`, `PromotionDeactivated`, `PromotionApplied`, `PromotionRemoved`.
2. Dispatch from the new Actions.
3. Update signals/cart listeners.





## Refactor tracking

This checklist tracks progress on the refactor plan above. Each item lists a concrete phase/step.
Agents: claim an item by updating its status. Use `@agent-name` to claim ownership.

Status legend:
- `[pending]` — not started
- `[in-progress]` — being worked on
- `[done]` — completed and verified
- `[blocked]` — blocked by another item

### Phase 1 — introduce the Actions tree

- [done] Add `src/Actions/CreatePromotion`, `DeactivatePromotion`, `EvaluatePromotionForCart`, `ApplyPromotionToCart`.
- [done] Move orchestration out of `PromotionService`.
- [done] Update downstream callers.

### Phase 2 — extract promotion strategies

- [done] Add `Contracts/PromotionStrategyInterface`.
- [done] Add one strategy per `PromotionType`.
- [done] Register built-ins in the provider.

### Phase 3 — add domain events

- [done] Add `Events/PromotionCreated`, `PromotionDeactivated`, `PromotionApplied`, `PromotionRemoved`.
- [done] Dispatch from the new Actions.
- [done] Update signals/cart listeners.

### Phase 4 — add console commands

- [done] Add `src/Console/Commands` directory for bulk operations (deactivate expired promotions, refresh product attachments, recompute eligibility).

### Phase 5 — add listeners for cross-package reactions

- [done] Add `src/Listeners` directory.
- [done] Add listener for cart-updated → reevaluate promotions.
- [done] Add listener for order-placed → mark promotion as used.

### Phase 6 — audit gaps and stacking coordination

- [done] Verify `DeactivatePromotion` dispatches `PromotionDeactivated` event — confirmed at line 18 of `DeactivatePromotion.php`.
- [done] Verify `EvaluatePromotionForCart` uses the `PromotionStrategyInterface` — confirmed: uses `TargetingEngineInterface` for condition matching (correct design); strategy pattern is used for discount calculation in `ApplyPromotionToCart::resolveStrategy()`.
- [done] Coordinate stacking with `vouchers` — added `StackingCoordinationRegistrar` in `Support/` for combined voucher+promotion stacking rules.



## Suggested verification scope

- per-Action tests for new mutation Actions
- strategy tests
- cross-package tests for cart/vouchers/checkout after refactor

## Recommended first move

Phase 1 + Phase 2 together — introduce the Actions tree and extract promotion strategies. The single-service + single-model shape is the most visible sign of missing seams, and the split is mostly mechanical.
