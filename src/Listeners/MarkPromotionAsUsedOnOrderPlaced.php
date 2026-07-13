<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Listeners;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Promotions\Models\Promotion;

final class MarkPromotionAsUsedOnOrderPlaced
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $sessionId = $this->resolveSessionId($order);

        if ($sessionId === null) {
            return;
        }

        // ponytail: cross-tenant session lookup — session ID is resolved from
        // the order's own owner-scoped metadata, so the lookup target is already
        // bounded to the same owner as the order. Strip owner scope for direct
        // session lookup since CheckoutSession may be stored as a global record.
        $session = CheckoutSession::query()
            ->withoutGlobalScope(OwnerScope::class)
            ->find($sessionId);

        if ($session === null) {
            return;
        }

        $allocations = $session->discount_data['allocations'] ?? [];

        if (! is_array($allocations) || $allocations === []) {
            return;
        }

        $owner = OwnerTupleParser::fromTypeAndId($event->owner_type, $event->owner_id)->toOwnerModel();

        foreach ($allocations as $allocation) {
            if (($allocation['provider_key'] ?? '') !== 'promotions') {
                continue;
            }

            $promotionId = $allocation['meta']['promotion_id'] ?? null;

            if ($promotionId === null) {
                continue;
            }

            $promotion = Promotion::query()
                ->forOwner($owner, false)
                ->find($promotionId);

            if ($promotion === null) {
                continue;
            }

            OwnerContext::withOwner($owner, static fn (): Promotion => $promotion->incrementUsage());
        }
    }

    private function resolveSessionId(mixed $order): ?string
    {
        $metadata = is_callable([$order, 'getAttribute'])
            ? $order->getAttribute('metadata')
            : null;

        if (! is_array($metadata)) {
            return null;
        }

        return $metadata['checkout_session_id'] ?? null;
    }
}
