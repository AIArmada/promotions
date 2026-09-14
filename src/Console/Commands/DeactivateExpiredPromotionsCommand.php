<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Console\Commands;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Promotions\Actions\DeactivatePromotion;
use AIArmada\Promotions\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class DeactivateExpiredPromotionsCommand extends Command
{
    protected $signature = 'promotions:deactivate-expired
        {--dry-run : Preview which promotions would be deactivated without making changes}';

    protected $description = 'Deactivate all expired promotions';

    public function handle(DeactivatePromotion $deactivatePromotion): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $runner = new OwnerBatchRunner(Promotion::class, [
            'enabled' => 'promotions.features.owner.enabled',
            'include_global' => 'promotions.features.owner.include_global',
        ]);

        $deactivated = 0;

        // Iterate owners explicitly so each deactivation runs inside the
        // owning scope (the model write guard rejects cross-owner updates),
        // and chunk so large promotion tables never load fully into memory.
        $runner->forEach(function () use ($dryRun, $deactivatePromotion, &$deactivated): void {
            Promotion::query()
                ->forOwner()
                ->where('is_active', true)
                ->where('ends_at', '<=', CarbonImmutable::now())
                ->chunkById(100, function ($expired) use ($dryRun, $deactivatePromotion, &$deactivated): void {
                    foreach ($expired as $promotion) {
                        $this->line(" - [{$promotion->id}] {$promotion->name}");

                        if (! $dryRun) {
                            $deactivatePromotion->handle($promotion);
                        }

                        $deactivated++;
                    }
                });
        });

        if ($deactivated === 0) {
            $this->info('No expired promotions found.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("Dry-run mode: {$deactivated} expired promotion(s) found, none deactivated.");
        } else {
            $this->info("Deactivated {$deactivated} expired promotion(s).");
        }

        return self::SUCCESS;
    }
}
