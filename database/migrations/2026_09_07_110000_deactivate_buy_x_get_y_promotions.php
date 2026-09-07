<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('promotions.database.tables.promotions', 'promotions');

        if (! Schema::hasTable($tableName)
            || ! Schema::hasColumn($tableName, 'type')
            || ! Schema::hasColumn($tableName, 'discount_value')
            || ! Schema::hasColumn($tableName, 'is_active')) {
            return;
        }

        $now = now();

        if (Schema::hasColumn($tableName, 'deactivated_at')) {
            DB::table($tableName)
                ->where('type', 'buy_x_get_y')
                ->whereNull('deactivated_at')
                ->update(['deactivated_at' => $now]);
        }

        $updates = [
            'type' => 'fixed',
            'discount_value' => 0,
            'is_active' => false,
        ];

        if (Schema::hasColumn($tableName, 'updated_at')) {
            $updates['updated_at'] = $now;
        }

        DB::table($tableName)
            ->where('type', 'buy_x_get_y')
            ->update($updates);
    }
};
