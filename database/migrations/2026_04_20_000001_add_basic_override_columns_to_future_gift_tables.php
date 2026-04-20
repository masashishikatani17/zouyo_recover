<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('future_gift_headers', function (Blueprint $table) {
            $table->boolean('calendar_basic_override_enabled')
                ->default(false)
                ->comment('暦年課税の基礎控除額を手入力するか');

            $table->boolean('settlement_basic_override_enabled')
                ->default(false)
                ->comment('精算課税の基礎控除額を手入力するか');
        });

        Schema::table('future_gift_plan_entries', function (Blueprint $table) {
            $table->unsignedInteger('calendar_basic_override_thousand')
                ->nullable()
                ->comment('暦年課税の基礎控除額手入力値（千円）');

            $table->unsignedInteger('settlement_basic_override_thousand')
                ->nullable()
                ->comment('精算課税の基礎控除額手入力値（千円）');
        });
    }

    public function down(): void
    {
        Schema::table('future_gift_plan_entries', function (Blueprint $table) {
            $table->dropColumn('calendar_basic_override_thousand');
            $table->dropColumn('settlement_basic_override_thousand');
        });

        Schema::table('future_gift_headers', function (Blueprint $table) {
            $table->dropColumn('calendar_basic_override_enabled');
            $table->dropColumn('settlement_basic_override_enabled');
        });
    }
};