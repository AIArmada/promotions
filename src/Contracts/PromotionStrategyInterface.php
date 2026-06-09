<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Contracts;

use AIArmada\Promotions\Enums\PromotionType;
use AIArmada\Promotions\Models\Promotion;

interface PromotionStrategyInterface
{
    public function supports(PromotionType $type): bool;

    public function calculateDiscount(Promotion $promotion, int $priceInCents): int;
}
