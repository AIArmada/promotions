<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Events;

use AIArmada\Promotions\Models\Promotion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PromotionDeactivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Promotion $promotion,
    ) {}
}
