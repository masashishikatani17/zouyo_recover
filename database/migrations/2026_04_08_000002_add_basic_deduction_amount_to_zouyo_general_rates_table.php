<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zouyo_general_rates')) {
            return;
        }

        if (! Schema::hasColumn('zouyo_general_rates', 'basic_deduction_amount')) {
            Schema::table('zouyo_general_rates', function (Blueprint $table): void {
                $table->unsignedBigInteger('basic_deduction_amount')
                    ->default(1100000)
                    ->after('deduction_amount')
                    ->comment('贈与税の基礎控除額（円）');
            });
        }

        DB::table('zouyo_general_rates')
            ->whereNull('basic_deduction_amount')
            ->update([
                'basic_deduction_amount' => 1100000,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('zouyo_general_rates')) {
            return;
        }

        if (Schema::hasColumn('zouyo_general_rates', 'basic_deduction_amount')) {
            Schema::table('zouyo_general_rates', function (Blueprint $table): void {
                $table->dropColumn('basic_deduction_amount');
            });
        }
    }
};