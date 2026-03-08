---
title: Overview
---

# Promotions Package

The Promotions package provides a complete promotional discount system for commerce applications. It supports automatic promotions, promo codes, usage limits, scheduling, and integrates with the targeting engine from commerce-support.

## Features

- **Multiple Discount Types** — Percentage, fixed amount, and Buy X Get Y promotions
- **Automatic Promotions** — Apply discounts without codes
- **Promo Codes** — Optional code-based discounts
- **Usage Limits** — Control total usage and per-customer limits
- **Scheduling** — Time-limited campaigns with start/end dates
- **Stacking Control** — Define whether promotions can combine
- **Priority System** — Determine which promotions take precedence
- **Targeting Engine** — Apply conditions (customer segments, cart value, products)
- **Multi-tenancy** — Full owner scoping for SaaS applications
- **Activity Logging** — Automatic tracking of promotion changes

## Architecture

```
promotions/
├── Contracts/
│   └── PromotionServiceInterface.php   # Service contract
├── Enums/
│   └── PromotionType.php               # Discount type enum
├── Models/
│   └── Promotion.php                   # Main model
├── Services/
│   └── PromotionService.php            # Business logic
└── Support/
    └── PromotionsOwnerScope.php        # Owner scope helper
```

## Data Model

### Promotions Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `owner_type` | string | Tenant owner morph type |
| `owner_id` | UUID | Tenant owner ID |
| `name` | string | Promotion name |
| `description` | text | Optional description |
| `code` | string | Promo code (null for automatic) |
| `type` | enum | Percentage, Fixed, BuyXGetY |
| `discount_value` | integer | Discount amount (cents or %) |
| `conditions` | JSON | Targeting conditions |
| `min_order_value` | integer | Minimum order to qualify |
| `max_discount` | integer | Cap for percentage discounts |
| `usage_limit` | integer | Total usage limit |
| `usage_per_customer` | integer | Per-customer limit |
| `priority` | integer | Higher = applied first |
| `is_active` | boolean | Active status |
| `is_stackable` | boolean | Can combine with others |
| `starts_at` | datetime | Campaign start |
| `ends_at` | datetime | Campaign end |

### Promotionables Table (Pivot)

Links promotions to models (products, categories, customers) via polymorphic relationships.

## Requirements

- PHP 8.4+
- Laravel 12+
- aiarmada/commerce-support
- spatie/laravel-data
- spatie/laravel-activitylog
