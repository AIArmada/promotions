<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\States\Active;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

final class IssueVouchersFromPromotion
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $overrides
     * @return Collection<int, mixed>
     */
    public function handle(
        Promotion $promotion,
        int $count = 1,
        ?string $codePrefix = null,
        array $overrides = [],
    ): Collection {
        if ($count < 1) {
            throw new InvalidArgumentException('Voucher issue count must be at least 1.');
        }

        $voucherService = $this->resolveVoucherService();
        $owner = OwnerContext::fromTypeAndId($promotion->owner_type, $promotion->owner_id);

        if ($owner === null && ! OwnerContext::isExplicitGlobal()) {
            throw new NoCurrentOwnerException(
                'Issuing vouchers from a global promotion requires explicit global context. Use OwnerContext::withOwner(null, ...) before calling this action.'
            );
        }

        return OwnerContext::withOwner($owner, function () use ($count, $codePrefix, $overrides, $promotion, $voucherService): Collection {
            $issued = collect();

            for ($sequence = 1; $sequence <= $count; $sequence++) {
                $payload = array_replace_recursive(
                    $this->buildVoucherPayload($promotion, $sequence, $codePrefix),
                    $overrides,
                );

                $issued->push($voucherService->create($payload));
            }

            return $issued;
        });
    }

    /**
     * @return object{create: callable|mixed}
     */
    private function resolveVoucherService(): object
    {
        $contract = 'AIArmada\\Vouchers\\Contracts\\VoucherServiceInterface';

        if (! interface_exists($contract)) {
            throw new RuntimeException('Voucher issuance requires the vouchers package to be installed.');
        }

        /** @var object $voucherService */
        $voucherService = app($contract);

        return $voucherService;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVoucherPayload(Promotion $promotion, int $sequence, ?string $codePrefix): array
    {
        $currency = (string) config('promotions.defaults.currency', config('vouchers.default_currency', 'MYR'));
        $metadata = array_filter([
            'source_promotion_id' => $promotion->id,
            'source_promotion_name' => $promotion->name,
            'source_promotion_code' => $promotion->code,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'code' => $this->buildVoucherCode($promotion, $sequence, $codePrefix),
            'name' => $promotion->name . ($sequence > 1 ? " #{$sequence}" : ''),
            'description' => $promotion->description,
            'type' => $this->mapVoucherType($promotion->type),
            'value' => $this->mapVoucherValue($promotion),
            'value_config' => $this->buildValueConfig($promotion),
            'currency' => $currency,
            'min_cart_value' => $promotion->min_purchase_amount,
            'usage_limit' => 1,
            'starts_at' => $promotion->starts_at,
            'expires_at' => $promotion->ends_at,
            'status' => Active::class,
            'owner_type' => $promotion->owner_type,
            'owner_id' => $promotion->owner_id,
            'target_definition' => $this->buildTargetDefinition($promotion),
            'metadata' => $metadata !== [] ? $metadata : null,
            'promotion_id' => $promotion->id,
        ];
    }

    private function buildVoucherCode(Promotion $promotion, int $sequence, ?string $codePrefix): string
    {
        $basePrefix = $this->normalizeCodePrefix($codePrefix ?? $promotion->code ?? $promotion->name);
        $randomSuffix = Str::upper(Str::random(8));
        $sequencePrefix = $sequence > 1 ? "{$sequence}-" : '';

        if ($basePrefix === '') {
            return $sequencePrefix . $randomSuffix;
        }

        return "{$basePrefix}-{$sequencePrefix}{$randomSuffix}";
    }

    private function normalizeCodePrefix(string $value): string
    {
        return mb_trim(Str::upper(Str::slug($value, '-')), '-');
    }

    private function mapVoucherType(PromotionType $type): string
    {
        return match ($type) {
            PromotionType::Percentage => 'percentage',
            PromotionType::Fixed => 'fixed',
            PromotionType::BuyXGetY => 'buy_x_get_y',
        };
    }

    private function mapVoucherValue(Promotion $promotion): int
    {
        return match ($promotion->type) {
            PromotionType::Percentage => $promotion->discount_value * 100,
            PromotionType::Fixed => $promotion->discount_value,
            PromotionType::BuyXGetY => 0,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildValueConfig(Promotion $promotion): ?array
    {
        if ($promotion->type !== PromotionType::BuyXGetY) {
            return null;
        }

        $buyQuantity = max(1, (int) ($promotion->min_quantity ?? 1));
        $getQuantity = max(1, $promotion->discount_value);

        return [
            'buy' => [
                'quantity' => $buyQuantity,
                'product_matcher' => ['type' => 'all'],
            ],
            'get' => [
                'quantity' => $getQuantity,
                'discount' => '100%',
                'selection' => 'cheapest',
                'product_matcher' => ['type' => 'same_as_buy'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildTargetDefinition(Promotion $promotion): ?array
    {
        if (! is_array($promotion->conditions) || $promotion->conditions === []) {
            return null;
        }

        return ['targeting' => $promotion->conditions];
    }
}
