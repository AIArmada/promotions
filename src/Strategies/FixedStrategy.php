<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Strategies;

use AIArmada\Promotions\Contracts\PromotionStrategyInterface;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;

final class FixedStrategy implements PromotionStrategyInterface
{
    public function supports(PromotionType $type): bool
    {
        return $type === PromotionType::Fixed;
    }

    public function calculateDiscount(Promotion $promotion, int $priceInCents): int
    {
        return min($promotion->discount_value, $priceInCents);
    }
}
