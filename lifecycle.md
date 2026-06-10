# Promotions Lifecycle Audit & Refactoring Plan

## 1. Executive Summary

The `promotions` table uses `is_active` boolean + `starts_at`/`ends_at` scheduling windows, which is the **correct lifecycle pattern for a configuration entity**. Per lifecycle principles, promotions with time windows should keep `is_active` as an admin override toggle without a `status` column.

No lifecycle remediation is required. The existing boolean flags + scheduling windows provide a clean, auditable pattern. Optionally, a single `deactivated_at timestampTz` can be added for transition auditing.

---

## 2. Full Inventory by Table

### 2.1 `promotions` table

| Column | Type | Nullable | Default | Lifecycle Role | Assessment |
|---|---|---|---|---|---|
| `id` | uuid | no | — | PK | OK |
| `owner_type` | morphs | yes | null | tenancy | OK |
| `owner_id` | morphs | yes | null | tenancy | OK |
| `name` | string | no | — | display | OK |
| `code` | string | yes | null | code-based vs automatic | OK |
| `description` | text | yes | null | display | OK |
| `type` | string | no | `percentage` | discount type | OK |
| `discount_value` | bigint | no | — | discount amount | OK |
| `priority` | integer | no | 0 | stacking order | OK |
| `is_stackable` | boolean | no | false | behavioral config flag | **OK** — keep boolean |
| `is_active` | boolean | no | true | admin on/off toggle | **OK** — correct for config entity |
| `usage_limit` | int | yes | null | exhaustion ceiling | OK |
| `usage_count` | int | no | 0 | exhaustion counter | OK |
| `per_customer_limit` | int | yes | null | per-customer ceiling | OK |
| `min_purchase_amount` | bigint | yes | null | targeting | OK |
| `min_quantity` | int | yes | null | targeting | OK |
| `conditions` | jsonb | yes | null | targeting | OK |
| `starts_at` | timestamptz | yes | null | scheduled activation window | OK |
| `ends_at` | timestamptz | yes | null | scheduled expiration window | OK |
| `created_at` | timestamptz | no | — | creation audit | OK |
| `updated_at` | timestamptz | no | — | mutation audit | OK |

### 2.2 `promotionables` table

| Column | Type | Lifecycle Role |
|---|---|---|
| `promotion_id` | foreign uuid | FK to promotion |
| `promotionable_id` | uuid morphs | polymorphic target |
| `promotionable_type` | uuid morphs | polymorphic target |

No lifecycle columns. No changes needed.

### 2.3 Indexes

| Index | Columns | Assessment |
|---|---|---|
| `promotions_is_active_priority_index` | `is_active, priority` | OK — correct for boolean-based queries |
| `promotions_starts_at_ends_at_index` | `starts_at, ends_at` | OK — correct for scheduling window queries |

---

## 3. Problems Summary

| # | Severity | Problem |
|---|---|---|
| P1 | Low | No `deactivated_at` timestampTz — optional audit trail for the `is_active` toggle |
| P2 | Low | No `visibility` column — future display/publication scoping should use a `visibility` string enum (not `is_public` boolean) when introduced |

---

## 4. Recommended Structure

### 4.1 Current schema is correct

The `is_active` + `starts_at`/`ends_at` pattern is the appropriate lifecycle representation for a promotions configuration entity. `is_active` serves as the admin override toggle; scheduling windows gate time-based activation.

### 4.2 Optional audit improvement

```
deactivated_at   timestampTz NULL   -- OPTIONAL: set when is_active transitions to false
```

No `status` column needed. Keep `is_active` boolean.

### 4.3 Future visibility scoping

When display/publication scoping is introduced, use a `visibility` string enum:

```
visibility       string NOT NULL DEFAULT 'public'   -- public | hidden | scheduled
```

Do NOT use `is_public` boolean — following the `is_public → visibility` principle for content-adjacent entities.

---

## 5. Refactoring Plan — Optional Improvements

These are **optional** and not required.

### Optional A: Add `deactivated_at` for audit trail

- [x] **A1.** Add `deactivated_at` timestampTz nullable column
- [x] **A2.** Update `Promotion` model: add `deactivated_at` to casts
- [x] **A3.** Update `DeactivatePromotion` action to set `deactivated_at = now()`
- [x] **A4.** Update `getActivitylogOptions()` and `getAuditInclude()` with `deactivated_at`

### Optional B: Add `visibility` for display scoping (future)

- [x] **B1.** Add `visibility` string column, default `'public'`
- [x] **B2.** Create `PromotionVisibility` enum (`Public`, `Hidden`, `Scheduled`)
- [x] **B3.** Add `published_at` timestampTz for scheduled visibility transitions

---

## 6. Migration Strategy

No required migrations. The existing schema is correct.

Optional migration for `deactivated_at`:
```php
Schema::table($table, function (Blueprint $table): void {
    $table->timestampTz('deactivated_at')->nullable()->after('is_active');
});
```

No column removals needed. No indexes to rebuild.

---

## 7. Verification Commands

```bash
# Run PHPStan on the promotions package only
./vendor/bin/phpstan analyse packages/promotions/src --level=6

# Run Pint formatting on changed files
./vendor/bin/pint packages/promotions/src --test

# Run all promotions tests
./vendor/bin/pest --parallel packages/promotions/tests

# Verify is_active + starts_at/ends_at pattern is intact
rg -n "is_active|starts_at|ends_at" packages/promotions/src/Models
```
