<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('future_gift_recipients', function (Blueprint $table) {
            $table->boolean('calendar_tax_override_enabled')
                ->default(false)
                ->comment('暦年課税の贈与税額修正を受贈者ごとに有効化するか');
        });

        Schema::table('future_gift_plan_entries', function (Blueprint $table) {
            $table->unsignedInteger('calendar_tax_override_thousand')
                ->nullable()
                ->comment('暦年課税の贈与税額手入力値（千円）');
        });
    }

    public function down(): void
    {
        Schema::table('future_gift_plan_entries', function (Blueprint $table) {
            $table->dropColumn('calendar_tax_override_thousand');
        });

        Schema::table('future_gift_recipients', function (Blueprint $table) {
            $table->dropColumn('calendar_tax_override_enabled');
        });
    }
};