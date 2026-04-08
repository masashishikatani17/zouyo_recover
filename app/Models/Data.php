<?php
//App\Models\Data
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Data extends Model
{
    /**
     * DB テーブル名
     */
    protected $table = 'datas'; // id, company_id, group_id, ...

    /**
     * 親ファーストでサーバ側で data_id を強制代入するため、
     * 不用意な一括代入を避けたいカラムは guarded に。
     */
    protected $guarded = []; // 必要に応じて ['id'] 等に変更

    // ============================================================
    // 提案書：ヘッダ & 家族明細
    // ============================================================
    /** 提案書ヘッダ（1:1） */
    public function proposalHeader(): HasOne
    {
        return $this->hasOne(\App\Models\ProposalHeader::class, 'data_id')->withDefault();
    }

    /** 提案書 家族・資産明細（1:N） */
    public function proposalFamilyMembers(): HasMany
    {
        return $this->hasMany(\App\Models\ProposalFamilyMember::class, 'data_id')
                    ->orderBy('row_no');
    }

    // ============================================================
    // 過年度の贈与
    // ============================================================
    /** 過年度贈与ヘッダ（1:1） */
    public function pastGiftInput(): HasOne
    {
        return $this->hasOne(\App\Models\PastGiftInput::class, 'data_id')->withDefault();
    }

    /** 受贈者一覧（1:N, 受贈者No=2..9） */
    public function pastGiftRecipients(): HasMany
    {
        return $this->hasMany(\App\Models\PastGiftRecipient::class, 'data_id')
                    ->orderBy('recipient_no');
    }

    /** 暦年贈与エントリ（1:N） */
    public function pastGiftCalendarEntries(): HasMany
    {
        return $this->hasMany(\App\Models\PastGiftCalendarEntry::class, 'data_id')
                    ->orderBy('recipient_no')->orderBy('row_no');
    }

    /** 精算課税 贈与エントリ（1:N） */
    public function pastGiftSettlementEntries(): HasMany
    {
        return $this->hasMany(\App\Models\PastGiftSettlementEntry::class, 'data_id')
                    ->orderBy('recipient_no')->orderBy('row_no');
    }

    // ============================================================
    // これからの贈与
    // ============================================================
    /** これからの贈与：ヘッダ（1:1） */
    public function futureGiftHeader(): HasOne
    {
        return $this->hasOne(\App\Models\FutureGiftHeader::class, 'data_id')->withDefault();
    }

    /** これからの贈与：受贈者（1:N, 受贈者No=2..9） */
    public function futureGiftRecipients(): HasMany
    {
        return $this->hasMany(\App\Models\FutureGiftRecipient::class, 'data_id')
                    ->orderBy('recipient_no');
    }

    /** これからの贈与：プラン明細（1:N, row_no=1..20） */
    public function futureGiftPlanEntries(): HasMany
    {
        return $this->hasMany(\App\Models\FutureGiftPlanEntry::class, 'data_id')
                    ->orderBy('recipient_no')->orderBy('row_no');
    }

    // ============================================================
    // 遺産分割（現時点）
    // ============================================================
    /** 遺産分割：ヘッダ（1:1） */
    public function inheritanceDistributionHeader(): HasOne
    {
        return $this->hasOne(\App\Models\InheritanceDistributionHeader::class, 'data_id')->withDefault();
    }

    /** 遺産分割：明細（1:N, recipient_no=2..10） */
    public function inheritanceDistributionMembers(): HasMany
    {
        return $this->hasMany(\App\Models\InheritanceDistributionMember::class, 'data_id')
                    ->orderBy('recipient_no');
    }

    



    // ============================================================
    // Guest（訪問者 or 顧客）情報：親ファースト構造で必要
    // ============================================================
    /**
     * Data ←→ Guest は group_id で紐づく 1:1 関係
     * 現在の datas テーブルには guest_id が無いため、
     * guest.id = data.guest_id ではなく guest.group_id = data.group_id で引く。
     */
    public function guest(): HasOne
    {
        return $this->hasOne(\App\Models\Guest::class, 'group_id', 'group_id')->withDefault();
     }
    
    
    
}
