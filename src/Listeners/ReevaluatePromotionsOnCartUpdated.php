<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Listeners;

use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Cart\Events\ItemRemoved;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\CommerceSupport\Targeting\TargetingContext;
use AIArmada\Promotions\Actions\EvaluatePromotionForCart;
use AIArmada\Promotions\Models\Promotion;

final class ReevaluatePromotionsOnCartUpdated
{
    public function __construct(
        private readonly EvaluatePromotionForCart $evaluateAction,
    ) {}

    public function handle(ItemAdded | ItemRemoved $event): void
    {
        $owner = OwnerTupleParser::fromTypeAndId(
            $event->cart->storage()->getOwnerType(),
            $event->cart->storage()->getOwnerId(),
        )->toOwnerModel();

        $promotions = Promotion::query()
            ->forOwner($owner, false)
            ->where('is_active', true)
            ->get();

        $context = TargetingContext::fromCart($event->cart);

        foreach ($promotions as $promotion) {
            $this->evaluateAction->handle($promotion, $context);
        }
    }
}
