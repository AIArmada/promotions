---
title: Overview
---

# Promotions Package

## Purpose

The `aiarmada/promotions` package owns automatic and code-based discount campaigns, targeting-driven eligibility evaluation, and owner-aware promotion rules.

## What this package owns

- Promotion records, discount types, campaign schedules, usage limits, and stackability rules
- Targeting payload storage and evaluation for promotion eligibility
- Promotion-to-voucher issuance orchestration when the vouchers package is installed
- Promotion activity logging for core promotion fields
- Owner-aware promotion queries and write guards

## What this package does not own

- Product, cart, or order persistence
- Voucher validation, redemption, persistence, and wallet flows (`aiarmada/vouchers`)
- Filament admin surfaces (`aiarmada/filament-promotions`)
- Price-list management (`aiarmada/pricing`)

## Related packages

- [`aiarmada/filament-promotions`](../../filament-promotions/docs/01-overview.md) — Filament admin resources and widgets for promotion operations
- [`aiarmada/pricing`](../../pricing/docs/01-overview.md) — pricing resolution that may consume active promotions
- [`aiarmada/products`](../../products/docs/01-overview.md) — optional product/category targeting context
- [`aiarmada/vouchers`](../../vouchers/docs/01-overview.md) — voucher persistence, redemption, and reporting for promotion-issued vouchers
- [`aiarmada/commerce-support`](../../commerce-support/docs/01-overview.md) — owner scoping and targeting engine primitives

## Main models services or surfaces

- **Model** — `Promotion`
- **Actions** — `CreatePromotion`, `ApplyPromotionToCart`, `EvaluatePromotionForCart`, `DeactivatePromotion`, `IssueVouchersFromPromotion`
- **Events** — `PromotionCreated`, `PromotionApplied`, `PromotionRemoved`, `PromotionDeactivated`
- **Strategies** — `FixedStrategy`, `PercentageStrategy`, `BuyXGetYStrategy` (resolved via `PromotionStrategyInterface`)
- **Contracts** — `PromotionStrategyInterface`, `PromotionServiceInterface`
- **Console commands** — `DeactivateExpiredPromotionsCommand`, `RecomputePromotionEligibilityCommand`
- **Listeners** — `MarkPromotionAsUsedOnOrderPlaced`, `ReevaluatePromotionsOnCartUpdated`
- **Support** — `StackingCoordinationRegistrar`, `PromotionPerformanceInsights`
- **Core surfaces** — promotion targeting evaluation, usage-limit enforcement, code and automatic promotion flows
- **Docs deep dives** — promotion service and targeting internals live in the companion docs pages for this package

## Owner scoping and security notes

- Promotions are owner-aware and should follow the `commerce-support` owner-boundary rules
- Filtered admin options are not sufficient authorization; consuming packages still need to validate inbound identifiers and promotion applicability inside the current owner scope
- Shared or global promotions should remain explicit rather than relying on missing owner context

`aiarmada/promotions` provides automatic and code-based discounts with owner-aware behavior and targeting-engine evaluation.

## Highlights

- Discount types: percentage, fixed, buy-x-get-y
- Automatic promotions (no code) and code promotions
- Usage limits and per-customer limits
- Scheduling (`starts_at`, `ends_at`)
- Stackable/non-stackable control
- Targeting conditions powered by commerce-support
- Optional promotion-issued one-time vouchers for recovery or targeted distribution campaigns
- Owner-aware scoping and write guards
- Activity logging for core promotion fields
- Action-based API: `CreatePromotion`, `ApplyPromotionToCart`, `EvaluatePromotionForCart`, `DeactivatePromotion`
- Strategy pattern: `FixedStrategy`, `PercentageStrategy`, `BuyXGetYStrategy` via `PromotionStrategyInterface`
- Events for extensibility: `PromotionCreated`, `PromotionApplied`, `PromotionRemoved`, `PromotionDeactivated`
- Console commands: `DeactivateExpiredPromotionsCommand`, `RecomputePromotionEligibilityCommand`

## Core model fields

| Column | Type | Notes |
| --- | --- | --- |
| `id` | UUID | Primary key |
| `owner_type`, `owner_id` | morph | Optional owner tuple |
| `name` | string | Required |
| `code` | string nullable | Null means automatic promotion |
| `description` | text nullable | Optional |
| `type` | enum string | `percentage`, `fixed`, `buy_x_get_y` |
| `discount_value` | integer | Percent points or minor units |
| `priority` | integer | Higher runs first |
| `is_stackable` | boolean | Allow combination with others |
| `is_active` | boolean | Top-level activation toggle |
| `usage_limit` | integer nullable | Overall cap |
| `usage_count` | integer | Current usage counter |
| `per_customer_limit` | integer nullable | Per-customer cap |
| `min_purchase_amount` | integer nullable | Minor units |
| `min_quantity` | integer nullable | Cart quantity floor |
| `conditions` | JSON nullable | Targeting engine payload |
| `starts_at`, `ends_at` | timestamps nullable | Active window |

## Requirements

- PHP 8.4+
- Laravel 13+
- `aiarmada/commerce-support`
- `spatie/laravel-activitylog`

## Read next

- [Installation](02-installation.md)
- [Configuration](03-configuration.md)
- [Usage](04-usage.md)
- [Promotion service](05-promotion-service.md)
- [Targeting](06-targeting.md)
- [Multitenancy](07-multitenancy.md)
- [Filament Promotions overview](../../filament-promotions/docs/01-overview.md)
