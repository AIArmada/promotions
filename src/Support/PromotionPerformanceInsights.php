<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Support;

use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Models\Promotion;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\VoucherStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class PromotionPerformanceInsights
{
    /**
     * @var array{
     *     total_orders: int,
     *     influenced_orders: int,
     *     code_influenced_orders: int,
     *     automatic_influenced_orders: int,
     *     influenced_revenue_minor: int,
     *     attributed_discount_minor: int,
     *     reporting_currency: string|null,
     *     currency_count: int,
     *     influenced_order_rate: float,
     * }|null
     */
    private ?array $orderOverviewCache = null;

    /**
     * @var Collection<int, array{label: string, order_count: int, influenced_revenue_minor: int, attributed_discount_minor: int}>|null
     */
    private ?Collection $topPromotionsByOrdersCache = null;

    /**
     * @return array{
     *     total_promotions: int,
     *     active_promotions: int,
     *     code_promotions: int,
     *     automatic_promotions: int,
     *     total_redemptions: int,
     *     code_redemptions: int,
     *     automatic_redemptions: int,
     *     active_redemptions: int,
     *     issued_vouchers: int,
     *     redeemed_promotion_vouchers: int,
     *     active_promotion_vouchers: int,
     *     total_orders: int,
     *     influenced_orders: int,
     *     code_influenced_orders: int,
     *     automatic_influenced_orders: int,
     *     influenced_revenue_minor: int,
     *     attributed_discount_minor: int,
     *     reporting_currency: string|null,
     *     currency_count: int,
     *     influenced_order_rate: float,
     * }
     */
    public function overview(): array
    {
        $baseQuery = $this->promotions();

        return array_merge([
            'total_promotions' => (clone $baseQuery)->count(),
            'active_promotions' => (clone $baseQuery)->where('is_active', true)->count(),
            'code_promotions' => (clone $baseQuery)->whereNotNull('code')->count(),
            'automatic_promotions' => (clone $baseQuery)->whereNull('code')->count(),
            'total_redemptions' => $this->sumUsageCount(clone $baseQuery),
            'code_redemptions' => $this->sumUsageCount((clone $baseQuery)->whereNotNull('code')),
            'automatic_redemptions' => $this->sumUsageCount((clone $baseQuery)->whereNull('code')),
            'active_redemptions' => $this->sumUsageCount((clone $baseQuery)->where('is_active', true)),
        ], $this->issuedVoucherOverview(clone $baseQuery), $this->orderOverview());
    }

    /**
     * @return Collection<int, array{label: string, usage_count: int}>
     */
    public function topPromotionsByUsage(int $limit = 5): Collection
    {
        return $this->promotions()
            ->where('usage_count', '>', 0)
            ->orderByDesc('usage_count')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['name', 'code', 'usage_count'])
            ->map(function (Promotion $promotion): array {
                return [
                    'label' => $this->labelFor($promotion),
                    'usage_count' => (int) $promotion->usage_count,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, order_count: int, influenced_revenue_minor: int, attributed_discount_minor: int}>
     */
    public function topPromotionsByOrders(int $limit = 5): Collection
    {
        $this->loadOrderPerformance();

        return ($this->topPromotionsByOrdersCache ?? collect())
            ->take($limit)
            ->values();
    }

    /**
     * @return Builder<Promotion>
     */
    private function promotions(): Builder
    {
        /** @var Builder<Promotion> $query */
        $query = Promotion::query();

        /** @var Builder<Promotion> $scoped */
        $scoped = $query;

        return $scoped;
    }

    /**
     * @param  Builder<Promotion>  $query
     */
    private function sumUsageCount(Builder $query): int
    {
        return (int) $query->sum('usage_count');
    }

    /**
     * @param  Builder<Promotion>  $query
     * @return array{issued_vouchers: int, redeemed_promotion_vouchers: int, active_promotion_vouchers: int}
     */
    private function issuedVoucherOverview(Builder $query): array
    {
        if (! Promotion::supportsIssuedVoucherTracking()) {
            return $this->emptyIssuedVoucherOverview();
        }

        $voucherModelClass = $this->issuedVoucherModelClass();

        if ($voucherModelClass === null) {
            return $this->emptyIssuedVoucherOverview();
        }

        $promotionIds = (clone $query)->pluck('id')->all();

        if ($promotionIds === []) {
            return $this->emptyIssuedVoucherOverview();
        }

        /** @var Model $voucherModel */
        $voucherModel = new $voucherModelClass;

        /** @var Builder<Model> $voucherQuery */
        $voucherQuery = $voucherModel->newQuery()->whereIn('promotion_id', $promotionIds);

        return [
            'issued_vouchers' => (int) (clone $voucherQuery)->count(),
            'redeemed_promotion_vouchers' => (int) (clone $voucherQuery)->whereHas('usages')->count(),
            'active_promotion_vouchers' => (int) (clone $voucherQuery)
                ->where('status', VoucherStatus::normalize(Active::class))
                ->count(),
        ];
    }

    /**
     * @return array{
     *     total_orders: int,
     *     influenced_orders: int,
     *     code_influenced_orders: int,
     *     automatic_influenced_orders: int,
     *     influenced_revenue_minor: int,
     *     attributed_discount_minor: int,
     *     reporting_currency: string|null,
     *     currency_count: int,
     *     influenced_order_rate: float,
     * }
     */
    private function orderOverview(): array
    {
        $this->loadOrderPerformance();

        return $this->orderOverviewCache ?? $this->emptyOrderOverview();
    }

    private function loadOrderPerformance(): void
    {
        if ($this->orderOverviewCache !== null && $this->topPromotionsByOrdersCache !== null) {
            return;
        }

        if (! $this->ordersAvailable()) {
            $this->orderOverviewCache = $this->emptyOrderOverview();
            $this->topPromotionsByOrdersCache = collect();

            return;
        }

        $totalOrders = Order::query()->count();
        $orders = Order::query()
            ->select(['id', 'grand_total', 'currency', 'metadata'])
            ->get();

        $influencedOrders = 0;
        $codeInfluencedOrders = 0;
        $automaticInfluencedOrders = 0;
        $influencedRevenueMinor = 0;
        $attributedDiscountMinor = 0;
        $currencies = [];
        $promotionPerformance = [];

        foreach ($orders as $order) {
            $appliedPromotions = data_get($order->metadata, 'discount_data.promotions');

            if (! is_array($appliedPromotions) || $appliedPromotions === []) {
                continue;
            }

            $influencedOrders++;
            $orderRevenueMinor = max(0, (int) $order->grand_total);
            $influencedRevenueMinor += $orderRevenueMinor;

            $currency = $this->normalizeString($order->currency);

            if ($currency !== null) {
                $currencies[mb_strtoupper($currency)] = true;
            }

            $hasCodePromotion = false;
            $hasAutomaticPromotion = false;
            $countedPromotionsForOrder = [];

            foreach ($appliedPromotions as $promotionPayload) {
                if (! is_array($promotionPayload)) {
                    continue;
                }

                $label = $this->labelFromPromotionPayload($promotionPayload);
                $promotionKey = $this->promotionMetricKey($promotionPayload, $label);
                $promotionCode = $this->normalizeString($promotionPayload['code'] ?? null);
                $discountMinor = max(0, (int) ($promotionPayload['discount'] ?? 0));

                $attributedDiscountMinor += $discountMinor;

                if ($promotionCode !== null) {
                    $hasCodePromotion = true;
                } else {
                    $hasAutomaticPromotion = true;
                }

                if (! array_key_exists($promotionKey, $promotionPerformance)) {
                    $promotionPerformance[$promotionKey] = [
                        'label' => $label,
                        'order_count' => 0,
                        'influenced_revenue_minor' => 0,
                        'attributed_discount_minor' => 0,
                    ];
                }

                $promotionPerformance[$promotionKey]['attributed_discount_minor'] += $discountMinor;

                if (! isset($countedPromotionsForOrder[$promotionKey])) {
                    $countedPromotionsForOrder[$promotionKey] = true;
                    $promotionPerformance[$promotionKey]['order_count']++;
                    $promotionPerformance[$promotionKey]['influenced_revenue_minor'] += $orderRevenueMinor;
                }
            }

            if ($hasCodePromotion) {
                $codeInfluencedOrders++;
            }

            if ($hasAutomaticPromotion) {
                $automaticInfluencedOrders++;
            }
        }

        $currencyCount = count($currencies);
        $reportingCurrency = $currencyCount === 1 ? array_key_first($currencies) : null;

        $this->orderOverviewCache = [
            'total_orders' => $totalOrders,
            'influenced_orders' => $influencedOrders,
            'code_influenced_orders' => $codeInfluencedOrders,
            'automatic_influenced_orders' => $automaticInfluencedOrders,
            'influenced_revenue_minor' => $influencedRevenueMinor,
            'attributed_discount_minor' => $attributedDiscountMinor,
            'reporting_currency' => $reportingCurrency,
            'currency_count' => $currencyCount,
            'influenced_order_rate' => $totalOrders > 0
                ? round(($influencedOrders / $totalOrders) * 100, 1)
                : 0.0,
        ];

        $this->topPromotionsByOrdersCache = collect(array_values($promotionPerformance))
            ->sort(function (array $left, array $right): int {
                return $right['order_count'] <=> $left['order_count']
                    ?: $right['attributed_discount_minor'] <=> $left['attributed_discount_minor']
                    ?: strcmp($left['label'], $right['label']);
            })
            ->map(static fn (array $promotion): array => [
                'label' => (string) $promotion['label'],
                'order_count' => (int) $promotion['order_count'],
                'influenced_revenue_minor' => (int) $promotion['influenced_revenue_minor'],
                'attributed_discount_minor' => (int) $promotion['attributed_discount_minor'],
            ])
            ->values();
    }

    /**
     * @return array{
     *     total_orders: int,
     *     influenced_orders: int,
     *     code_influenced_orders: int,
     *     automatic_influenced_orders: int,
     *     influenced_revenue_minor: int,
     *     attributed_discount_minor: int,
     *     reporting_currency: string|null,
     *     currency_count: int,
     *     influenced_order_rate: float,
     * }
     */
    private function emptyOrderOverview(): array
    {
        return [
            'total_orders' => 0,
            'influenced_orders' => 0,
            'code_influenced_orders' => 0,
            'automatic_influenced_orders' => 0,
            'influenced_revenue_minor' => 0,
            'attributed_discount_minor' => 0,
            'reporting_currency' => null,
            'currency_count' => 0,
            'influenced_order_rate' => 0.0,
        ];
    }

    /**
     * @return array{issued_vouchers: int, redeemed_promotion_vouchers: int, active_promotion_vouchers: int}
     */
    private function emptyIssuedVoucherOverview(): array
    {
        return [
            'issued_vouchers' => 0,
            'redeemed_promotion_vouchers' => 0,
            'active_promotion_vouchers' => 0,
        ];
    }

    private function ordersAvailable(): bool
    {
        if (! class_exists(Order::class)) {
            return false;
        }

        try {
            return Schema::hasTable((new Order)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    private function labelFor(Promotion $promotion): string
    {
        if ($promotion->code !== null && $promotion->code !== '') {
            return "{$promotion->name} ({$promotion->code})";
        }

        return $promotion->name;
    }

    /**
     * @param  array<string, mixed>  $promotionPayload
     */
    private function labelFromPromotionPayload(array $promotionPayload): string
    {
        $name = $this->normalizeString($promotionPayload['name'] ?? null) ?? 'Promotion';
        $code = $this->normalizeString($promotionPayload['code'] ?? null);

        return $code !== null ? "{$name} ({$code})" : $name;
    }

    /**
     * @param  array<string, mixed>  $promotionPayload
     */
    private function promotionMetricKey(array $promotionPayload, string $label): string
    {
        $promotionId = $promotionPayload['promotion_id'] ?? null;

        if (is_scalar($promotionId)) {
            $normalizedId = $this->normalizeString((string) $promotionId);

            if ($normalizedId !== null) {
                return 'promotion:' . $normalizedId;
            }
        }

        return 'label:' . mb_strtolower($label);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function issuedVoucherModelClass(): ?string
    {
        foreach ([
            '\\AIArmada\\Vouchers\\Models\\Voucher',
        ] as $voucherModelClass) {
            if (class_exists($voucherModelClass)) {
                /** @var class-string<Model> $voucherModelClass */

                return $voucherModelClass;
            }
        }

        return null;
    }
}
