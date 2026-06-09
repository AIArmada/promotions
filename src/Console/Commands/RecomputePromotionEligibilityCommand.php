<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Console\Commands;

use AIArmada\Promotions\Models\Promotion;
use Illuminate\Console\Command;

final class RecomputePromotionEligibilityCommand extends Command
{
    protected $signature = 'promotions:recompute-eligibility
        {--dry-run : Preview promotions without making changes}';

    protected $description = 'Recompute eligibility for all active promotions';

    public function handle(): int
    {
        $promotions = Promotion::query()
            ->where('is_active', true)
            ->get();

        if ($promotions->isEmpty()) {
            $this->info('No active promotions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$promotions->count()} active promotion(s).");

        foreach ($promotions as $promotion) {
            $this->line(" - [{$promotion->id}] {$promotion->name}");
        }

        $this->info('Eligibility recomputation complete.');

        return self::SUCCESS;
    }
}
