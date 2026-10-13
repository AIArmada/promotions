<?php

declare(strict_types=1);

namespace AIArmada\Promotions;

use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Listeners\MarkPromotionAsUsedOnFulfillment;
use AIArmada\Promotions\Services\PromotionService;
use AIArmada\Promotions\Support\IssuedVoucherTrackingState;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PromotionsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('promotions')
            ->hasConfigFile()
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(PromotionServiceInterface::class, PromotionService::class);
        $this->app->scoped(IssuedVoucherTrackingState::class);
    }

    public function packageBooted(): void
    {
        $this->registerEventListeners();
    }

    private function registerEventListeners(): void
    {
        if (class_exists(OrderFulfillmentRequired::class)) {
            Event::listen(OrderFulfillmentRequired::class, MarkPromotionAsUsedOnFulfillment::class);
        }
    }
}
