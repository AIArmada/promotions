<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Contracts;

use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Contract for promotion services.
 */
interface PromotionServiceInterface
{
    /**
     * Get all applicable automatic promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotions(TargetingContext $context): Collection;

    /**
     * Get applicable automatic promotions at a supplied instant.
     *
     * Existing pricing callers must continue using the wall-clock method.
     *
     * @return Collection<int, Promotion>
     */
    public function getApplicablePromotionsAsOf(TargetingContext $context, CarbonImmutable $asOf): Collection;

    /**
     * Get the best applicable promotion for the given context.
     */
    public function getBestPromotion(TargetingContext $context): ?Promotion;

    /**
     * Resolve an applicable code-based promotion for the given context.
     */
    public function findApplicableCodePromotion(string $code, TargetingContext $context): ?Promotion;

    /**
     * Get all stackable promotions for the given context.
     *
     * @return Collection<int, Promotion>
     */
    public function getStackablePromotions(TargetingContext $context): Collection;

    /**
     * Calculate total discount from applicable promotions.
     *
     * @return array{discount: int, applied: Collection<int, Promotion>}
     */
    public function calculateDiscounts(TargetingContext $context, int $subtotalInCents): array;
}
