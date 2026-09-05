---
title: Promotions Context
package: promotions
status: current
surface: domain
family: growth-and-incentives
keywords:
  - promotion
  - discount
  - campaign
  - targeting
---

# Promotions Context

## Snapshot
- Composer: `aiarmada/promotions`
- Role: Automatic and code-based discount campaigns with targeting evaluation.
- Triggers: promotion, discount, campaign, targeting
- Search first: `src/Models, src/Actions, src/Services, config, docs`
- Related: `filament-promotions`, `pricing`, `products`
- Paired: `filament-promotions` (Filament admin adapter)

## Read next
1. `docs/01-overview.md`
2. `docs/03-configuration.md`
3. `docs/04-usage.md`
4. `docs/99-troubleshooting.md`
5. `../filament-promotions/CONTEXT.md` when the change crosses UI/domain
6. `docs/02-installation.md` when setup or publishing changes are involved

## Guardrails
- Owns models, actions, services, events, calculations, and persistence rules.
- If admin UI changes too, audit `filament-promotions`.
- Update `docs/*.md` in the same pass when public behavior or config changes.

## Decide fast
- Use when: Discount campaigns.
- Skip when: Price lists — see pricing; coupons/wallets — see vouchers.
- Owner/security: Owner-aware (disabled by default).

## Key surfaces
- Models: `Promotion`
- Actions/Services: `Actions/ApplyPromotionToCart`, `Actions/CreatePromotion`, `Actions/DeactivatePromotion`, `Actions/EvaluatePromotionForCart`, `Actions/IssueVouchersFromPromotion`, `Services/PromotionService`, `Support/PromotionPerformanceInsights`
- Config `promotions.php`: `database`, `json_column_type`, `tables`, `promotions`, `promotionables`, `defaults`, `currency`, `features`, `owner`, `enabled`

## Docs map
- Start: `01-overview` → `03-configuration` → `04-usage` → `99-troubleshooting`
- Deep dives: `05-promotion-service.md`, `06-targeting.md`, `07-multitenancy.md`
