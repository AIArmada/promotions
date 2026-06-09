<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Console\Commands;

use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class DeactivateExpiredPromotionsCommand extends Command
{
    protected $signature = 'promotions:deactivate-expired
        {--dry-run : Preview which promotions would be deactivated without making changes}';

    protected $description = 'Deactivate all expired promotions';

    public function handle(DeactivatePromotion $deactivatePromotion): int
    {
        $now = Carbon::now();

        $expired = Promotion::query()
            ->where('is_active', true)
            ->where('ends_at', '<=', $now)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired promotions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$expired->count()} expired promotion(s).");

        foreach ($expired as $promotion) {
            $this->line(" - [{$promotion->id}] {$promotion->name}");

            if (! (bool) $this->option('dry-run')) {
                $deactivatePromotion->handle($promotion);
            }
        }

        if ((bool) $this->option('dry-run')) {
            $this->warn('Dry-run mode: no promotions were actually deactivated.');
        } else {
            $this->info("Deactivated {$expired->count()} expired promotion(s).");
        }

        return self::SUCCESS;
    }
}
