<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Listeners;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
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

        $owner = OwnerTupleParser::fromTypeAndId($event->owner_type, $event->owner_id)->toOwnerModel();

        $promotion = Promotion::query()
            ->forOwner($owner, false)
            ->find($promotionId);

        if ($promotion === null) {
            return;
        }

        OwnerContext::withOwner($owner, static fn (): Promotion => $promotion->incrementUsage());
    }
}
