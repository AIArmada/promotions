<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Events\PromotionCreated;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class CreatePromotion
{
    public function handle(array $data): Promotion
    {
        $this->validate($data);
        $this->assertOwnerContext();

        $promotion = DB::transaction(fn (): Promotion => Promotion::create($data));

        PromotionCreated::dispatch($promotion);

        return $promotion;
    }

    private function assertOwnerContext(): void
    {
        if (! config('promotions.features.owner.enabled', false)) {
            return;
        }

        $owner = OwnerContext::resolve();

        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            throw new NoCurrentOwnerException(
                'Creating promotions requires an owner context or explicit global context. Use OwnerContext::withOwner(...) before calling this action.'
            );
        }
    }

    private function validate(array $data): void
    {
        $name = $data['name'] ?? null;

        if (! is_string($name) || mb_trim($name) === '') {
            throw new InvalidArgumentException('A promotion name is required.');
        }

        $type = $data['type'] ?? PromotionType::Percentage;

        if (! $type instanceof PromotionType) {
            $type = PromotionType::tryFrom((string) $type);
        }

        if (! $type instanceof PromotionType) {
            throw new InvalidArgumentException('Invalid promotion type.');
        }

        $discountValue = $data['discount_value'] ?? null;

        if (! is_numeric($discountValue)) {
            throw new InvalidArgumentException('A numeric discount value is required.');
        }

        $discountValue = (int) $discountValue;

        if ($type === PromotionType::Percentage && ($discountValue < 0 || $discountValue > 100)) {
            throw new InvalidArgumentException('Percentage discounts must be between 0 and 100.');
        }

        if ($type === PromotionType::Fixed && $discountValue < 0) {
            throw new InvalidArgumentException('Fixed discounts cannot be negative.');
        }

        foreach (['usage_limit', 'per_customer_limit', 'min_purchase_amount', 'min_quantity'] as $field) {
            $value = $data[$field] ?? null;

            if ($value !== null && (! is_numeric($value) || (int) $value < 0)) {
                throw new InvalidArgumentException("Promotion {$field} must be zero or a positive integer.");
            }
        }

        $startsAt = $this->parseDate($data['starts_at'] ?? null, 'starts_at');
        $endsAt = $this->parseDate($data['ends_at'] ?? null, 'ends_at');

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Promotion ends_at must be after starts_at.');
        }

        $this->assertCodeAvailable($data['code'] ?? null);
    }

    private function parseDate(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw new InvalidArgumentException("Promotion {$field} is not a valid date.");
        }
    }

    private function assertCodeAvailable(mixed $code): void
    {
        if ($code === null) {
            return;
        }

        $normalized = mb_strtoupper(mb_trim((string) $code));

        if ($normalized === '') {
            return;
        }

        $query = Promotion::query()->where('code', $normalized);

        if (config('promotions.features.owner.enabled', false)) {
            $owner = OwnerContext::resolve();

            if ($owner === null) {
                $query->whereNull('owner_type')->whereNull('owner_id');
            } else {
                $query->where('owner_type', $owner->getMorphClass())
                    ->where('owner_id', (string) $owner->getKey());
            }
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('The promotion code is already in use.');
        }
    }
}
