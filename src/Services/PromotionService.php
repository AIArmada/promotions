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

    private function matchesContextAt(Promotion $promotion, TargetingContext $context, ?CarbonImmutable $asOf): bool
    {
        if ($asOf === null ? ! $promotion->isActive() : ! $promotion->isActiveAt($asOf)) {
            return false;
        }

        if (! $this->withinCustomerLimit($promotion, $context, $asOf)) {
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

        $query->chunkById(100, function (Collection $promotions) use (&$applicable, $context, $asOf): void {
            foreach ($promotions as $promotion) {
                if ($this->matchesContextAt($promotion, $context, $asOf)) {
                    $applicable->push($promotion);
                }
            }
        });

        return $applicable->sortByDesc('priority')->values();
    }

    private function withinCustomerLimit(Promotion $promotion, TargetingContext $context, ?CarbonImmutable $asOf): bool
    {
        if ($promotion->per_customer_limit === null) {
            return true;
        }

        $customerId = $context->metadata['customer_id'] ?? $context->user?->getKey();

        if ($customerId === null || $customerId === '') {
            Log::debug('Promotion per-customer limit skipped: no customer identifier.', [
                'promotion_id' => $promotion->getKey(),
            ]);

            return true;
        }

        /** @phpstan-var class-string<Order> $orderClass */
        $orderClass = 'AIArmada\\Orders\\Models\\Order';

        if (! class_exists($orderClass)) {
            Log::debug('Promotion per-customer limit skipped: orders package is unavailable.', [
                'promotion_id' => $promotion->getKey(),
            ]);

            return true;
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

            $count = 0;
            $limit = $promotion->per_customer_limit;
            $orders->chunkById(100, function (Collection $orders) use (&$count, $limit, $promotion): bool {
                foreach ($orders as $order) {
                    if ($this->orderContainsPromotion($order->getAttribute('metadata'), (string) $promotion->getKey())) {
                        $count++;

                        if ($count >= $limit) {
                            return false;
                        }
                    }
                }

                return true;
            });

            return $count < $limit;
        } catch (Throwable $exception) {
            Log::debug('Promotion per-customer limit skipped: order history could not be read.', [
                'promotion_id' => $promotion->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return true;
        }
    }

    private function orderContainsPromotion(mixed $metadata, string $promotionId): bool
    {
        if (! is_array($metadata)) {
            return false;
        }

        $allocations = $metadata['discount_data']['allocations']
            ?? $metadata['allocations']
            ?? [];

        if (! is_array($allocations)) {
            return false;
        }

        foreach ($allocations as $allocation) {
            if (($allocation['provider_key'] ?? null) === 'promotions'
                && (string) ($allocation['meta']['promotion_id'] ?? '') === $promotionId) {
                return true;
            }
        }

        return false;
    }
}
