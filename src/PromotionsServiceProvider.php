<?php

declare(strict_types=1);

namespace AIArmada\Promotions;

use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Promotions\Console\Commands\DeactivateExpiredPromotionsCommand;
use AIArmada\Promotions\Contracts\PromotionServiceInterface;
use AIArmada\Promotions\Listeners\MarkPromotionAsUsedOnOrderPlaced;
use AIArmada\Promotions\Services\PromotionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PromotionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/promotions.php', 'promotions');

        $this->app->singleton(PromotionServiceInterface::class, PromotionService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
            $this->registerCommands();
        }

        $this->registerEventListeners();
    }

    private function registerEventListeners(): void
    {
        if (class_exists(OrderPaid::class)) {
            Event::listen(OrderPaid::class, MarkPromotionAsUsedOnOrderPlaced::class);
        }
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/promotions.php' => config_path('promotions.php'),
        ], 'promotions-config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'promotions-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    private function registerCommands(): void
    {
        $this->commands([
            DeactivateExpiredPromotionsCommand::class,
        ]);
    }
}
