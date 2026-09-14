<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Support;

/**
 * Request-scoped memo for issued-voucher tracking detection.
 *
 * Registered as a container `scoped` binding so long-lived workers
 * re-detect after migrations instead of serving a stale process-wide flag.
 */
final class IssuedVoucherTrackingState
{
    public ?bool $supported = null;
}
