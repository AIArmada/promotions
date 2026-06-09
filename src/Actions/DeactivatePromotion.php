<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\Promotions\Events\PromotionDeactivated;
use AIArmada\Promotions\Models\Promotion;

final class DeactivatePromotion
{
    public function handle(Promotion $promotion): Promotion
    {
        $promotion->update(['is_active' => false]);

        $fresh = $promotion->fresh();

        PromotionDeactivated::dispatch($fresh);

        return $fresh;
    }
}
