<?php

declare(strict_types=1);

namespace AIArmada\Promotions\Enums;

use AIArmada\CommerceSupport\Support\MoneyFormatter;

/**
 * Promotion discount type.
 */
enum PromotionType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage Off',
            self::Fixed => 'Fixed Amount',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Percentage => 'heroicon-o-receipt-percent',
            self::Fixed => 'heroicon-o-currency-dollar',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percentage => 'success',
            self::Fixed => 'primary',
        };
    }

    public function formatValue(int $value): string
    {
        return match ($this) {
            self::Percentage => "{$value}%",
            self::Fixed => MoneyFormatter::formatMinor($value, (string) config('promotions.defaults.currency', 'USD')),
        };
    }
}
