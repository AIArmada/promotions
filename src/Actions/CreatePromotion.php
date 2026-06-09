<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Actions;

use AIArmada\Promotions\Events\PromotionCreated;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Facades\DB;

final class CreatePromotion
{
    public function handle(array $data): Promotion
    {
        $promotion = DB::transaction(fn (): Promotion => Promotion::create($data));

        PromotionCreated::dispatch($promotion);

        return $promotion;
    }
}
