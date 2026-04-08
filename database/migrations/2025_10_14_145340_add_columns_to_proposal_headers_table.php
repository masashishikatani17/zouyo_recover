<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proposal_headers')) return;
        Schema::table('proposal_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('proposal_headers', 'customer_name')) {
                $table->string('customer_name', 100)->nullable()->after('data_id');
            }
            if (!Schema::hasColumn('proposal_headers', 'title')) {
                $table->string('title', 200)->nullable()->after('customer_name');
            }
            if (!Schema::hasColumn('proposal_headers', 'doc_year')) {
                $table->unsignedSmallInteger('doc_year')->nullable()->after('title');
                $table->unsignedTinyInteger('doc_month')->nullable()->after('doc_year');
                $table->unsignedTinyInteger('doc_day')->nullable()->after('doc_month');
            }
            if (!Schema::hasColumn('proposal_headers', 'proposer_name')) {
                $table->string('proposer_name', 200)->nullable()->after('doc_day');
            }
            if (!Schema::hasColumn('proposal_headers', 'after_tax_yield_percent')) {
                $table->decimal('after_tax_yield_percent', 4, 1)->nullable()->after('proposer_name');
            }
            if (!Schema::hasColumn('proposal_headers', 'property_total_thousand')) {
                $table->unsignedBigInteger('property_total_thousand')->nullable()->after('after_tax_yield_percent');
                $table->unsignedBigInteger('cash_total_thousand')->nullable()->after('property_total_thousand');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('proposal_headers')) return;
        Schema::table('proposal_headers', function (Blueprint $table) {
            foreach ([
                'customer_name','title','doc_year','doc_month','doc_day',
                'proposer_name','after_tax_yield_percent',
                'property_total_thousand','cash_total_thousand'
            ] as $col) {
                if (Schema::hasColumn('proposal_headers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
