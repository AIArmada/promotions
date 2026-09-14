<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Promotions\Events\PromotionDeactivated;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;

final class DeactivatePromotion
{
    public function handle(Promotion $promotion): Promotion
    {
        if (config('promotions.features.owner.enabled', false)) {
            /** @var Promotion $promotion */
            $promotion = OwnerWriteGuard::findOrFailForOwner(Promotion::class, $promotion->getKey());
        }

        $promotion->update([
            'is_active' => false,
            'deactivated_at' => CarbonImmutable::now(),
        ]);

        $resolved = $promotion->fresh() ?? $promotion;

        PromotionDeactivated::dispatch($resolved);

        return $resolved;
    }
}
