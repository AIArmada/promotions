<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Strategies;

use AIArmada\Promotions\Contracts\PromotionStrategyInterface;
use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;

final class PercentageStrategy implements PromotionStrategyInterface
{
    public function supports(PromotionType $type): bool
    {
        return $type === PromotionType::Percentage;
    }

    public function calculateDiscount(Promotion $promotion, int $priceInCents): int
    {
        return (int) round($priceInCents * ($promotion->discount_value / 100));
    }
}
