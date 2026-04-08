<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sozoku_rates', function (Blueprint $table) {
            $table->unsignedInteger('basic_deduction_base_yen')
                ->default(30000000)
                ->after('deduction_amount');

            $table->unsignedInteger('basic_deduction_per_heir_yen')
                ->default(6000000)
                ->after('basic_deduction_base_yen');
        });

        DB::table('sozoku_rates')->update([
            'basic_deduction_base_yen'     => 30000000,
            'basic_deduction_per_heir_yen' => 6000000,
        ]);
    }

    public function down(): void
    {
        Schema::table('sozoku_rates', function (Blueprint $table) {
            $table->dropColumn([
                'basic_deduction_base_yen',
                'basic_deduction_per_heir_yen',
            ]);
        });
    }
};