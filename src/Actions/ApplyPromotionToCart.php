<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\Promotions\Contracts\PromotionStrategyInterface;
use AIArmada\Promotions\Events\PromotionApplied;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Container\Attributes\Tag;
use RuntimeException;

final class ApplyPromotionToCart
{
    public function __construct(
        #[Tag('promotions.strategy')]
        private readonly iterable $strategies,
    ) {}

    public function handle(Promotion $promotion, int $subtotalInCents): int
    {
        $discount = $this->resolveStrategy($promotion)->calculateDiscount($promotion, $subtotalInCents);

        PromotionApplied::dispatch($promotion, $subtotalInCents, $discount);

        return $discount;
    }

    private function resolveStrategy(Promotion $promotion): PromotionStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($promotion->type)) {
                return $strategy;
            }
        }

        throw new RuntimeException("No strategy found for promotion type: {$promotion->type->value}");
    }
}
