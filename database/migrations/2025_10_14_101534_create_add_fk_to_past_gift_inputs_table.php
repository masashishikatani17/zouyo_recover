<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 受贈者（No2〜9）一覧（1:N）
         * まずは親なしでも通すため FK は付けない（data_id は INT UNSIGNED）
         */
        Schema::create('past_gift_recipients', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('data_id');
            $table->index('data_id');
            $table->unsignedTinyInteger('recipient_no');     // 2..9
            $table->string('recipient_name', 100)->nullable();
            $table->timestamps();
            $table->unique(['data_id', 'recipient_no']);
        });

        /**
         * 暦年贈与エントリ（1:N） data_id × recipient_no × row_no（1..10）
         */
        Schema::create('past_gift_calendar_entries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('data_id');
            $table->index('data_id');
            $table->unsignedTinyInteger('recipient_no'); // 2..9
            $table->unsignedTinyInteger('row_no');       // 1..10
            $table->unsignedSmallInteger('gift_year')->nullable();
            $table->unsignedTinyInteger('gift_month')->nullable();
            $table->unsignedTinyInteger('gift_day')->nullable();
            $table->unsignedBigInteger('amount_thousand')->nullable(); // rekinen_zoyo
            $table->unsignedBigInteger('tax_thousand')->nullable();    // rekinen_kojo（贈与税額）
            $table->timestamps();
            $table->unique(['data_id', 'recipient_no', 'row_no']);
            $table->index(['gift_year', 'gift_month', 'gift_day']);
        });

        /**
         * 精算課税 贈与エントリ（1:N） data_id × recipient_no × row_no（1..10）
         */
        Schema::create('past_gift_settlement_entries', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('data_id');
            $table->index('data_id');
            $table->unsignedTinyInteger('recipient_no'); // 2..9
            $table->unsignedTinyInteger('row_no');       // 1..10
            $table->unsignedSmallInteger('gift_year')->nullable();
            $table->unsignedTinyInteger('gift_month')->nullable();
            $table->unsignedTinyInteger('gift_day')->nullable();
            $table->unsignedBigInteger('amount_thousand')->nullable(); // seisan_zoyo
            $table->unsignedBigInteger('tax_thousand')->nullable();    // seisan_kojo（贈与税額）
            $table->timestamps();
            $table->unique(['data_id', 'recipient_no', 'row_no']);
            $table->index(['gift_year', 'gift_month', 'gift_day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('past_gift_settlement_entries');
        Schema::dropIfExists('past_gift_calendar_entries');
        Schema::dropIfExists('past_gift_recipients');
    }
};
