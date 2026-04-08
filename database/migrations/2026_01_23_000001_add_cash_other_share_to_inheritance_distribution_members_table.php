+<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inheritance_distribution_members', function (Blueprint $table) {
            if (!Schema::hasColumn('inheritance_distribution_members', 'cash_share_value_thousand')) {
                $table->integer('cash_share_value_thousand')->nullable()->after('taxable_manu_value_thousand');
            }
            if (!Schema::hasColumn('inheritance_distribution_members', 'other_asset_share_value_thousand')) {
                $table->integer('other_asset_share_value_thousand')->nullable()->after('cash_share_value_thousand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inheritance_distribution_members', function (Blueprint $table) {
            if (Schema::hasColumn('inheritance_distribution_members', 'other_asset_share_value_thousand')) {
                $table->dropColumn('other_asset_share_value_thousand');
            }
            if (Schema::hasColumn('inheritance_distribution_members', 'cash_share_value_thousand')) {
                $table->dropColumn('cash_share_value_thousand');
            }
        });
    }
};
