<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for finding and applying automatic promotions.
 */
final class PromotionService implements PromotionServiceInterface
{
    public function __construct(
        private readonly TargetingEngineInterface $targetingEngine
    ) {}

    /**
     * Get all applicable automatic promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotions(TargetingContext $context): Collection
    {
        return $this->getApplicablePromotionsAsOf($context, CarbonImmutable::now());
    }

    /**
     * Evaluate automatic promotions at a supplied instant.
     *
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotionsAsOf(TargetingContext $context, CarbonImmutable $asOf): Collection
    {
        return $this->applicablePromotions($context, $asOf);
    }

    /**
     * Get the best applicable promotion for the given context.
     */
    public function getBestPromotion(TargetingContext $context): ?Promotion
    {
        return $this->getApplicablePromotions($context)->first();
    }

    /**
     * Resolve an applicable code-based promotion for the given context.
     */
    public function findApplicableCodePromotion(string $code, TargetingContext $context): ?Promotion
    {
        $normalizedCode = mb_strtoupper(mb_trim($code));

        if ($normalizedCode === '') {
            return null;
        }

        /** @var Collection<int, Promotion> $promotions */
        $promotions = Promotion::query()
            ->active()
            ->withCode()
            ->forOwner()
            ->where('code', $normalizedCode)
            ->orderByDesc('priority')
            ->get();

        return $promotions->first(fn (Promotion $promotion): bool => $this->matchesContext($promotion, $context));
    }

    /**
     * Get all stackable promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getStackablePromotions(TargetingContext $context): Collection
    {
        return $this->getApplicablePromotions($context)
            ->filter(fn (Promotion $promotion): bool => $promotion->is_stackable);
    }

    /**
     * Calculate total discount from applicable promotions.
     *
     * @return array{discount: int, applied: Collection<int, Promotion>}
     */
    public function calculateDiscounts(TargetingContext $context, int $subtotalInCents): array
    {
        $applicablePromotions = $this->getApplicablePromotions($context);

        if ($applicablePromotions->isEmpty()) {
            return [
                'discount' => 0,
                'applied' => collect(),
            ];
        }

        $appliedPromotions = collect();
        $totalDiscount = 0;
        $remainingAmount = $subtotalInCents;
        $hasAppliedNonStackable = false;

        foreach ($applicablePromotions as $promotion) {
            if ($hasAppliedNonStackable && ! $promotion->is_stackable) {
                continue;
            }

            if (! $hasAppliedNonStackable || $promotion->is_stackable) {
                $discount = $promotion->calculateDiscount($remainingAmount);

                if ($discount > 0) {
                    $totalDiscount += $discount;
                    $remainingAmount -= $discount;
                    $appliedPromotions->push($promotion);

                    if (! $promotion->is_stackable) {
                        $hasAppliedNonStackable = true;
                    }
                }
            }
        }

        return [
            'discount' => min($totalDiscount, $subtotalInCents),
            'applied' => $appliedPromotions,
        ];
    }

    /**
     * Check if a promotion matches the given context.
     */
    private function matchesContext(Promotion $promotion, TargetingContext $context): bool
    {
        return $this->matchesContextAt($promotion, $context, null);
    }

    private function matchesContextAt(Promotion $promotion, TargetingContext $context, ?CarbonImmutable $asOf, ?Closure $usageCountsLoader = null): bool
    {
        if ($asOf === null ? ! $promotion->isActive() : ! $promotion->isActiveAt($asOf)) {
            return false;
        }

        if (! $this->withinCustomerLimit($promotion, $context, $asOf, $usageCountsLoader)) {
            return false;
        }

        if ($promotion->min_purchase_amount !== null && $context->getCartValue() < $promotion->min_purchase_amount) {
            return false;
        }

        if ($promotion->min_quantity !== null && $context->getCartQuantity() < $promotion->min_quantity) {
            return false;
        }

        $conditions = $promotion->conditions;

        if (empty($conditions)) {
            return true;
        }

        return $this->targetingEngine->evaluate($conditions, $context);
    }

    /**
     * @return Collection<int, Promotion>
     */
    private function applicablePromotions(TargetingContext $context, CarbonImmutable $asOf): Collection
    {
        $query = Promotion::query()
            ->activeAt($asOf)
            ->automatic()
            ->forOwner()
            ->when(
                $context->getCartValue() > 0,
                fn (Builder $builder): Builder => $builder->where(function (Builder $query) use ($context): void {
                    $query->whereNull('min_purchase_amount')
                        ->orWhere('min_purchase_amount', '<=', $context->getCartValue());
                }),
            )
            ->when(
                $context->getCartQuantity() > 0,
                fn (Builder $builder): Builder => $builder->where(function (Builder $query) use ($context): void {
                    $query->whereNull('min_quantity')
                        ->orWhere('min_quantity', '<=', $context->getCartQuantity());
                }),
            );

        $applicable = collect();

        // Scan the customer's order history at most once per evaluation and
        // share the resulting per-promotion usage map across every candidate,
        // instead of re-reading the full history inside the promotion loop.
        // The scan stays lazy so limit-free evaluations issue no order query.
        $usageCounts = null;
        $usageCountsLoaded = false;

        /** @var Closure(): ?array<string, int> $usageCountsLoader */
        $usageCountsLoader = function () use ($context, $asOf, &$usageCounts, &$usageCountsLoaded): ?array {
            if (! $usageCountsLoaded) {
                $usageCountsLoaded = true;
                $usageCounts = $this->customerPromotionUsageCounts($context, $asOf);
            }

            return $usageCounts;
        };

        $query->chunkById(100, function (Collection $promotions) use (&$applicable, $context, $asOf, $usageCountsLoader): void {
            foreach ($promotions as $promotion) {
                if ($this->matchesContextAt($promotion, $context, $asOf, $usageCountsLoader)) {
                    $applicable->push($promotion);
                }
            }
        });

        return $applicable->sortByDesc('priority')->values();
    }

    /**
     * @param  Closure(): ?array<string, int>  $usageCountsLoader
     */
    private function withinCustomerLimit(Promotion $promotion, TargetingContext $context, ?CarbonImmutable $asOf, ?Closure $usageCountsLoader = null): bool
    {
        if ($promotion->per_customer_limit === null) {
            return true;
        }

        $usageCounts = $usageCountsLoader !== null
            ? $usageCountsLoader()
            : $this->customerPromotionUsageCounts($context, $asOf);

        // Fail closed: when a limit is configured but usage cannot be
        // determined (guest, missing history, read error), the promotion
        // does not apply.
        if ($usageCounts === null) {
            return false;
        }

        return ($usageCounts[(string) $promotion->getKey()] ?? 0) < $promotion->per_customer_limit;
    }

    /**
     * Count prior uses of each promotion for the context customer.
     *
     * @return array<string, int>|null Null when usage cannot be determined.
     */
    private function customerPromotionUsageCounts(TargetingContext $context, ?CarbonImmutable $asOf): ?array
    {
        $customerId = $context->metadata['customer_id'] ?? $context->user?->getKey();

        if ($customerId === null || $customerId === '') {
            Log::debug('Promotion per-customer limit denied: no customer identifier.');

            return null;
        }

        /** @phpstan-var class-string<Order> $orderClass */
        $orderClass = 'AIArmada\\Orders\\Models\\Order';

        if (! class_exists($orderClass)) {
            Log::debug('Promotion per-customer limit denied: orders package is unavailable.');

            return null;
        }

        try {
            $orders = $orderClass::query()
                ->where('customer_id', $customerId)
                ->select(['id', 'metadata']);

            if (method_exists($orderClass, 'scopeForOwner')) {
                $orders->forOwner(OwnerContext::resolve(), false);
            }

            if ($asOf !== null) {
                $orders->where('created_at', '<=', $asOf);
            }

            $counts = [];

            $orders->chunkById(100, function (Collection $orders) use (&$counts): void {
                foreach ($orders as $order) {
                    foreach ($this->promotionIdsInMetadata($order->getAttribute('metadata')) as $promotionId) {
                        $counts[$promotionId] = ($counts[$promotionId] ?? 0) + 1;
                    }
                }
            });

            return $counts;
        } catch (Throwable $exception) {
            Log::debug('Promotion per-customer limit denied: order history could not be read.', [
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function promotionIdsInMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $allocations = $metadata['discount_data']['allocations']
            ?? $metadata['allocations']
            ?? [];

        if (! is_array($allocations)) {
            return [];
        }

        $promotionIds = [];

        foreach ($allocations as $allocation) {
            if (($allocation['provider_key'] ?? null) !== 'promotions') {
                continue;
            }

            $promotionId = (string) ($allocation['meta']['promotion_id'] ?? '');

            if ($promotionId !== '') {
                $promotionIds[] = $promotionId;
            }
        }

        return $promotionIds;
    }
}
