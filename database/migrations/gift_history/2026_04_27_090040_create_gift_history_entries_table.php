<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('gift_history')->create('gift_history_entries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('gift_history_case_id');

            // 贈与形態：calendar / settlement
            $table->string('gift_taxation_type', 30);
            $table->date('gift_date');
            $table->unsignedSmallInteger('gift_year')->nullable();

            // 贈与者・受贈者
            $table->unsignedBigInteger('donor_family_member_id');
            $table->unsignedBigInteger('recipient_family_member_id');
            $table->string('donor_name_snapshot')->nullable();
            $table->string('recipient_name_snapshot')->nullable();

            // 相互続柄
            // donor_relationship_from_recipient:
            //   贈与者は受贈者から見て何か
            // recipient_relationship_from_donor:
            //   受贈者は贈与者から見て何か
            $table->unsignedInteger('donor_relationship_code_from_recipient')->nullable();
            $table->string('donor_relationship_from_recipient')->nullable();
            $table->unsignedInteger('recipient_relationship_code_from_donor')->nullable();
            $table->string('recipient_relationship_from_donor')->nullable();

            // 財産情報
            $table->string('asset_category', 50);
            $table->string('asset_category_name_snapshot')->nullable();
            $table->string('asset_name')->nullable();
            $table->text('asset_description')->nullable();
            $table->unsignedBigInteger('gift_amount_yen')->default(0);

            // 暦年贈与：general / tokurei
            $table->string('calendar_tax_type', 30)->nullable();

            // 生前贈与加算期限
            $table->date('addback_3year_deadline_date')->nullable();
            $table->date('addback_final_deadline_date')->nullable();

            // 相続時精算課税の確認情報
            $table->boolean('settlement_election_confirmed')->default(false);
            $table->boolean('settlement_no_return_confirmed')->default(false);
            $table->date('settlement_notification_date')->nullable();

            // 贈与税額
            // 第1実装では自動計算は後続フェーズのため、tax_auto_amount_yen は0で保存する。
            $table->unsignedBigInteger('tax_auto_amount_yen')->default(0);
            $table->boolean('tax_override_enabled')->default(false);
            $table->unsignedBigInteger('tax_override_amount_yen')->nullable();
            $table->unsignedBigInteger('tax_final_amount_yen')->default(0);
            $table->string('tax_override_reason')->nullable();

            // 申告・契約書・備考
            $table->string('tax_return_status', 50)->nullable();
            $table->string('gift_contract_status', 50)->nullable();
            $table->text('memo')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('gift_history_case_id', 'gift_history_entries_case_idx');
            $table->index(['gift_history_case_id', 'gift_date'], 'gift_history_entries_case_date_idx');
            $table->index(['gift_history_case_id', 'recipient_family_member_id', 'gift_year'], 'gift_history_entries_recipient_year_idx');
            $table->index(['gift_history_case_id', 'donor_family_member_id'], 'gift_history_entries_donor_idx');
            $table->index('gift_taxation_type', 'gift_history_entries_taxation_type_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('gift_history')->dropIfExists('gift_history_entries');
    }
};