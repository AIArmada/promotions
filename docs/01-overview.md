---
title: Overview
---

# Promotions Package

`aiarmada/promotions` provides automatic and code-based discounts with owner-aware behavior and targeting-engine evaluation.

## Highlights

- Discount types: percentage, fixed, buy-x-get-y
- Automatic promotions (no code) and code promotions
- Usage limits and per-customer limits
- Scheduling (`starts_at`, `ends_at`)
- Stackable/non-stackable control
- Targeting conditions powered by commerce-support
- Owner-aware scoping and write guards
- Activity logging for core promotion fields

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
- Laravel 11+
- `aiarmada/commerce-support`
- `spatie/laravel-activitylog`
