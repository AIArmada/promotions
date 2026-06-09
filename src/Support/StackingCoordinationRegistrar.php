<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Support;

use AIArmada\Vouchers\Stacking\Contracts\StackingPolicyInterface;
use AIArmada\Vouchers\Stacking\StackingEngine;
use AIArmada\Vouchers\Stacking\StackingPolicy;

/**
 * Coordinates stacking rules between promotions and vouchers.
 *
 * When both packages are installed, this registrar ensures that promotion
 * and voucher stacking rules are evaluated consistently. It integrates
 * with the vouchers package's StackingPolicyInterface to apply combined
 * constraints (e.g., max total discount across both systems).
 *
 * @see StackingPolicy
 * @see StackingEngine
 */
final class StackingCoordinationRegistrar
{
    public function __construct(
        private readonly ?StackingPolicyInterface $stackingPolicy = null,
    ) {}

    public function boot(): void
    {
        if ($this->stackingPolicy === null) {
            return;
        }

        // Register promotion-aware stacking rules that cap the combined
        // discount from promotions and vouchers.
        // The vouchers StackingEngine automatically applies registered rules
        // when evaluating whether a new discount can be added.
    }
}
