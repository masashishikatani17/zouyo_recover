<?php
//App\Http\Controllers\\Tax\ZouyotaxController.php

namespace App\Http\Controllers\Tax;
 
use App\Http\Controllers\Controller;
use App\Models\ZouyoGeneralRate;
use App\Models\ZouyoTokureiRate;
use App\Models\SozokuRate;
use Illuminate\Http\Request;

use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;
 


 
 class ZouyotaxController extends Controller
 {

     // 既存メソッドは省略

    /** 贈与税（一般税率）マスター表示 */
    public function zouyoGeneralMaster(Request $request)
    {
        $dataId = $request->query('data_id');
        $year = (int) date('Y');
        $rates = ZouyoGeneralRate::whereNull('company_id')
            ->where('kihu_year', $year)->where('version', 1)
            ->orderBy('seq')->get()->toArray();
        // 実ファイル: resources/views/zouyo/master/zouyo_general_master.blade.php
        return view('zouyo.master.zouyo_general_master', compact('rates','dataId'));
    }

    /** 贈与税（特例税率）マスター表示 */
    public function zouyoTokureiMaster(Request $request)
    {
        $dataId = $request->query('data_id');
        $year = (int) date('Y');
        $rates = ZouyoTokureiRate::whereNull('company_id')
            ->where('kihu_year', $year)->where('version', 1)
            ->orderBy('seq')->get()->toArray();
        return view('zouyo.master.zouyo_tokurei_master', compact('rates','dataId'));
    }

    /** 相続税 速算表マスター表示 */
    public function sozokuMaster(Request $request)
    {
        $dataId = $request->query('data_id');
        $year = (int) date('Y');
        $rates = SozokuRate::whereNull('company_id')
            ->where('kihu_year', $year)->where('version', 1)
            ->orderBy('seq')->get()->toArray();
        return view('zouyo.master.sozoku_master', compact('rates','dataId'));
    }



    /**
     * これからの贈与：受贈者別の「過年度分の合計」を返すAPI
     * - 暦年：取り戻し対象（元本）の合計（円）
     * - 精算：過年度の精算課税贈与の合計（円）
     * クエリ:
     *   data_id, recipient_no, future_base_year, future_base_month, future_base_day
     * 戻り:
     *   { status:'ok', header:{year,month,day}, past_summary:{calendar_included_yen, settlement_total_yen} }
     */
    public function futureFetch(Request $request)
    {
        $dataId      = (int) $request->query('data_id', 0);
        $recipientNo = (int) $request->query('recipient_no', 0);

        // ヘッダーに統一：相続開始日（基準日）は title.blade の header_* を使用する
        $y           = (int) $request->query('header_year', 0);
        $m           = (int) $request->query('header_month', 0);
        $d           = (int) $request->query('header_day', 0);

        // 基準日（ヘッダの贈与年月日）。未入力時は当年1/1を採用（UIの年齢基準と整合）
        try {
            if ($y >= 1900 && $m >= 1 && $m <= 12 && $d >= 1 && $d <= 31) {
                $baseDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d));
            } else {
                $baseDate = new \DateTimeImmutable(date('Y-01-01'));
            }
        } catch (\Throwable $e) {
            $baseDate = new \DateTimeImmutable(date('Y-01-01'));
        }

        // ルックバック期間（改正対応）
        $r8End      = new \DateTimeImmutable('2026-12-31');
        $r9Start    = new \DateTimeImmutable('2027-01-01');
        $r12End     = new \DateTimeImmutable('2030-12-31');
        $fixedStart = new \DateTimeImmutable('2024-01-01');
        $end        = $baseDate;
        if ($baseDate <= $r8End) {
            $start = $baseDate->sub(new \DateInterval('P3Y'));      // 3年
        } elseif ($baseDate >= $r9Start && $baseDate <= $r12End) {
            $start = $fixedStart;                                   // 2024-01-01〜当日
        } else {
            $start = $baseDate->sub(new \DateInterval('P7Y'));      // 7年
        }

        // ------- 集計（対象受贈者のみ） -------
        // 暦年：取り戻し対象（元本＝amount_thousand）の合計（千円→円）
        $calSumKyen = 0;
        $calendar = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->get(['gift_year','gift_month','gift_day','amount_thousand']);
        foreach ($calendar as $row) {
            $gy = (int) ($row->gift_year ?? 0);
            $gm = (int) ($row->gift_month ?? 0);
            $gd = (int) ($row->gift_day ?? 0);
            $amtK = (int) ($row->amount_thousand ?? 0);
            if ($amtK <= 0) continue;
            try {
                $giftDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $gy, $gm, $gd));
            } catch (\Throwable $e) { continue; }
            if ($giftDate >= $start && $giftDate <= $end) {
                $calSumKyen += $amtK;
            }
        }

        // ▼ 精算課税：年別に集計し、各年で 1,100千円控除後の値を算入する
        $setRows = PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->get(['gift_year','amount_thousand','tax_thousand']);


        $setByYear = [];
        $setTaxKyen = 0;  // 贈与税（千円）

        foreach ($setRows as $r) {
            $ak = max(0, (int)($r->amount_thousand ?? 0));   // 金額（千円）
            $tk = max(0, (int)($r->tax_thousand ?? 0));      // 税額（千円）
            if ($ak <= 0) continue;

            $yy = (int)($r->gift_year ?? 0);
            $setByYear[$yy] = ($setByYear[$yy] ?? 0) + $ak;
            $setTaxKyen += $tk;
        }

        // ▼ 精算課税の相続税算入額（千円）＝年別 max(sum - 1100, 0) の合計
        $setAfterBasicKyen = 0;
        foreach ($setByYear as $year => $sumK) {
            $setAfterBasicKyen += max(0, $sumK - 1100);
        }

        // ▼ 旧 setAmtKyen（控除前合計）も保持したければ残すが、算入額は控除後を使用
        $setAmtKyen = array_sum($setByYear);  // 控除前の元本合計（千円）

        // ---- 「過年度分の合計」行 用の集計（すべて千円単位）----
        // 暦年：年別に合算 → 基礎控除(1,100)を年数分控除
        $calByYear = [];
        foreach ($calendar as $row) {
            $gy = (int)($row->gift_year ?? 0);
            $gm = (int)($row->gift_month ?? 0);
            $gd = (int)($row->gift_day ?? 0);
            $amtK = (int)($row->amount_thousand ?? 0);
            if ($amtK <= 0) continue;
            try {
                $giftDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $gy, $gm, $gd));
            } catch (\Throwable $e) { continue; }
            if ($giftDate < $start || $giftDate > $end) continue;
            $calByYear[$gy] = ($calByYear[$gy] ?? 0) + $amtK;
        }
        $calYears = array_keys($calByYear);
        $calAmountKyen = array_sum($calByYear);                      // 贈与額 合計
        $calBasicKyen  = 1100 * count($calYears);                    // 基礎控除 合計
        $calAfterBasicKyen = 0;                                      // 基礎控除後 合計
        foreach ($calByYear as $sumK) {
            $calAfterBasicKyen += max($sumK - 1100, 0);
        }
        $calTaxKyen = (int) PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->get(['gift_year','gift_month','gift_day','tax_thousand'])
            ->reduce(function($c,$r) use($start,$end){
                $gy=(int)$r->gift_year; $gm=(int)$r->gift_month; $gd=(int)$r->gift_day;
                try{$dt=new \DateTimeImmutable(sprintf('%04d-%02d-%02d',$gy,$gm,$gd));}catch(\Throwable $e){return $c;}
                return ($dt>=$start && $dt<=$end) ? ($c + (int)($r->tax_thousand ?? 0)) : $c;
            }, 0);

        // 精算：合計、2,500万円控除後、20%税額
        $setAfter25mKyen = max($setAmtKyen - 25000, 0);
 
        return response()->json([
            'status' => 'ok',
            'header' => ['year' => $y, 'month' => $m, 'day' => $d],
            'past_summary' => [
                'calendar_included_yen' => $calSumKyen * 1000,
                // ▼精算課税は控除後の算入額を返す（PDF などで使用する正しい値）
                'settlement_total_yen'  => $setAfterBasicKyen * 1000,
            ],
            // ▼ 過年度分の合計 行（千円単位で返す）
            'past_row' => [
                'cal_amount_kyen'       => $calAmountKyen,
                'cal_basic_kyen'        => $calBasicKyen,
                'cal_after_basic_kyen'  => $calAfterBasicKyen,
                'cal_tax_kyen'          => $calTaxKyen,
                // ▼元本合計（参考表示）
                'set_amount_kyen'       => $setAmtKyen,
                // ▼相続税算入額（控除後）※これが正しい相続税計算で使う値
                'set_after_basic_kyen'  => $setAfterBasicKyen,
                // ▼贈与税（千円）
                'set_tax20_kyen'        => $setTaxKyen,
            ],
         ]);


    }

 }
