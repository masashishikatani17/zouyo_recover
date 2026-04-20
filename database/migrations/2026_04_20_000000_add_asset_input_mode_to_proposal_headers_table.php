<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proposal_headers')) {
            return;
        }

        Schema::table('proposal_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('proposal_headers', 'asset_input_mode')) {
                $table->string('asset_input_mode', 20)
                    ->default('split')
                    ->after('cash_total_thousand');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('proposal_headers')) {
            return;
        }

        Schema::table('proposal_headers', function (Blueprint $table) {
            if (Schema::hasColumn('proposal_headers', 'asset_input_mode')) {
                $table->dropColumn('asset_input_mode');
            }
        });
    }
};