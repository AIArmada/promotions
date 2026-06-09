<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Targeting\Contracts\TargetingEngineInterface;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Models\Promotion;

final class EvaluatePromotionForCart
{
    public function __construct(
        private readonly TargetingEngineInterface $targetingEngine,
    ) {}

    public function handle(Promotion $promotion, TargetingContext $context): bool
    {
        if (! $promotion->isActive()) {
            return false;
        }

        $conditions = $promotion->conditions;

        if ($conditions === null || $conditions === []) {
            return true;
        }

        return $this->targetingEngine->evaluate($conditions, $context);
    }
}
