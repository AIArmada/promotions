<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Listeners;

use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MarkPromotionAsUsedOnOrderPlaced
{
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $sessionId = $this->resolveSessionId($order);

        if ($sessionId === null) {
            $this->skip('order has no checkout session id', ['order_id' => $order->getKey()]);

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
            $this->skip('checkout session was not found', ['order_id' => $order->getKey(), 'session_id' => $sessionId]);

            return;
        }

        if ((string) $session->getAttribute('owner_type') !== (string) $event->owner_type
            || (string) $session->getAttribute('owner_id') !== (string) $event->owner_id) {
            $this->skip('checkout session owner tuple does not match the paid order', [
                'order_id' => $order->getKey(),
                'session_id' => $sessionId,
            ]);

            return;
        }

        $allocations = $session->discount_data['allocations'] ?? [];

        if (! is_array($allocations) || $allocations === []) {
            $this->skip('checkout session contains no discount allocations', [
                'order_id' => $order->getKey(),
                'session_id' => $sessionId,
            ]);

            return;
        }

        try {
            $owner = OwnerTupleParser::fromTypeAndId($event->owner_type, $event->owner_id)->toOwnerModel();
        } catch (Throwable $exception) {
            $this->skip('paid order owner tuple is malformed', [
                'order_id' => $order->getKey(),
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($allocations as $allocation) {
            if (($allocation['provider_key'] ?? '') !== 'promotions') {
                Log::debug('Promotion usage skipped: allocation belongs to another provider.', [
                    'order_id' => $order->getKey(),
                    'provider_key' => $allocation['provider_key'] ?? null,
                ]);

                continue;
            }

            $promotionId = $allocation['meta']['promotion_id'] ?? null;

            if ($promotionId === null) {
                $this->skip('promotion allocation has no promotion id', ['order_id' => $order->getKey()]);

                continue;
            }

            $promotion = Promotion::query()
                ->forOwner($owner, false)
                ->find($promotionId);

            if ($promotion === null) {
                $this->skip('promotion allocation could not be resolved in the order owner scope', [
                    'order_id' => $order->getKey(),
                    'promotion_id' => $promotionId,
                ]);

                continue;
            }

            $incremented = OwnerContext::withOwner($owner, static fn (): bool => $promotion->tryIncrementUsage());

            if (! $incremented) {
                $this->skip('promotion usage limit was reached during atomic increment', [
                    'order_id' => $order->getKey(),
                    'promotion_id' => $promotionId,
                ]);
            }
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function skip(string $reason, array $context): void
    {
        Log::warning('Promotion usage allocation skipped.', ['reason' => $reason, ...$context]);
    }
}
