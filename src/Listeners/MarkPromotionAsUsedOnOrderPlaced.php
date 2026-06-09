<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Listeners;

use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Promotions\Models\Promotion;

final class MarkPromotionAsUsedOnOrderPlaced
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        $promotionId = $order->getAttribute('promotion_id');

        if ($promotionId === null) {
            return;
        }

        $promotion = Promotion::query()->find($promotionId);

        if ($promotion === null) {
            return;
        }

        $promotion->increment('times_used');
    }
}
