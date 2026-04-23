<!--   isanbunkatu.blade  -->

<style>
  .small-text {
    font-size: 14px;
  }
</style>

<style>
  input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    font-size: 12px;
    height: 20px;        /* ★ここが追加 */
    padding: 0 4px;      /* ★上下詰めて横だけ少し余裕 */
    line-height: 1.2;    /* ★行間の詰め */
  }

  .input-small {
    font-size: 12px;
    height: 20px;        /* class指定があるならこちらにも */
    padding: 0 4px;
  }
</style>

<style>
  .vertical-text {                /*縦書き用*/
    writing-mode: vertical-rl;
    text-orientation: upright;
    font-weight: bold;
    text-align: center;
    white-space: nowrap;
  }
</style>

{{-- ▼ 遺産分割表：左3列固定＋相続人9列横スクロール（安定版） --}}
<style>
  /* 左右余白 */
  .isan-container {
    padding-left: 10px;
    padding-right: 10px;
  }

  /* 左テーブル + 右スクロールを横並び */
  .isan-split {
    display: flex;
    align-items: flex-start;
    gap: 0; /* くっつける */
  }

  .isan-left {
    flex: 0 0 auto;
  }

  .isan-right-wrap {
    flex: 1 1 auto;
    overflow-x: auto;
  }


  /* 相続人見出し帯用：左固定表ぶんのスペーサ */
  .isan-left-spacer {
    flex: 0 0 390px; /* 左固定表の実幅(28+36+26+185+35+80) */
  }

  .isan-header-wrap {
    flex: 1 1 auto;
    min-width: 0;
    position: relative;
    padding-top: 16px;    
  }

  .isan-right-header-table {
    width: 630px;    
    table-layout: fixed;
    margin: 0;
  }


  .isan-header-unit {
    position: absolute;
    top: 0;
    right: 0;
    font-size: 12px;
    line-height: 1;
    white-space: nowrap;
  }


  /* 2つのテーブルは余白を消して揃える */
  .isan-left table,
  .isan-right-wrap table {
    margin: 0;
    table-layout: fixed;
  }

  /* セルの基本（既存table-compact-pに合わせる） */
  #isan-left-table th, #isan-left-table td,
  #isan-right-table th, #isan-right-table td {
    border: 1px solid #999 !important;
    padding: 2px 4px;
    font-size: 12px;
    line-height: 1.2;
    white-space: nowrap;
    vertical-align: middle;
  }

  /* 左テーブルの幅固定
     col1: 大分類 / col2: 小分類(税額控除用: 狭め) / col3: 申告納税額拡張分 / col4: 項目 / col5: 番号 / col6: 合計 */
  #isan-left-table { width: 390px; } /* 28 + 36 + 26 + 185 + 35 + 80 = 390 */


  #isan-left-table .isan-group-col,
  #isan-left-table .isan-subgroup-col {
    padding: 0 !important;
    text-align: center;
    vertical-align: middle;
  }

  #isan-left-table .isan-group-col .vertical-text,
  #isan-left-table .isan-subgroup-col .vertical-text {
    display: inline-block;
    line-height: 1.05;
    letter-spacing: 1px;
  }


  #isan-left-table .isan-subgroup-2rows {
    padding: 0 !important;
    font-weight: 700;
    color: #111827;    
  }

  #isan-left-table .isan-subgroup-2rows .vertical-text,
  #isan-left-table .isan-subgroup-2rows > div {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    line-height: 1.05;
    white-space: normal;
    font-weight: 700;
  }


  #isan-left-table .isan-subgroup-blank {
    background-color: #fff;
  }

  #isan-left-table .isan-item-center {
    text-align: center;
  }



  /* 右側：相続人列（100px×9）なので最小幅だけ確保 */
  #isan-right-table { min-width: 900px; }

  /* 境界の二重線を防ぐ：右テーブルの一番左線を消す */
  #isan-right-table th:first-child,
  #isan-right-table td:first-child {
    border-left: none !important;
  }

  /* 見出し背景（左も右もbg-blueは同じ色に） */
  tr.bg-blue > th,
  tr.bg-blue > td {
    background-color: #e9eff7 !important;
  }


  /* 相続人ヘッダ（1段目:氏名 / 2段目:続柄） */
  #isan-left-table thead .isan-left-head-name-row {
    height: 44px; /* 左見出しは固定値（右の 24px + 20px に合わせる） */
  }

  #isan-right-table thead .isan-heir-name-row {
    height: 24px;
  }


  #isan-right-table thead .isan-heir-rel-row {
    height: 20px;
  }

  #isan-right-table thead .isan-heir-name-row th {
    font-weight: bold;
  }

  #isan-right-table thead .isan-heir-rel-row th {
    font-size: 11px;
    font-weight: normal;
  }

</style>


<style>
  /* 入力可 / 参照 / 自動計算 の見た目を明確化 */
  .isan-field-input {
    background: #fff8db !important;
    border: 2px solid #d4a72c !important;
    color: #111827 !important;
  }

  .isan-field-input:focus {
    background: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
    outline: none;
  }

  .isan-field-ref {
    background: #edf6ff !important;
    border: 1px solid #9ec5fe !important;
    color: #1f3b5b !important;
  }

  .isan-field-calc {
    background: #eef2f6 !important;
    border: 1px solid #cbd5e1 !important;
    color: #4b5563 !important;
  }

  .isan-field-ref:focus,
  .isan-field-calc:focus {
    box-shadow: none !important;
    outline: none;
  }
</style>




@php
  // 被相続人名（行1）
  $donorNameCandidates = [
      \Illuminate\Support\Arr::get($family ?? [],        '1.name'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [], '1.name'),
      old('customer_name'),
      old('name.1'),
      request()->input('customer_name'),
      request()->input('name.1'),
  ];

  $donorName = '';
  foreach ($donorNameCandidates as $cand) {
      $cand = is_string($cand) ? trim($cand) : '';
      if ($cand !== '') {
          $donorName = $cand;
          break;
      }
  }
@endphp



<table class="table-base ms-10" style="width: 290px;">
  <tr>
    <th class="bg-blue" style="width: 120px;">被相続人</th>
    <td style="width: 170px;">
      <input type="text" class="form-control d-inline input-small isan-field-ref"
             id="isan-customer-name"      
             name="customer_name"
             style="width: 160px; background-color: #f0f0f0; text-align: left;"             
             readonly tabindex="-1" value="{{ $donorName }}">             
    </td>
  </tr>
</table>

<table class="table-base ms-10" style="width: 350px;">
  <tr>
    <th class="bg-blue" style="width: 120px;">遺産の分割方法</th>
    <td class="bg-cream" style="width: 230px;">
      @php
        // 0=法定, 9=手入力（Controller 側でそのように保存）
        $method   = $prefillInheritance['method_code'] ?? null;
        $isAuto   = is_null($method) ? true : ((int)$method === 0);
        $isManual = (!is_null($method) && (int)$method === 9);
      @endphp
      <label><input type="radio" name="input_mode" value="auto" @checked($isAuto)> 法定相続割合</label>
      <label class="ms-3"><input type="radio" name="input_mode" value="manual" @checked($isManual)> 手入力</label>
    </td>
  </tr>
</table>

<div class="mt-6">

  {{-- ▼ 相続税計算結果（$results）から「対策前(before)」を使用し、相続人 index（2..10）で引けるよう整形 --}}
  @php
    // デバッグ：渡されている結果の内容を確認
    //Log::debug('Results:', ['results' => $results]);

    // この画面は「遺産分割(現時点)＝対策前」を表示する。
    $root = $results ?? [];
    $calc = [];
    if (is_array($root) && isset($root['before']) && is_array($root['before'])) {
        $calc = $root['before'];
    } elseif (is_array($root) && isset($root['after']) && is_array($root['after'])) {
        $calc = $root['after'];
    }

    //Log::debug('Before Data:', ['before' => $calc['before'] ?? 'No before data']);
    //Log::debug('After Data:',  ['after'  => $calc['after']  ?? 'No after data']);

    if (empty($calc) || !is_array($calc)) {
        $calc = ['summary' => [], 'heirs' => [], 'meta' => []];
    } else {
        $calc['summary'] = $calc['summary'] ?? [];
        $calc['heirs']   = $calc['heirs']   ?? [];
        $calc['meta']    = $calc['meta']    ?? [];
    }

    $heirsByIdx = [];
    if (is_array($calc) && isset($calc['heirs']) && is_iterable($calc['heirs'])) {
        foreach ($calc['heirs'] as $h) {
            if (isset($h['row_index'])) {
                $heirsByIdx[(int)$h['row_index']] = $h;
            }
        }
    }

    //Log::debug('Heirs Data:', ['heirs' => $heirsByIdx]);

    // 円→千円へ（四捨五入）。null/未定義は空文字で表示
    $toKyen = function ($yen) {
        if (!isset($yen)) return '';
        $v = (int) round(((int)$yen) / 1000);
        return number_format($v);
    };

    // 比率→小数（小数点以下4位固定、%にしない）
    $toFixed4 = function ($ratio) {
        if (!isset($ratio)) return '';
        return number_format((float)$ratio, 4, '.', '');
    };

    // heirs キーの合計（円）を千円換算で返す
    $sumHeirKyen = function ($key) use ($heirsByIdx) {
        $sum = 0;
        for ($i=2; $i<=10; $i++) {
          $sum += (int)($heirsByIdx[$i][$key] ?? 0);
        }
        return number_format((int)round($sum/1000));
    };

    // summary の値（円）を千円換算で
    $sumKyenFromSummary = function ($key) use ($calc) {
        $v = (int)($calc['summary'][$key] ?? 0);
        return number_format((int)round($v/1000));
    };

    // No16 相続時精算課税分の贈与税額控除額（各人欄と同じ基準で合計）
    $sumSettlementGiftYen = 0;
    for ($__i = 2; $__i <= 10; $__i++) {
        $sumSettlementGiftYen += (int)($heirsByIdx[$__i]['settlement_gift_tax_yen'] ?? 0);
    }
    $sumSettlementGiftKyen = $sumSettlementGiftYen / 1000;

    // 納付税額 / 還付税額 集計用
    $sumPayableYen = 0;
    $sumRefundYen  = 0;
    for ($__i = 2; $__i <= 10; $__i++) {
        $raw = array_key_exists('raw_final_after_settlement_yen', $heirsByIdx[$__i] ?? [])
            ? (int)($heirsByIdx[$__i]['raw_final_after_settlement_yen'] ?? 0)
            : ((int)($heirsByIdx[$__i]['sashihiki_tax_yen'] ?? 0) - (int)($heirsByIdx[$__i]['settlement_gift_tax_yen'] ?? 0));

        if ($raw >= 0) {
            $sumPayableYen += $raw;
        } else {
            $sumRefundYen += abs($raw);
        }
    }
    $sumPayableKyen     = $sumPayableYen / 1000;
    $sumRefundKyen      = $sumRefundYen / 1000;


    //Log::debug('Total Payable (Kyen):', ['payable' => $sumPayableKyen]);
    //Log::debug('Total Refund (Kyen):',  ['refund'  => $sumRefundKyen]);

    // ▼ 続柄マスタは config/relationships.php を唯一の参照元にする
    $relationships = config('relationships', []);  

    $heirNames = [];
    $heirRels  = [];

    for ($no = 2; $no <= 10; $no++) {
        $key = (string)$no;

        // 氏名
        $nameCandidates = [
            \Illuminate\Support\Arr::get($family ?? [],        $key . '.name'),
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.name'),
            old("name.$no", null),
            request()->input("name.$no"),
        ];
        $name = '';
        foreach ($nameCandidates as $cand) {
            $cand = is_string($cand) ? trim($cand) : '';
            if ($cand !== '') { $name = $cand; break; }
        }
        $heirNames[$no] = $name;

        // relationship_code
        $relCode = \Illuminate\Support\Arr::get($family ?? [],        $key . '.relationship_code');
        if ($relCode === null) {
            $relCode = \Illuminate\Support\Arr::get($prefillFamily ?? [],$key . '.relationship_code');
        }
        if ($relCode === null) {
            $tmp = old("relationship_code.$no", null) ?? request()->input("relationship_code.$no");
            if ($tmp !== null && trim((string)$tmp) !== '') {
                $relCode = $tmp;
            }
        }

        $relLabel = '';
        if ($name !== '' && $relCode !== null && trim((string)$relCode) !== '') {
            $code = (int)preg_replace('/[^\d\-]/u', '', (string)$relCode);
            if (array_key_exists($code, $relationships)) {
                $relLabel = $relationships[$code];
            } else {
                $relLabel = (string)$relCode;
            }
        }

        $heirRels[$no] = $relLabel;
    }
  @endphp

<div class="isan-container">

  <div class="isan-split mb-0">
    <div class="isan-left-spacer" aria-hidden="true"></div>
    <div class="isan-header-wrap">
      <div class="isan-header-unit">(単位:千円)</div>      
      <table class="table-base me-3 mb-0 b-none isan-right-header-table">
        <tr>
          <td class="bg-blue b-r-no" style="width: 630px;">相　続　人</td>
        </tr>
      </table>
    </div>
  </div>





  <div class="isan-split">

    {{-- =========================
        左：固定（分類／小分類／項目／番号／合計）
        ========================= --}}
    <div class="isan-left">
      <table id="isan-left-table" class="table-compact-p">
        <colgroup>
          <col style="width:28px;">
          <col style="width:36px;">
          <col style="width:26px;">
          <col style="width:185px;">          
          <col style="width:35px;">
          <col style="width:80px;">
        </colgroup>

         <thead>
           <tr class="bg-blue isan-left-head-name-row">
             <th colspan="4" class="text-center">項　　目</th>
             <th class="text-center">番号</th>
             <th class="text-center">合　計</th>
           </tr>
         </thead>


        <tbody>

          <!-- 相続税計算 -->
          <?php $i = 1; ?>
          <tr>
            <td class="isan-group-col" rowspan="5">
              <div class="vertical-text">課税価格</div>
            </td>
            <td colspan="3" class="text-start" style="font-weight: bold;">金融資産</td>            
            <td class="text-center">{{ $i }}</td>

            {{-- 合計（表示専用） --}}
            @php
              $___basePropertyKyen = (int)($prefillFamily[1]['property'] ?? 0); // 既存：総資産（千円）
              $___baseCashKyen     = (int)($prefillFamily[1]['cash'] ?? 0);     // ★追加：金融資産（千円）
              $___baseOtherKyen    = max(0, $___basePropertyKyen - $___baseCashKyen); // ★追加：その他資産（千円）
            @endphp
            <td>
              <input type="text"
                     id="id_cash_total"
                     name="cash_total_k"
                     class="form-control suji8 comma decimal0"
                     readonly tabindex="-1"
                     value="{{ $___baseCashKyen ? number_format($___baseCashKyen) : '' }}">
            </td>
          </tr>

          <?php $i = 2; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">その他資産</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text"
                     id="id_other_total"
                     name="other_total_k"
                     class="form-control suji8 comma decimal0"
                     readonly tabindex="-1"
                     value="{{ $___baseOtherKyen ? number_format($___baseOtherKyen) : '' }}">
            </td>
          </tr>

          <?php $i = 3; ?>
          <tr>
            <td colspan="3" class="text-center" style="font-weight: bold;">合　　計</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text"
                     id="id_taxable_manu_total"
                     name="property[1]"
                     class="form-control suji8 comma decimal0"
                     readonly tabindex="-1"
                     value="{{ $___basePropertyKyen ? number_format($___basePropertyKyen) : '' }}">
            </td>
          </tr>

          <!-- 生前贈与加算 -->
          <?php $i = 4; ?>
          @php
              $sumLifetimeGiftAddition = 0;
              for ($no = 2; $no <= 10; $no++) {
                  $lifetimeGiftAddition = (int)($heirsByIdx[$no]['past_gift_included_yen'] ?? 0) / 1000;
                  $heirsByIdx[$no]['lifetime_gift_addition'] = number_format($lifetimeGiftAddition);
                  $sumLifetimeGiftAddition += $lifetimeGiftAddition;
              }
          @endphp
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">生前贈与加算額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     id="isan-total-lifetime-gift"              
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     name="id_lifetime_gift_addition" inputmode="numeric"
                     value="{{ number_format($sumLifetimeGiftAddition) }}">
            </td>
          </tr>

          {{-- 合計（所有財産 + 生前贈与加算） --}}
          <?php $i = 5; ?>
          <tr>
            <td colspan="3" class="text-center" style="font-weight: bold;">課税価格</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     data-role="taxable_total_overall"
                     value="{{ number_format(
                        (int)preg_replace('/[^\d]/', '', $___basePropertyKyen) +
                        (int)preg_replace('/[^\d]/', '', ($sumLifetimeGiftAddition ?? 0))
                     ) }}">
            </td>
          </tr>

          @php
            ////////////////////////////////////////////////////////////////////
            // 基礎控除額
            // ▼ 直書き禁止
            //   - サーバー側でマスターから取得した値を優先
            //   - 未対応時だけ現行値（30,000 / 6,000 千円）へフォールバック
            ////////////////////////////////////////////////////////////////////
            $legalHeirCount = 0;
            for ($no = 2; $no <= 10; $no++) {
                $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
                $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
                if ($bunsi >= 1 && $bunbo >= 1) {
                    $legalHeirCount++;
                }
            }

            // 再描画直後などで $heirsByIdx に未反映のときは old()/request() も見る
            if ($legalHeirCount === 0) {
                for ($no = 2; $no <= 10; $no++) {
                    $tmpBunsi = old("bunsi.$no", request()->input("bunsi.$no"));
                    $tmpBunbo = old("bunbo.$no", request()->input("bunbo.$no"));
                    $tmpBunsi = (int)preg_replace('/[^\d\-]/u', '', (string)$tmpBunsi);
                    $tmpBunbo = (int)preg_replace('/[^\d\-]/u', '', (string)$tmpBunbo);
                    if ($tmpBunsi >= 1 && $tmpBunbo >= 1) {
                        $legalHeirCount++;
                    }
                }
            }

            $basicDeductionMeta = is_array($calc['meta'] ?? null) ? $calc['meta'] : [];

            $basicDeductionBaseKyen = (int)(
                $basicDeductionMeta['basic_deduction_base_kyen']
                ?? $basicDeductionMeta['basic_deduction_base_amount_kyen']
                ?? old('basic_deduction_base_kyen', request()->input('basic_deduction_base_kyen', 30000))
            );

            $basicDeductionPerHeirKyen = (int)(
                $basicDeductionMeta['basic_deduction_per_heir_kyen']
                ?? $basicDeductionMeta['basic_deduction_per_heir_amount_kyen']
                ?? old('basic_deduction_per_heir_kyen', request()->input('basic_deduction_per_heir_kyen', 6000))
            );

            $basicDeductionKyen = $basicDeductionBaseKyen + ($basicDeductionPerHeirKyen * $legalHeirCount);

            $basicDeductionLabel = sprintf(
                '基礎控除額　%s千円＋%s千円×%d人',
                number_format($basicDeductionBaseKyen),
                number_format($basicDeductionPerHeirKyen),
                $legalHeirCount
            );

          @endphp

          <?php $i = 6; ?>

          <tr>
            
            <td class="isan-group-col" rowspan="7">
              <div class="vertical-text">各人の算出税額</div>
            </td>
            <td colspan="3" class="text-start" style="font-weight: bold;">
              <span id="basic-deduction-label">{{ $basicDeductionLabel }}</span>
              <input type="hidden" id="basic_deduction_base_kyen" value="{{ $basicDeductionBaseKyen }}">
              <input type="hidden" id="basic_deduction_per_heir_kyen" value="{{ $basicDeductionPerHeirKyen }}">
            </td>

            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="basic_deduction_amount"
                     value="{{ number_format($basicDeductionKyen) }}">                     
            </td>
          </tr>

          @php
            $anbunMode = $calc['meta']['anbun_mode'] ?? $calc['summary']['anbun_mode'] ?? null;
          @endphp

          @php
            ////////////////////////////////////////////////////////////////////
            // 課税遺産総額の各相続人按分（税務上の法定相続割合 bunsi/bunbo ベース）
            ////////////////////////////////////////////////////////////////////

            $totalTaxableEstateK = (int) round(((int)($calc['summary']['taxable_estate'] ?? 0)) / 1000);

            $taxableEstateShareKByHeir = [];
            for ($no = 2; $no <= 10; $no++) {
                $taxableEstateShareKByHeir[$no] = 0;
            }

            $gcd = function (int $a, int $b): int {
                $a = abs($a); $b = abs($b);
                while ($b !== 0) { $t = $a % $b; $a = $b; $b = $t; }
                return $a === 0 ? 1 : $a;
            };
            $lcm = function (int $a, int $b) use ($gcd): int {
                $a = abs($a); $b = abs($b);
                if ($a === 0 || $b === 0) return 0;
                return (int)($a / $gcd($a, $b) * $b);
            };

            $bunboLcm = 1;
            $targets  = [];
            for ($no = 2; $no <= 10; $no++) {
                $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
                $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
                if ($bunsi >= 1 && $bunbo >= 1) {
                    $targets[] = $no;
                    $bunboLcm  = $lcm($bunboLcm, $bunbo);
                }
            }

            $weights = [];
            $sumW    = 0;
            foreach ($targets as $no) {
                $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
                $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
                $w = ($bunbo > 0) ? (int)($bunsi * ($bunboLcm / $bunbo)) : 0;
                $weights[$no] = $w;
                $sumW += $w;
            }

            for ($no = 2; $no <= 10; $no++) { $taxableEstateShareKByHeir[$no] = 0; }

            if ($totalTaxableEstateK > 0 && $sumW > 0) {
                $allocatedK = 0;
                $lastNo     = null;
                foreach ($targets as $no) {
                    $w = (int)($weights[$no] ?? 0);
                    if ($w <= 0) continue;
                    $lastNo = $no;
                    $shareK = (int)floor($totalTaxableEstateK * $w / $sumW);
                    $taxableEstateShareKByHeir[$no] = $shareK;
                    $allocatedK += $shareK;
                }
                if ($lastNo !== null) {
                    $taxableEstateShareKByHeir[$lastNo] += ($totalTaxableEstateK - $allocatedK);
                }
            }
          @endphp

          <?php $i = 7; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">課税遺産総額</td>
            <td class="text-center">{{ $i }}</td>
            @php $showTotal = $sumKyenFromSummary('taxable_estate'); @endphp
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-taxable-estate"                     
                     value="{{ $showTotal }}">
            </td>
          </tr>

          <?php $i = 8; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">法定相続分</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0 text-center"
                     style="background-color:#f0f0f0; text-align:center !important;" readonly tabindex="-1" value="">
            </td>
          </tr>

          <?php $i = 9; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">相続税の総額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-sozoku-tax"
                     value="{{ $sumKyenFromSummary('sozoku_tax_total') }}">
            </td>
          </tr>

          <?php $i = 10; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">あん分割合</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1" 
                     id="isan-total-anbun-ratio"
                     value="1.0000">
            </td>
          </tr>

          <?php $i = 11; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">算出税額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-sanzutsu"                     
                     value="{{ $sumHeirKyen('sanzutsu_tax_yen') }}">
            </td>
          </tr>

          <?php $i = 12; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">２割加算</td>
            <td class="text-center">{{ $i }}</td>
            @php
              $sumInc = 0;
              for ($__i = 2; $__i <= 10; $__i++) {
                $inc = ((int)($heirsByIdx[$__i]['final_tax_yen'] ?? 0)) - ((int)($heirsByIdx[$__i]['sanzutsu_tax_yen'] ?? 0));
                $sumInc += max(0, $inc);
              }
            @endphp
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-two-tenths"                     
                     value="{{ $sumInc ? number_format((int)round($sumInc/1000)) : '' }}">
            </td>
          </tr>

          <?php $i = 13; ?>
          <tr>
            <td class="isan-group-col" rowspan="8">
              <div class="vertical-text">各人の納付・還付税額</div>
            </td>
            <td class="isan-subgroup-col" rowspan="4">
              <div class="vertical-text">税額控除</div>
            </td>            
            <td colspan="2" class="text-start" style="font-weight: bold;">暦年課税分の贈与税額控除額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-calendar-credit"                     
                     value="{{ $sumKyenFromSummary('total_gift_tax_credits') }}">
            </td>
          </tr>

          <?php $i = 14; ?>
          <tr>
            <td colspan="2" class="text-start" style="font-weight: bold;">配偶者の税額軽減額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-spouse-relief"                     
                     value="{{ $sumKyenFromSummary('total_spouse_relief') }}">
            </td>
          </tr>

          <?php $i = 15; ?>
          <tr>
            <td colspan="2" class="text-start" style="font-weight: bold;">その他の税額控除額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-other-credit"                     
                     value="{{ $sumKyenFromSummary('total_other_credits') }}">
            </td>
          </tr>

          <?php $i = 16; ?>
          <tr>
            <td colspan="2" class="text-center" style="font-weight: bold;">合　計</td>
            <td class="text-center">{{ $i }}</td>
            @php
              $__sumCredits = (int)($calc['summary']['total_gift_tax_credits'] ?? 0)
                            + (int)($calc['summary']['total_spouse_relief'] ?? 0)
                            + (int)($calc['summary']['total_other_credits'] ?? 0);
            @endphp
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-credit"                     
                     value="{{ $__sumCredits ? number_format((int)round($__sumCredits / 1000)) : '' }}">
            </td>
          </tr>

          <?php $i = 17; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">差引税額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-sashihiki"
                     value="{{ $sumKyenFromSummary('total_sashihiki_tax') }}">
            </td>
          </tr>

          <?php $i = 18; ?>
          <tr>
            <td colspan="3" class="text-start" style="font-weight: bold;">相続時精算課税分の贈与税額控除額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-settlement-credit"
                     value="{{ $sumSettlementGiftKyen !== 0 ? number_format((int)round($sumSettlementGiftKyen)) : '' }}">
            </td>
          </tr>


          <?php $i = 19; ?>
          <tr>
            <td colspan="2" class="isan-subgroup-col isan-subgroup-2rows" rowspan="2">
              <div>申告納税額</div>
            </td>            
            <td class="text-start" style="font-weight: bold;">納付税額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-payable"                     
                     value="{{ $sumPayableKyen ? number_format((int)round($sumPayableKyen)) : 0 }}">
            </td>
          </tr>

          <?php $i = 20; ?>
          <tr>
            <td class="text-start" style="font-weight: bold;">還付税額</td>
            <td class="text-center">{{ $i }}</td>
            <td>
              <input type="text" class="form-control suji8 comma decimal0"
                     style="background-color:#f0f0f0;" readonly tabindex="-1"
                     id="isan-total-refund"                     
                     value="{{ $sumRefundKyen ? number_format((int)round($sumRefundKyen)) : 0 }}">
            </td>
          </tr>

        </tbody>
      </table>
    </div>

    {{-- =========================
        右：横スクロール（相続人9列）
        ========================= --}}
    <div class="isan-right-wrap">
      <table id="isan-right-table" class="table-compact-p mb-2">
        <colgroup>
          @for ($no = 2; $no <= 10; $no++)
            <col style="width:100px;">
          @endfor
        </colgroup>

        <thead>

          {{-- 相続人氏名 --}}
          <tr class="bg-blue isan-heir-name-row">
            @for ($no = 2; $no <= 10; $no++)
              <th class="text-center" data-heir-name-header="{{ $no }}">{{ $heirNames[$no] ?? '' }}</th>
            @endfor
          </tr>
          {{-- 相続人続柄 --}}
          <tr class="bg-blue isan-heir-rel-row">
            @for ($no = 2; $no <= 10; $no++)
              <th class="text-center" data-heir-rel-header="{{ $no }}">{{ $heirRels[$no] ?? '' }}</th>
            @endfor
          </tr>
        </thead>

         <tbody>
                   
          <?php $i = 1; ?>
          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php $hasHeirName = trim((string)($heirNames[$no] ?? '')) !== ''; @endphp
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0 isan-field-calc"
                       name="id_cash_share[{{ $no }}]"
                       data-heir-no="{{ $no }}"
                       data-has-name="{{ $hasHeirName ? '1' : '0' }}"
                       readonly
                       tabindex="-1"
                       @disabled(!$hasHeirName)
                       value="{{ $hasHeirName
                         ? old('id_cash_share.'.$no,
                             (isset($prefillInheritance['members'][$no]['cash_share']) && $prefillInheritance['members'][$no]['cash_share'] !== null)
                               ? number_format((int)$prefillInheritance['members'][$no]['cash_share'])
                               : '')
                         : '' }}">
              </td>
            @endfor
          </tr>

          <?php $i = 2; ?>
          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php $hasHeirName = trim((string)($heirNames[$no] ?? '')) !== ''; @endphp
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0 isan-field-calc"
                       name="id_other_share[{{ $no }}]"
                       data-heir-no="{{ $no }}"
                       data-has-name="{{ $hasHeirName ? '1' : '0' }}"
                       readonly
                       tabindex="-1"
                       @disabled(!$hasHeirName)
                       value="{{ $hasHeirName
                         ? old('id_other_share.'.$no,
                             isset($prefillInheritance['members'][$no]['other_share'])
                               ? number_format($prefillInheritance['members'][$no]['other_share'])
                               : '')
                         : '' }}">
              </td>
            @endfor
          </tr>

          <?php $i = 3; ?>
          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php $hasHeirName = trim((string)($heirNames[$no] ?? '')) !== ''; @endphp
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0 isan-field-calc"
                       name="id_taxable_manu[{{ $no }}]"
                       data-heir-no="{{ $no }}"
                       data-has-name="{{ $hasHeirName ? '1' : '0' }}"
                       readonly
                       tabindex="-1"
                       @disabled(!$hasHeirName)
                       style="background-color:#f0f0f0;"
                       value="{{ $hasHeirName
                       ? old('id_taxable_manu.'.$no, isset($prefillInheritance['members'][$no]['taxable_manu']) ? number_format($prefillInheritance['members'][$no]['taxable_manu']) : '')
                         : '' }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0"
                       style="background-color:#f0f0f0;"
                       readonly
                       tabindex="-1"
                       name="id_lifetime_gift_addition[{{ $no }}]"
                       data-role="lifetime_gift_addition"
                       data-heir-no="{{ $no }}"
                       value="{{ old('id_lifetime_gift_addition.'.$no, $heirsByIdx[$no]['lifetime_gift_addition'] ?? '') }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0"
                       style="background-color:#f0f0f0;"
                       readonly
                       tabindex="-1"
                       data-role="taxable_total_heir"
                       data-heir-no="{{ $no }}"
                       value="{{ number_format(
                          (int)preg_replace('/[^\d]/', '', ($prefillInheritance['members'][$no]['taxable_manu'] ?? 0)) +
                          (int)preg_replace('/[^\d]/', '', ($heirsByIdx[$no]['lifetime_gift_addition'] ?? 0))
                       ) }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" value=""></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0"
                       style="background-color:#f0f0f0;"
                       readonly
                       tabindex="-1"
                       data-role="taxable_estate_share"
                       data-heir-no="{{ $no }}"
                       value="{{ $taxableEstateShareKByHeir[$no] ? number_format($taxableEstateShareKByHeir[$no]) : '' }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php
                $bunsi = old(
                    "bunsi.$no",
                    $prefillFamily[$no]['bunsi']
                        ?? request()->input("bunsi.$no")
                        ?? null
                );
                $bunbo = old(
                    "bunbo.$no",
                    $prefillFamily[$no]['bunbo']
                        ?? request()->input("bunbo.$no")
                        ?? null
                );

                if (($bunsi === null || $bunsi === '') && ($bunbo === null || $bunbo === '')) {
                    $bunsi = $heirsByIdx[$no]['bunsi'] ?? null;
                    $bunbo = $heirsByIdx[$no]['bunbo'] ?? null;
                }

                $bunsi = ($bunsi === null || $bunsi === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunsi);
                $bunbo = ($bunbo === null || $bunbo === '') ? null : (int)preg_replace('/[^\d\-]/u', '', (string)$bunbo);

                $val = (isset($bunsi, $bunbo) && $bunsi >= 1 && $bunbo >= 1) ? ($bunsi . '/' . $bunbo) : '';
              @endphp
              <td>
                <input type="text"
                       class="form-control comma decimal0"
                       style="background-color:#f0f0f0; text-align:center !important;"
                       readonly
                       tabindex="-1"
                       data-role="legal_share_text"
                       data-heir-no="{{ $no }}"
                       value="{{ $val }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php $perHeirLegalTax = $heirsByIdx[$no]['legal_tax_yen'] ?? null; @endphp
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="legal_tax" data-heir-no="{{ $no }}" value="{{ isset($perHeirLegalTax) ? $toKyen($perHeirLegalTax) : '' }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php $ratio = (float)($heirsByIdx[$no]['anbun_ratio'] ?? 0.0); @endphp
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="anbun_ratio" data-heir-no="{{ $no }}" value="{{ $toFixed4($ratio) }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="sanzutsu_tax" data-heir-no="{{ $no }}" value="{{ isset($heirsByIdx[$no]['sanzutsu_tax_yen']) ? $toKyen($heirsByIdx[$no]['sanzutsu_tax_yen']) : '' }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php
                $inc = ((int)($heirsByIdx[$no]['final_tax_yen'] ?? 0)) - ((int)($heirsByIdx[$no]['sanzutsu_tax_yen'] ?? 0));
                $inc = $inc > 0 ? $inc : 0;
              @endphp
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="two_tenths_amount" data-heir-no="{{ $no }}" value="{{ $inc ? $toKyen($inc) : '' }}"></td>
            @endfor
          </tr>

          <tr>
          @for ($no = 2; $no <= 10; $no++)
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="gift_tax_credit_calendar" data-heir-no="{{ $no }}" value="{{ isset($heirsByIdx[$no]['gift_tax_credit_calendar_yen']) ? $toKyen($heirsByIdx[$no]['gift_tax_credit_calendar_yen']) : '' }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="spouse_relief" data-heir-no="{{ $no }}" value="{{ isset($heirsByIdx[$no]['spouse_relief_yen']) ? $toKyen($heirsByIdx[$no]['spouse_relief_yen']) : '' }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php $hasHeirName = trim((string)($heirNames[$no] ?? '')) !== ''; @endphp
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0 a isan-field-input"
                       name="id_other_credit[{{ $no }}]"
                       data-heir-no="{{ $no }}"
                       data-has-name="{{ $hasHeirName ? '1' : '0' }}"
                       inputmode="numeric"
                       @disabled(!$hasHeirName)
                       value="{{ $hasHeirName
                         ? old('id_other_credit.'.$no, isset($prefillInheritance['other_credit'][$no]) ? number_format($prefillInheritance['other_credit'][$no]) : '')
                         : '' }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php
                $c_person = (int)($heirsByIdx[$no]['gift_tax_credit_calendar_yen'] ?? 0)
                          + (int)($heirsByIdx[$no]['spouse_relief_yen'] ?? 0)
                          + (int)($heirsByIdx[$no]['other_credit_yen'] ?? 0);
              @endphp
              <td>
                <input type="text"
                       class="form-control suji8 comma decimal0"
                       style="background-color:#f0f0f0;"
                       readonly
                       tabindex="-1"
                       data-role="credit_total"
                       data-heir-no="{{ $no }}"
                       value="{{ $c_person ? $toKyen($c_person) : '' }}">
              </td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="sashihiki_tax" data-heir-no="{{ $no }}" value="{{ isset($heirsByIdx[$no]['sashihiki_tax_yen']) ? $toKyen($heirsByIdx[$no]['sashihiki_tax_yen']) : '' }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="settlement_gift_tax" data-heir-no="{{ $no }}" value="{{ isset($heirsByIdx[$no]['settlement_gift_tax_yen']) ? $toKyen($heirsByIdx[$no]['settlement_gift_tax_yen']) : '' }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php
                $rawSubtotalYen = array_key_exists('raw_final_after_settlement_yen', $heirsByIdx[$no] ?? [])
                    ? (int)($heirsByIdx[$no]['raw_final_after_settlement_yen'] ?? 0)
                    : ((int)($heirsByIdx[$no]['sashihiki_tax_yen'] ?? 0) - (int)($heirsByIdx[$no]['settlement_gift_tax_yen'] ?? 0));
                $payableYen = max(0, $rawSubtotalYen);
              @endphp
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="payable_tax" data-heir-no="{{ $no }}" value="{{ $toKyen($payableYen) }}"></td>
            @endfor
          </tr>

          <tr>
            @for ($no = 2; $no <= 10; $no++)
              @php
                $rawSubtotalYen = array_key_exists('raw_final_after_settlement_yen', $heirsByIdx[$no] ?? [])
                    ? (int)($heirsByIdx[$no]['raw_final_after_settlement_yen'] ?? 0)
                    : ((int)($heirsByIdx[$no]['sashihiki_tax_yen'] ?? 0) - (int)($heirsByIdx[$no]['settlement_gift_tax_yen'] ?? 0));
                $refundYen = $rawSubtotalYen < 0 ? abs($rawSubtotalYen) : 0;
              @endphp
              <td><input type="text" class="form-control suji8 comma decimal0" style="background-color:#f0f0f0;" readonly tabindex="-1" data-role="refund_tax" data-heir-no="{{ $no }}" value="{{ $toKyen($refundYen) }}"></td>
            @endfor
          </tr>


        </tbody>
      </table>
    </div>

  </div>
</div>

      <br>
      <br>


<script>
  function syncIsanTableHeights() {
    const leftHeadRows  = Array.from(document.querySelectorAll('#isan-left-table thead tr'));
    const rightHeadRows = Array.from(document.querySelectorAll('#isan-right-table thead tr'));
    const leftBodyRows  = Array.from(document.querySelectorAll('#isan-left-table tbody tr'));
    const rightBodyRows = Array.from(document.querySelectorAll('#isan-right-table tbody tr'));
    const paytaxMergedCell = document.querySelector('#isan-left-table td.isan-subgroup-2rows');
 


    [...leftHeadRows, ...rightHeadRows, ...leftBodyRows, ...rightBodyRows].forEach((el) => {
      el.style.height = '';
    });
    if (paytaxMergedCell) {
      paytaxMergedCell.style.height = '';
    }    

    // 左ヘッダは固定値で運用する
    if (leftHeadRows.length === 1 && rightHeadRows.length >= 2) {
      leftHeadRows[0].style.height = '44px';
    }
    
    const bodyN = Math.min(leftBodyRows.length, rightBodyRows.length);
    for (let i = 0; i < bodyN; i++) {
      const h = Math.max(leftBodyRows[i].offsetHeight, rightBodyRows[i].offsetHeight);
      leftBodyRows[i].style.height = h + 'px';
      rightBodyRows[i].style.height = h + 'px';
    }


    // 「申告納税額」(rowspan=2) は、右側の No.19 / No.20 行の合計高さに合わせる
    if (paytaxMergedCell && leftBodyRows.length >= 20 && rightBodyRows.length >= 20) {
      const payableIdx = 18; // No.19
      const refundIdx  = 19; // No.20
      const mergedHeight =
        (rightBodyRows[payableIdx]?.offsetHeight || 0) +
        (rightBodyRows[refundIdx]?.offsetHeight || 0);

      if (mergedHeight > 0) {
        paytaxMergedCell.style.height = mergedHeight + 'px';
      }
    }

  }

  window.syncIsanTableHeights = syncIsanTableHeights;

  document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
      syncIsanTableHeights();
    });
  });
</script>



  <br>
</div>
</div>
</div>



<script>
  window.ISAN_RELATIONSHIPS = @json($relationships ?? [], JSON_UNESCAPED_UNICODE);

  function isanGetPane() {
    return document.getElementById('zouyo-tab-input05');
  }

  function isanGetSourceCandidates(name) {
    const pane = isanGetPane();
    const all = Array.from(document.querySelectorAll(`[name="${name}"]`));
    if (!pane) return all;

    const outside = all.filter((el) => !pane.contains(el));
    return outside.length ? outside : all;
  }

  function isanFindSourceField(name) {
    const candidates = isanGetSourceCandidates(name);
    if (!candidates.length) return null;

    return (
      candidates.find((el) => !(el instanceof HTMLInputElement && el.type === 'hidden')) ||
      candidates[0]
    );
  }

  function isanReadSourceValue(name, defaultValue = '') {
    const candidates = isanGetSourceCandidates(name);
    if (!candidates.length) return defaultValue;

    const first = candidates[0];
    if (first instanceof HTMLInputElement && first.type === 'radio') {
      const checked = candidates.find((el) => el.checked);
      return checked ? (checked.value ?? defaultValue) : defaultValue;
    }

    const el = isanFindSourceField(name);
    if (!el) return defaultValue;

    if (el instanceof HTMLInputElement) {
      if (el.type === 'checkbox') {
        return el.checked ? (el.value || '1') : '0';
      }
      return el.value ?? defaultValue;
    }

    if (el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement) {
      return el.value ?? defaultValue;
    }

    return defaultValue;
  }
  

  function isanReadRelationshipCodeForPreview(no) {
    const field = isanFindIndexedSourceField(no, /(relationship|zokugara|rel)/i);
    if (!field) return '';

    if (
      field instanceof HTMLSelectElement ||
      field instanceof HTMLInputElement ||
      field instanceof HTMLTextAreaElement
    ) {
      return String(field.value ?? '').trim();
    }

    return '';
  }

  function buildIsanPreviewFormDataFromCurrentForm() {
    const form = document.getElementById('zouyo-input-form');
    const pane = isanGetPane();
    const fd = new FormData();

    if (!form) return fd;

    const token =
      form.querySelector('input[name="_token"]')?.value ||
      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
      '';

    const dataId =
      form.querySelector('input[name="data_id"]')?.value ||
      (typeof window.Z_RESOLVED_DATA_ID !== 'undefined' ? window.Z_RESOLVED_DATA_ID : '') ||
      (typeof window.APP_DATA_ID !== 'undefined' ? window.APP_DATA_ID : '') ||
      '';

    if (token !== '') fd.set('_token', token);
    if (dataId !== '') fd.set('data_id', String(dataId));
    fd.set('active_tab', 'input05');

    // ▼ ヘッダ系（input02 の実入力を優先）
    const donorName = String(
      isanReadSourceValue('name[1]', '') || isanReadSourceValue('customer_name', '')
    ).trim();
    if (donorName !== '') {
      fd.set('customer_name', donorName);
    }

    ['header_year', 'header_month', 'header_day', 'per'].forEach((key) => {
      const v = String(isanReadSourceValue(key, '') ?? '').trim();
      if (v !== '') fd.set(key, v);
    });

    // ▼ 家族構成（input02 の現在値を使う）
    for (let no = 1; no <= 10; no++) {
      const name = String(isanReadSourceValue(`name[${no}]`, '') ?? '').trim();
      if (name !== '') fd.set(`name[${no}]`, name);

      const relCode = isanReadRelationshipCodeForPreview(no);
      if (relCode !== '') {
        fd.set(`relationship[${no}]`, relCode);
        fd.set(`relationship_code[${no}]`, relCode);
      }

      [
        'civil_share_bunsi',
        'civil_share_bunbo',
        'bunsi',
        'bunbo',
        'property',
        'cash',
        'birth_year',
        'birth_month',
        'birth_day',
        'age',
      ].forEach((key) => {
        const v = String(isanReadSourceValue(`${key}[${no}]`, '') ?? '').trim();
        if (v !== '') fd.set(`${key}[${no}]`, v);
      });

      ['twenty_percent_add', 'tokurei_zouyo'].forEach((key) => {
        const v = String(isanReadSourceValue(`${key}[${no}]`, '0') ?? '').trim();
        fd.set(`${key}[${no}]`, v === '' ? '0' : v);
      });
    }

    // ▼ 遺産分割等タブの現在値（input05 の値だけを使う）
    const mode = pane?.querySelector('input[name="input_mode"]:checked')?.value || 'auto';
    fd.set('input_mode', mode);

    for (let no = 2; no <= 10; no++) {
      [
        'id_taxable_manu',
        'id_cash_share',
        'id_other_share',
        'id_other_credit',
      ].forEach((key) => {
        const el = pane?.querySelector(`[name="${key}[${no}]"]`);
        if (!el) return;
        if (el.disabled) return;
        const v = String(el.value ?? '').trim();
        if (v !== '') fd.set(`${key}[${no}]`, v);
      });
    }

    return fd;
  }

  window.buildIsanPreviewFormDataFromCurrentForm = buildIsanPreviewFormDataFromCurrentForm;






  
  function isanMapRelationshipValue(raw) {
    const value = String(raw ?? '').trim();
    if (value === '') return '';

    const code = Number(String(value).replace(/[^\d\-]/g, ''));
    const map = window.ISAN_RELATIONSHIPS || {};

    if (!Number.isNaN(code) && Object.prototype.hasOwnProperty.call(map, code)) {
      return String(map[code] || '').trim();
    }

    return value;
  }

  function isanFindIndexedSourceField(no, tester) {
    const pane = isanGetPane();
    const all = Array.from(document.querySelectorAll('[name]')).filter((el) => {
      const fieldName = String(el.getAttribute('name') || '');
      const m = fieldName.match(/\[(\d+)\]$/);
      if (!m || Number(m[1]) !== Number(no)) return false;
      return tester.test(fieldName);
    });

    if (!all.length) return null;

    const outside = pane ? all.filter((el) => !pane.contains(el)) : all;
    const pool = outside.length ? outside : all;

    return pool.find((el) => !(el instanceof HTMLInputElement && el.type === 'hidden')) || pool[0] || null;
  }


  
  
  
  
  
  
  
  
  
  
  

  function isanResolveRelationshipLabel(no) {
    const name = String(isanReadSourceValue(`name[${no}]`, '')).trim();
    if (name === '') return '';

    const field =
      isanFindIndexedSourceField(no, /(relationship|zokugara|rel)/i) ||
      isanFindSourceField(`relationship_code[${no}]`);

    if (field instanceof HTMLSelectElement) {
      const value = String(field.value || '').trim();
      if (value === '') return '';
      const text = String(field.options[field.selectedIndex]?.textContent || '').trim();


      if (text !== '') return text;

      return isanMapRelationshipValue(value);

    }

    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
      const raw = String(field.value || '').trim();
      if (raw !== '') return isanMapRelationshipValue(raw);
    }

    return isanMapRelationshipValue(isanReadSourceValue(`relationship_code[${no}]`, ''));

   }
   
   
  function isanClearColumn(no) {
    const pane = isanGetPane() || document;
    const colIndex = no - 2; // 2→0, 10→8

    pane.querySelectorAll('#isan-right-table tbody tr').forEach((tr) => {
      const td = tr.children[colIndex];
      if (!td) return;

      td.querySelectorAll('input[type="text"], textarea').forEach((el) => {
        el.value = '';
      });
    });
  }

  function isanApplyHeirState(no, hasName) {
    const pane = isanGetPane() || document;
    const mode = document.querySelector('input[name="input_mode"]:checked')?.value || 'auto';
    const assetMode = document.querySelector('input[name="asset_input_mode"]:checked')?.value || 'split';

    const cashEl   = pane.querySelector(`input[name="id_cash_share[${no}]"]`);
    const otherEl  = pane.querySelector(`input[name="id_other_share[${no}]"]`);
    const totalEl  = pane.querySelector(`input[name="id_taxable_manu[${no}]"]`);
    const creditEl = pane.querySelector(`input[name="id_other_credit[${no}]"]`);

    [cashEl, otherEl, totalEl, creditEl].forEach((el) => {
      if (!el) return;
      el.dataset.hasName = hasName ? '1' : '0';
    });

    if (!hasName) {
      [cashEl, otherEl, totalEl, creditEl].forEach((el) => {
        if (!el) return;
        el.value = '';
        el.disabled = true;
        el.readOnly = true;
        el.style.backgroundColor = '#f0f0f0';
        setIsanVisualState(el, 'calc');
      });
      isanClearColumn(no);
      return;
    }

    if (cashEl) {
      if (assetMode === 'combined') {
        cashEl.value = '';
        cashEl.disabled = true;
        cashEl.readOnly = true;
        cashEl.tabIndex = -1;
        cashEl.style.backgroundColor = '#f0f0f0';
        setIsanVisualState(cashEl, 'calc');
      } else {
        cashEl.disabled = false;
        cashEl.readOnly = (mode !== 'manual');
        cashEl.style.backgroundColor = (mode === 'manual') ? '' : '#f0f0f0';
        setIsanVisualState(cashEl, (mode === 'manual') ? 'input' : 'calc');
      }
    }

    if (otherEl) {
      otherEl.disabled = false;
      otherEl.readOnly = (mode !== 'manual');
      otherEl.style.backgroundColor = (mode === 'manual') ? '' : '#f0f0f0';
      setIsanVisualState(otherEl, (mode === 'manual') ? 'input' : 'calc');
    }

    if (totalEl) {
      totalEl.disabled = false;
      totalEl.readOnly = true;
      totalEl.style.backgroundColor = '#f0f0f0';
      setIsanVisualState(totalEl, 'calc');
    }

    if (creditEl) {
      creditEl.disabled = false;
      creditEl.readOnly = false;
      creditEl.style.backgroundColor = '';
      setIsanVisualState(creditEl, 'input');
    }
  }

  function syncIsanFamilyHeaders() {
    const pane = isanGetPane() || document;

    // 被相続人名
    const donorEl = pane.querySelector('input[name="customer_name"]');
    if (donorEl) {
      const donorName = String(
        isanReadSourceValue('customer_name', '') || isanReadSourceValue('name[1]', '')
      ).trim();
      donorEl.value = donorName;
    }


    for (let no = 2; no <= 10; no++) {


      const name = String(isanReadSourceValue(`name[${no}]`, '')).trim();
      const rel  = isanResolveRelationshipLabel(no);

      const nameHeader = pane.querySelector(`#isan-right-table thead [data-heir-name-header="${no}"]`);
      const relHeader  = pane.querySelector(`#isan-right-table thead [data-heir-rel-header="${no}"]`);


      if (nameHeader) {
        nameHeader.textContent = name;
      }

      if (relHeader) {
        relHeader.textContent = name !== '' ? rel : '';
      }

      isanApplyHeirState(no, name !== '');
    }

    try {
      if (typeof updateLifetimeGiftAddition === 'function') updateLifetimeGiftAddition();
      if (typeof updateTaxablePriceTotal === 'function') updateTaxablePriceTotal();
    } catch (_) {}
    

    try {
      if (typeof window.applyIsanAssetInputMode === 'function') {
        window.applyIsanAssetInputMode();
      }
    } catch (_) {}    
    
  }

  window.syncIsanFamilyHeaders = syncIsanFamilyHeaders;

  let isanFamilySyncTimer = null;
  function queueIsanFamilySync() {
    if (isanFamilySyncTimer) {
      clearTimeout(isanFamilySyncTimer);
    }
    isanFamilySyncTimer = setTimeout(() => {
      isanFamilySyncTimer = null;
      syncIsanFamilyHeaders();
    }, 0);
  }

  function isIsanFamilySourceField(target) {
    if (!(target instanceof Element)) return false;

    const name = target.getAttribute('name') || '';
    if (name === 'customer_name') return true;

    const nameMatch = name.match(/^name\[(\d+)\]$/);
    if (nameMatch) {
      const no = Number(nameMatch[1]);
      return no >= 1 && no <= 10;
    }

    const relMatch = name.match(/^relationship_code\[(\d+)\]$/);
    if (relMatch) {
      const no = Number(relMatch[1]);
      return no >= 2 && no <= 10;
    }

    return false;
  }

  document.addEventListener('DOMContentLoaded', () => {
    queueIsanFamilySync();
  });

  document.addEventListener('input', (e) => {
    if (!isIsanFamilySourceField(e.target)) return;
    queueIsanFamilySync();
  }, true);

  document.addEventListener('change', (e) => {
    if (!isIsanFamilySourceField(e.target)) return;
    queueIsanFamilySync();
  }, true);

  document.addEventListener('shown.bs.tab', (e) => {
    const target =
      e.target?.getAttribute('data-bs-target') ||
      e.target?.getAttribute('href') ||
      '';

    if (target === '#zouyo-tab-input05') {
      queueIsanFamilySync();
    }
  });
</script>


<script>
  // 3桁区切りのカンマを付ける関数
  function formatNumber(input) {
    let raw = input.value.replace(/,/g, '').replace(/[^\d]/g, '');
    if (raw === '') return;
    input.value = Number(raw).toLocaleString();
  }

  document.addEventListener('DOMContentLoaded', function () {
    // name属性が "rekinen_zoyo[" または "rekinen_kojo[" で始まるinputを取得
    const priceInputs = document.querySelectorAll('input[name^="rekinen_zoyo["], input[name^="rekinen_kojo["], input[name^="seisan_zoyo["], input[name^="seisan_kojo["]');

    priceInputs.forEach(function (input) {
      input.addEventListener('blur', function () {
        formatNumber(input);
      });
    });
  });
</script>


<script>
  document.addEventListener('DOMContentLoaded', function () {
    const percentInput = document.querySelector('input[name="per"]');

    if (percentInput) {
      percentInput.addEventListener('blur', function () {
        let value = percentInput.value.trim();

        // 数字以外の文字を排除
        value = value.replace(/[^\d.]/g, '');

        // 小数第一位までに丸める（例：12.345 → 12.3）
        let num = parseFloat(value);
        if (!isNaN(num)) {
          percentInput.value = num.toFixed(1); // 小数1桁に固定
        } else {
          percentInput.value = ''; // 数字じゃない場合は空に
        }
      });
    }
  });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const currentYear = new Date().getFullYear();
  const baseDate = new Date(`${currentYear}-01-01`);

  // 2〜10行目の相続人を対象にループ処理
  for (let i = 1; i <= 10; i++) {
    const yearInput = document.querySelector(`input[name="birth_year[${i}]"]`);
    const monthInput = document.querySelector(`input[name="birth_month[${i}]"]`);
    const dayInput = document.querySelector(`input[name="birth_day[${i}]"]`);
    const ageInput = document.querySelector(`input[name="age[${i}]"]`);

    if (yearInput && monthInput && dayInput && ageInput) {
      const calculateAge = () => {
        const y = parseInt(yearInput.value);
        const m = parseInt(monthInput.value);
        const d = parseInt(dayInput.value);

        if (!isNaN(y) && !isNaN(m) && !isNaN(d)) {
          const birthDate = new Date(y, m - 1, d); // JSは月が0始まり
          let age = baseDate.getFullYear() - birthDate.getFullYear();

          // 誕生日が1月1日以降なら、まだ年取ってない
          const birthdayThisYear = new Date(baseDate.getFullYear(), birthDate.getMonth(), birthDate.getDate());
          if (birthdayThisYear > baseDate) {
            age -= 1;
          }

          if (age >= 0 && age <= 130) {
            ageInput.value = age;
          } else {
            ageInput.value = ''; // 異常値は空白に
          }
        }
      };

      yearInput.addEventListener('blur', calculateAge);
      monthInput.addEventListener('blur', calculateAge);
      dayInput.addEventListener('blur', calculateAge);
    }
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function sanitizeNumber(str) {
    // カンマ除去＆数字以外除去
    return parseInt(str.replace(/,/g, '').replace(/[^\d]/g, '')) || 0;
  }

  function formatWithComma(num) {
    return num.toLocaleString();
  }

  function calculaterekinen_zoyoTotal() {
    let total = 0;
    for (let i = 1; i <= 10; i++) {
      const input = document.querySelector(`input[name="rekinen_zoyo[${i}]"]`);
      if (input) {
        total += sanitizeNumber(input.value);
      }
    }

    const totalInput = document.querySelector(`input[name="rekinen_zoyo[110]"]`);
    if (totalInput) {
      totalInput.value = formatWithComma(total);
    }
  }

  // 各 rekinen_zoyo[1〜10] に blur イベントを付ける
  for (let i = 1; i <= 10; i++) {
    const input = document.querySelector(`input[name="rekinen_zoyo[${i}]"]`);
    if (input) {
      input.addEventListener('blur', calculaterekinen_zoyoTotal);
    }
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function sanitizeNumber(str) {
    // カンマ除去＆数字以外除去
    return parseInt(str.replace(/,/g, '').replace(/[^\d]/g, '')) || 0;
  }

  function formatWithComma(num) {
    return num.toLocaleString();
  }

  function calculaterekinen_zoyoTotal() {
    let total = 0;
    for (let i = 1; i <= 10; i++) {
      const input = document.querySelector(`input[name="rekinen_kojo[${i}]"]`);
      if (input) {
        total += sanitizeNumber(input.value);
      }
    }

    const totalInput = document.querySelector(`input[name="rekinen_kojo[110]"]`);
    if (totalInput) {
      totalInput.value = formatWithComma(total);
    }
  }

  // 各 rekinen_zoyo[1〜10] に blur イベントを付ける
  for (let i = 1; i <= 10; i++) {
    const input = document.querySelector(`input[name="rekinen_kojo[${i}]"]`);
    if (input) {
      input.addEventListener('blur', calculaterekinen_zoyoTotal);
    }
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function sanitizeNumber(str) {
    // カンマ除去＆数字以外除去
    return parseInt(str.replace(/,/g, '').replace(/[^\d]/g, '')) || 0;
  }

  function formatWithComma(num) {
    return num.toLocaleString();
  }

  function calculateseisan_zoyoTotal() {
    let total = 0;
    for (let i = 1; i <= 10; i++) {
      const input = document.querySelector(`input[name="seisan_zoyo[${i}]"]`);
      if (input) {
        total += sanitizeNumber(input.value);
      }
    }

    const totalInput = document.querySelector(`input[name="seisan_zoyo[110]"]`);
    if (totalInput) {
      totalInput.value = formatWithComma(total);
    }
  }

  // 各 seisan_zoyo[1〜10] に blur イベントを付ける
  for (let i = 1; i <= 10; i++) {
    const input = document.querySelector(`input[name="seisan_zoyo[${i}]"]`);
    if (input) {
      input.addEventListener('blur', calculateseisan_zoyoTotal);
    }
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function sanitizeNumber(str) {
    // カンマ除去＆数字以外除去
    return parseInt(str.replace(/,/g, '').replace(/[^\d]/g, '')) || 0;
  }

  function formatWithComma(num) {
    return num.toLocaleString();
  }

  function calculateseisan_zoyoTotal() {
    let total = 0;
    for (let i = 1; i <= 10; i++) {
      const input = document.querySelector(`input[name="seisan_kojo[${i}]"]`);
      if (input) {
        total += sanitizeNumber(input.value);
      }
    }

    const totalInput = document.querySelector(`input[name="seisan_kojo[110]"]`);
    if (totalInput) {
      totalInput.value = formatWithComma(total);
    }
  }

  // 各 seisan_zoyo[1〜10] に blur イベントを付ける
  for (let i = 1; i <= 10; i++) {
    const input = document.querySelector(`input[name="seisan_kojo[${i}]"]`);
    if (input) {
      input.addEventListener('blur', calculateseisan_zoyoTotal);
    }
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

  function sanitizeNumber(str) {
    return parseInt(String(str || '').replace(/,/g, '').replace(/[^\d\-]/g, ''), 10) || 0;
  }

  function getBasicDeductionMasterKyen() {
    const baseEl = document.getElementById('basic_deduction_base_kyen');
    const perEl  = document.getElementById('basic_deduction_per_heir_kyen');

    return {
      baseKyen: sanitizeNumber(baseEl ? baseEl.value : 30000) || 30000,
      perHeirKyen: sanitizeNumber(perEl ? perEl.value : 6000) || 6000,
    };
  }

  function setBasicDeductionMasterKyen(baseKyen, perHeirKyen) {
    const baseEl = document.getElementById('basic_deduction_base_kyen');
    const perEl  = document.getElementById('basic_deduction_per_heir_kyen');

    if (baseEl && baseKyen !== null && baseKyen !== undefined && baseKyen !== '') {
      baseEl.value = sanitizeNumber(baseKyen);
    }
    if (perEl && perHeirKyen !== null && perHeirKyen !== undefined && perHeirKyen !== '') {
      perEl.value = sanitizeNumber(perHeirKyen);
    }
  }

  window.setBasicDeductionMasterKyen = setBasicDeductionMasterKyen;

  function countHouteiByBunsi() {
    let count = 0;

    for (let i = 2; i <= 10; i++) {
      const bunsiInput = document.querySelector(`input[name="bunsi[${i}]"]`);
      const bunboInput = document.querySelector(`input[name="bunbo[${i}]"]`);      
      if (bunsiInput) {
        const bunsi = parseInt((bunsiInput.value || '').replace(/[^\d]/g, ''), 10);
        const bunbo = parseInt((bunboInput ? bunboInput.value : '').replace(/[^\d]/g, ''), 10);
        if (!isNaN(bunsi) && bunsi >= 1 && !isNaN(bunbo) && bunbo >= 1) {
          count++;
        }
      }
    }

    const targetInput = document.querySelector('input[name="houtei_ninzu"]');
    if (targetInput) {
      targetInput.value = count;
    }
    
    // 基礎控除額表示も同時に更新
    const master = getBasicDeductionMasterKyen();
    const baseKyen = master.baseKyen;
    const perHeirKyen = master.perHeirKyen;
    
    const label = document.getElementById('basic-deduction-label');
    if (label) {

      label.textContent = `基礎控除額　${baseKyen.toLocaleString()}千円＋${perHeirKyen.toLocaleString()}千円×${count}人`;

    }

    const amountInput = document.getElementById('basic_deduction_amount');
    if (amountInput) {
      const amount = baseKyen + (perHeirKyen * count); // 単位：千円      
      amountInput.value = amount.toLocaleString();
    }


  }

  // 各 bunsi/bunbo に blur イベントを付与  
   for (let i = 2; i <= 10; i++) {
    const bunsiInput = document.querySelector(`input[name="bunsi[${i}]"]`);
    const bunboInput = document.querySelector(`input[name="bunbo[${i}]"]`);
    if (bunsiInput) {
      bunsiInput.addEventListener('blur', countHouteiByBunsi);
    }
    if (bunboInput) {
      bunboInput.addEventListener('blur', countHouteiByBunsi);
     }
   }

  // 初期表示時にも同期
  countHouteiByBunsi();  

});
</script>


<script>
(() => {
  if (window.__isanEnterMoveBound === true) return;
  window.__isanEnterMoveBound = true;

  const isanEnterSelector = [
    'input[name="input_mode"]',
    'input[name^="id_cash_share["]',
    'input[name^="id_other_share["]',
    'input[name^="id_other_credit["]'
  ].join(', ');

  function getIsanPane() {
    return document.getElementById('zouyo-tab-input05');
  }

  function isInsideIsanPane(el) {
    const pane = getIsanPane();
    if (!pane) return true;
    return pane.contains(el);
  }

  function isIsanEnterTarget(el) {
    if (!(el instanceof HTMLElement)) return false;
    if (!el.matches(isanEnterSelector)) return false;
    if (!isInsideIsanPane(el)) return false;

    if (el instanceof HTMLInputElement && el.type === 'hidden') return false;
    if ('disabled' in el && el.disabled) return false;
    if ('readOnly' in el && el.readOnly) return false;
    if (el.tabIndex === -1) return false;

    return true;
  }

  function getIsanFocusable() {
    const pane = getIsanPane();
    const root = pane || document;

    return Array.from(root.querySelectorAll(isanEnterSelector))
      .filter((el) => isIsanEnterTarget(el));
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.isComposing) return;

    const target = e.target;
    if (!isIsanEnterTarget(target)) return;

    // submit（＝計算開始）を止める
    e.preventDefault();
    e.stopPropagation();

    const focusable = getIsanFocusable();
    const currentIndex = focusable.indexOf(target);
    if (currentIndex === -1) return;

    const next = focusable[currentIndex + 1];
    if (next) {
      next.focus();

      if (
        next instanceof HTMLInputElement &&
        next.type !== 'radio' &&
        typeof next.select === 'function'
      ) {
        next.select();
      }
      return;
    }

    // 最終項目では submit させず blur のみ
    if (target instanceof HTMLElement && typeof target.blur === 'function') {
      target.blur();
    }
  }, true);
})();
</script>




@verbatim
<!-- ★ 所有財産：入力モード（法定相続割合 / 手入力）切替 -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modeRadios    = document.querySelectorAll('input[name="input_mode"]');
  const assetModeRadios = document.querySelectorAll('input[name="asset_input_mode"]');  
  const manualInputs  = document.querySelectorAll('input[name^="id_taxable_manu["]');
  const totalInput    = document.getElementById('id_taxable_manu_total'); // 所有財産合計(被相続人)・千円
  const cashTotal     = document.getElementById('id_cash_total');         // ★金融資産 合計（千円）
  const otherTotal    = document.getElementById('id_other_total');        // ★その他資産 合計（千円）

  // ★ 手入力時でも左側合計は被相続人(家族構成等)の固定値を維持する
  const fixedCashTotalKyen  = cashTotal  ? (cashTotal.value  || '') : '';
  const fixedOtherTotalKyen = otherTotal ? (otherTotal.value || '') : '';
  const fixedPropertyKyen   = totalInput ? (totalInput.value || '') : '';

  // ★ 追加：金融資産/その他資産（相続人別）入力欄
  const cashShareInputs  = document.querySelectorAll('input[name^="id_cash_share["]');
  const otherShareInputs = document.querySelectorAll('input[name^="id_other_share["]');

  // 手入力の一時保管用（相続人ごと）
  const manualStore = {};
  // ★ split行の手入力退避（金融/その他）
  const cashStore  = {};
  const otherStore = {};

  // 現在のモードを保持（auto / manual）
  let currentMode = 'auto';
  let currentAssetInputMode = (() => {
    const checked = document.querySelector('input[name="asset_input_mode"]:checked');
    return checked ? checked.value : 'split';
  })();

  function getCurrentAssetInputMode() {
    const checked = document.querySelector('input[name="asset_input_mode"]:checked');
    return checked ? checked.value : 'split';
  }  

  function hasHeirNameInput(el) {
    return String(el?.dataset?.hasName || '') === '1';
  }

  function hasHeirNameByNo(no) {
    const el =
      document.querySelector(`input[name="id_taxable_manu[${no}]"]`) ||
      document.querySelector(`input[name="id_cash_share[${no}]"]`) ||
      document.querySelector(`input[name="id_other_share[${no}]"]`) ||
      document.querySelector(`input[name="id_other_credit[${no}]"]`);
    return hasHeirNameInput(el);
  }

  function disableInput(el) {
    if (!el) return;
    el.setAttribute('disabled', 'disabled');
    el.setAttribute('readonly', 'readonly');
    el.setAttribute('tabindex', '-1');    
    el.style.backgroundColor = '#f0f0f0';
    setIsanVisualState(el, 'calc');
    el.value = '';
  }

  function enableReadonlyInput(el) {
    if (!el) return;
    el.removeAttribute('disabled');
    el.setAttribute('readonly', 'readonly');
    el.setAttribute('tabindex', '-1');    
    el.style.backgroundColor = '#f0f0f0';
    setIsanVisualState(el, 'calc');    
  }

  function enableEditableInput(el) {
    if (!el) return;
    el.removeAttribute('disabled');
    el.removeAttribute('readonly');
    el.removeAttribute('tabindex');    
    el.style.backgroundColor = '';
    setIsanVisualState(el, 'input');    
  }



  // ★ 初期表示時：Blade から描画された値（＝DBの taxable_manu）を manualStore に取り込んでおく
  //   - これにより、ページ再読込後でも「手入力」へ戻したときに DB 保存済みの値を復元できる
  manualInputs.forEach(function (el) {
    const m = el.name.match(/^id_taxable_manu\[(\d+)\]/);
    if (!m) return;
    const heirNo = m[1];
    const v = (el.value || '').trim();
    if (v !== '') {
      manualStore[heirNo] = v;  // カンマ付き文字列のまま保持しておけばそのまま表示に使える
    }
  });

  // ★ 初期表示時：split（金融/その他）の現在値を退避（DB値 or 旧入力）
  function snapshotSplitStoresFromDom() {
    cashShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_cash_share\[(\d+)\]/);
      if (!m) return;
      const no = m[1];
      const v = (el.value || '').trim();
      if (v !== '') cashStore[no] = v;
    });
    otherShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_other_share\[(\d+)\]/);
      if (!m) return;
      const no = m[1];
      const v = (el.value || '').trim();
      if (v !== '') otherStore[no] = v;
    });
  }

  function syncIsanManualStoresFromDom() {
    manualInputs.forEach(function (el) {
      const m = el.name.match(/^id_taxable_manu\[(\d+)\]/);
      if (!m) return;
      manualStore[m[1]] = el.value || '';
    });

    cashShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_cash_share\[(\d+)\]/);
      if (!m) return;
      cashStore[m[1]] = (currentAssetInputMode === 'combined') ? '' : (el.value || '');      
    });

    otherShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_other_share\[(\d+)\]/);
      if (!m) return;
      otherStore[m[1]] = el.value || '';
    });
  }

  window.z_syncIsanManualStores = syncIsanManualStoresFromDom;

  snapshotSplitStoresFromDom();
  syncIsanManualStoresFromDom();




  function sanitizeInt(str) {
    if (!str) return 0;
    return parseInt(String(str).replace(/,/g, '').replace(/[^\d\-]/g, ''), 10) || 0;
  }

  function formatComma(n) {
    const v = parseInt(n, 10) || 0;
    return v ? v.toLocaleString() : '';
  }

  function setIsanVisualState(el, state) {
    if (!el) return;
    el.classList.remove('isan-field-input', 'isan-field-ref', 'isan-field-calc');

    if (state === 'input') {
      el.classList.add('isan-field-input');
      return;
    }
    if (state === 'ref') {
      el.classList.add('isan-field-ref');
      return;
    }
    el.classList.add('isan-field-calc');
  }

  function disableEditableCalcInput(el) {
    if (!el) return;
    el.setAttribute('disabled', 'disabled');
    el.setAttribute('readonly', 'readonly');
    el.setAttribute('tabindex', '-1');    
    el.style.backgroundColor = '#f0f0f0';
    setIsanVisualState(el, 'calc');
    el.value = '';
  }

  function enableReadonlyCalcInput(el) {
    if (!el) return;
    el.removeAttribute('disabled');
    el.setAttribute('readonly', 'readonly');
    el.setAttribute('tabindex', '-1');
    el.style.backgroundColor = '#f0f0f0';
    setIsanVisualState(el, 'calc');
  }

  // 入力中の値を常に manualStore に反映（手入力モードのときだけ）
  manualInputs.forEach(function (el) {
    const m = el.name.match(/^id_taxable_manu\[(\d+)\]/);
    if (!m) return;
    const heirNo = m[1];

    el.addEventListener('input', function () {
      if (currentMode === 'manual') {
        manualStore[heirNo] = el.value || '';
      }
    });
  });


  /**
   * ★手入力モード：金融/その他（相続人別）の入力から、
   *  - 各人の所有財産（合計） id_taxable_manu[no] = cash_share + other_share
   *  - 左側の合計 cashTotal / otherTotal / totalInput
   * を自動更新する。
   *
   * ※ auto モードでは「totalInput + 法定割合」が SoT なので実行しない。
   */
  function recomputeFromSplitInputsIfManual() {
    if (currentMode !== 'manual') return;


    const isCombined = currentAssetInputMode === 'combined';    

    let sumCash  = 0;
    let sumOther = 0;

    for (let no = 2; no <= 10; no++) {
      const cashEl  = document.querySelector(`input[name="id_cash_share[${no}]"]`);
      const otherEl = document.querySelector(`input[name="id_other_share[${no}]"]`);
      const ownEl   = document.querySelector(`input[name="id_taxable_manu[${no}]"]`);

      if (!hasHeirNameByNo(no)) {
        if (cashEl)  cashEl.value  = '';
        if (otherEl) otherEl.value = '';
        if (ownEl)   ownEl.value   = '';
        continue;
      }
      

      if (isCombined && cashEl) {
        cashEl.value = '';
        cashStore[String(no)] = '';
      }      

      const cash  = isCombined ? 0 : sanitizeInt(cashEl ? cashEl.value : '');      
      const other = sanitizeInt(otherEl ? otherEl.value : '');
      const own   = cash + other;

      sumCash  += cash;
      sumOther += other;

      // 各人：所有財産（合計）を更新（編集可能のまま）
      if (ownEl) {
        ownEl.value = formatComma(own);
        manualStore[String(no)] = ownEl.value; // 手入力復元用にも反映
      }
    }

    
    // ★ 左側合計は手入力値では再計算せず、被相続人の固定値を維持する
    if (cashTotal)  cashTotal.value  = isCombined ? '' : fixedCashTotalKyen;
    if (otherTotal) otherTotal.value = isCombined ? fixedPropertyKyen : fixedOtherTotalKyen;
    if (totalInput) totalInput.value = fixedPropertyKyen;
 

    // 課税価格合計（所有財産＋生前贈与加算）も再計算
    // ※ base は property[1]（固定値）を使う    
    if (typeof updateTaxablePriceTotal === 'function') {
      updateTaxablePriceTotal();
    }
  }

  // ★手入力モード：金融/その他の入力確定（blur）で再計算
  cashShareInputs.forEach(function (el) {
    el.addEventListener('blur', function () {
      // blur時はカンマ整形もしてから再計算
      const v = sanitizeInt(el.value);
      el.value = formatComma(v);
      const m = el.name.match(/^id_cash_share\[(\d+)\]/);
      if (m) cashStore[m[1]] = el.value || '';
      recomputeFromSplitInputsIfManual();
    });
  });
  otherShareInputs.forEach(function (el) {
    el.addEventListener('blur', function () {
      const v = sanitizeInt(el.value);
      el.value = formatComma(v);
      const m = el.name.match(/^id_other_share\[(\d+)\]/);
      if (m) otherStore[m[1]] = el.value || '';
      recomputeFromSplitInputsIfManual();
    });
  });


  // ★ split入力中の値を常に store に反映（手入力モードのときだけ）
  cashShareInputs.forEach(function (el) {
    const m = el.name.match(/^id_cash_share\[(\d+)\]/);
    if (!m) return;
    const no = m[1];
    el.addEventListener('input', function () {
      if (currentMode === 'manual') cashStore[no] = el.value || '';
    });
  });
  otherShareInputs.forEach(function (el) {
    const m = el.name.match(/^id_other_share\[(\d+)\]/);
    if (!m) return;
    const no = m[1];
    el.addEventListener('input', function () {
      if (currentMode === 'manual') otherStore[no] = el.value || '';
    });
  });

  // 民法上の法定相続割合（civil_share_bunsi/bunbo）から各人の所有財産（千円）を計算
  function computeAutoShares() {
    const shares = {};

    const totalKyen = totalInput ? sanitizeInt(totalInput.value) : 0;
    if (!totalKyen) {
      // 合計がゼロなら全員ゼロのまま
      for (let no = 2; no <= 10; no++) {
        shares[no] = 0;
      }
      return shares;
    }

    let sumShares = 0;
    let lastHeirWithShare = null;

    for (let no = 2; no <= 10; no++) {
    
      if (!hasHeirNameByNo(no)) {
        shares[no] = 0;
        continue;
      }    

      const bunsiInput = document.querySelector(`input[name="civil_share_bunsi[${no}]"]`);
      const bunboInput = document.querySelector(`input[name="civil_share_bunbo[${no}]"]`);

      const bunsi = sanitizeInt(bunsiInput ? bunsiInput.value : '');
      const bunbo = sanitizeInt(bunboInput ? bunboInput.value : '');

      let share = 0;
      if (bunsi > 0 && bunbo > 0) {
        // 民法上の法定相続割合：total × (bunsi / bunbo)
        share = Math.round(totalKyen * (bunsi / bunbo));
        if (share > 0) {
          lastHeirWithShare = no;
        }
      }

      shares[no] = share;
      sumShares += share;
    }

    // 丸め誤差が出た場合は、最後の相続人に差分を寄せて合計を合わせる
    const diff = totalKyen - sumShares;
    if (diff !== 0 && lastHeirWithShare !== null) {
      shares[lastHeirWithShare] = (shares[lastHeirWithShare] || 0) + diff;
    }

    return shares;
  }



  // ★比率（手入力＝id_taxable_manu、法定＝civil_share）で total を按分
  function allocateByMode(totalKyen) {
    const out = {};
    if (!totalKyen) {
      for (let no = 2; no <= 10; no++) out[no] = 0;
      return out;
    }

    const mode = currentMode; // 'auto' or 'manual'
    let weights = {};
    let sumW = 0;
    let lastNo = null;

    for (let no = 2; no <= 10; no++) {
      let w = 0;
      
      if (!hasHeirNameByNo(no)) {
        weights[no] = 0;
        continue;
      }      
      
      if (mode === 'auto') {
        const bunsiInput = document.querySelector(`input[name="civil_share_bunsi[${no}]"]`);
        const bunboInput = document.querySelector(`input[name="civil_share_bunbo[${no}]"]`);
        const bunsi = sanitizeInt(bunsiInput ? bunsiInput.value : '');
        const bunbo = sanitizeInt(bunboInput ? bunboInput.value : '');
        if (bunsi > 0 && bunbo > 0) w = bunsi / bunbo;
      } else {
        const ownInput = document.querySelector(`input[name="id_taxable_manu[${no}]"]`);
        const v = sanitizeInt(ownInput ? ownInput.value : '');
        if (v > 0) w = v; // 手入力は金額比率
      }
      weights[no] = w;
      if (w > 0) { sumW += w; lastNo = no; }
    }

    if (!sumW || lastNo === null) {
      for (let no = 2; no <= 10; no++) out[no] = 0;
      return out;
    }

    let allocated = 0;
    for (let no = 2; no <= 10; no++) {
      const w = weights[no] || 0;
      if (w <= 0) { out[no] = 0; continue; }
      const share = Math.floor(totalKyen * (w / sumW));
      out[no] = share;
      allocated += share;
    }
    // 端数寄せ
    out[lastNo] = (out[lastNo] || 0) + (totalKyen - allocated);
    return out;
  }

  // ★金融資産/その他資産を更新
  function updateAssetSplitRows() {
    const isCombined = currentAssetInputMode === 'combined';
    const cashK  = isCombined ? 0 : (cashTotal ? sanitizeInt(cashTotal.value) : 0);
    const othK   = isCombined
      ? (totalInput ? sanitizeInt(totalInput.value) : 0)
      : (otherTotal ? sanitizeInt(otherTotal.value) : 0);

    const cashShares  = allocateByMode(cashK);
    const otherShares = allocateByMode(othK);

    for (let no = 2; no <= 10; no++) {
      const cashCell  = document.querySelector(`input[name="id_cash_share[${no}]"]`);
      const othCell   = document.querySelector(`input[name="id_other_share[${no}]"]`);

      if (!hasHeirNameByNo(no)) {
        if (cashCell) cashCell.value = '';
        if (othCell)  othCell.value  = '';
        continue;
      }

      if (cashCell) {
        if (isCombined) {
          cashCell.value = '';
          cashStore[String(no)] = '';
        } else {
          cashCell.value = cashShares[no] ? cashShares[no].toLocaleString() : '';
        }
      }

      if (othCell)  othCell.value  = otherShares[no] ? otherShares[no].toLocaleString() : '';

    }
  }
  

  function applyIsanAssetInputMode() {
    currentAssetInputMode = getCurrentAssetInputMode();
    const isCombined = currentAssetInputMode === 'combined';

    if (cashTotal) {
      cashTotal.value = isCombined ? '' : fixedCashTotalKyen;
      cashTotal.setAttribute('readonly', 'readonly');
      cashTotal.setAttribute('tabindex', '-1');
      cashTotal.style.backgroundColor = '#f0f0f0';
      setIsanVisualState(cashTotal, 'calc');
    }

    if (otherTotal) {
      otherTotal.value = isCombined ? fixedPropertyKyen : fixedOtherTotalKyen;
      otherTotal.setAttribute('readonly', 'readonly');
      otherTotal.setAttribute('tabindex', '-1');
      otherTotal.style.backgroundColor = '#f0f0f0';
      setIsanVisualState(otherTotal, 'calc');
    }

    if (totalInput) {
      totalInput.value = fixedPropertyKyen;
      totalInput.setAttribute('readonly', 'readonly');
      totalInput.setAttribute('tabindex', '-1');
      totalInput.style.backgroundColor = '#f0f0f0';
      setIsanVisualState(totalInput, 'calc');
    }

    cashShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_cash_share\[(\d+)\]/);
      if (!m) return;
      const no = m[1];

      if (!hasHeirNameInput(el)) {      
        disableEditableCalcInput(el);
        return;
      }



      if (isCombined) {
        cashStore[no] = '';
        el.value = '';
        disableEditableCalcInput(el);
        return;
      }


      if (currentMode === 'manual') {
        enableEditableInput(el);
        if (cashStore.hasOwnProperty(no)) {
          el.value = cashStore[no];
        }
      } else {
        enableReadonlyInput(el);
      }
    });

    otherShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_other_share\[(\d+)\]/);
      if (!m) return;
      const no = m[1];

      if (!hasHeirNameInput(el)) {
        disableEditableCalcInput(el);
        return;
      }

      if (currentMode === 'manual') {
        enableEditableInput(el);
        if (otherStore.hasOwnProperty(no)) {
          el.value = otherStore[no];
        }
      } else {
        enableReadonlyInput(el);
      }
    });

    if (currentMode === 'auto') {
      updateAssetSplitRows();
    } else {
      recomputeFromSplitInputsIfManual();
    }

    if (typeof updateTaxablePriceTotal === 'function') {
      updateTaxablePriceTotal();
    }
  }

  window.applyIsanAssetInputMode = applyIsanAssetInputMode;



  // ★ split行の readonly / editable 切替
  function applySplitEditable(isManual) {

    cashShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_cash_share\[(\d+)\]/);
      if (!m) return;
      const no = m[1];

      if (!hasHeirNameInput(el)) {
        disableEditableCalcInput(el);
        return;
      }

      if (currentAssetInputMode === 'combined') {
        cashStore[no] = '';
        el.value = '';
        disableEditableCalcInput(el);
        return;
      }

      if (isManual) {
        enableEditableInput(el);
      } else {
        enableReadonlyInput(el);
      }

      if (isManual) {
        if (cashStore.hasOwnProperty(no)) el.value = cashStore[no];
      }
    });

    otherShareInputs.forEach(function (el) {
      const m = el.name.match(/^id_other_share\[(\d+)\]/);
      if (!m) return;
      const no = m[1];
      
      if (!hasHeirNameInput(el)) {
        disableInput(el);
        return;
      }

      if (isManual) {
        enableEditableInput(el);
      } else {
        enableReadonlyInput(el);
      }
      
      if (isManual) {
        if (otherStore.hasOwnProperty(no)) el.value = otherStore[no];
      }
    });
  }


  function applyInputMode(mode) {
    const isAuto = (mode === 'auto');

    manualInputs.forEach(function (el) {
      const m = el.name.match(/^id_taxable_manu\[(\d+)\]/);
      if (!m) return;
      const heirNo = m[1];

      if (!hasHeirNameInput(el)) {
        disableInput(el);
        return;
      }


      // id_taxable_manu は常に「金融資産 + その他資産」の自動計算結果表示欄。
      // manual/auto を問わず常時 readonly とする。
      enableReadonlyCalcInput(el);

      if (isAuto) {
        // auto 時は法定按分結果を表示
        const shares = computeAutoShares();
        const shareKyen = shares[heirNo] || 0;
        el.value = shareKyen ? shareKyen.toLocaleString() : '';
      } else {
        // manual 時は split 入力から後段の recomputeFromSplitInputsIfManual() で再計算させる
        el.value = '';
      }


    });

    currentMode = mode;
    // ★ split行の入力可否を切替
    applySplitEditable(!isAuto);

    if (isAuto) {
      // ★ auto：保存済み手入力を温存しつつ、表示は法定按分（上書き・readonly）
      updateAssetSplitRows();
    } else {
      // ★ manual：退避値で復元済み（applySplitEditable内）
      // ★ manual：金融/その他の入力から所有財産合計＆左側合計を同期
      recomputeFromSplitInputsIfManual();
    }

    applyIsanAssetInputMode();

  }

  // ★ manual → auto に切り替えるとき：
  //    1) まず現在の手入力値を saveInheritance に保存
  //    2) 保存成功後にだけ auto 表示へ切り替え
  //    3) その値で相続税 preview を再計算
  async function autosaveManual() {
    const form = document.getElementById('zouyo-input-form') || document.querySelector('form');
    if (!form) {
      throw new Error('zouyo-input-form not found');
    }

    const saveUrl = form.dataset.saveInheritanceUrl || form.action;
    const formData = new FormData(form);

    // ★ ここでは「手入力モードとして」保存したいので、input_mode=manual を強制
    formData.set('input_mode', 'manual');
    formData.set('autosave', '1');
    formData.set('active_tab', 'input05');

    // CSRF トークン取得（hidden _token または meta のどちらか）
    const tokenInput = document.querySelector('input[name="_token"]');
    const metaToken  = document.querySelector('meta[name="csrf-token"]');
    const csrfToken  = tokenInput ? tokenInput.value : (metaToken ? metaToken.getAttribute('content') : '');

    const res = await fetch(saveUrl, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: formData,
      credentials: 'include',
    });

    if (!res.ok) {
      const text = await res.text().catch(() => '');
      throw new Error(`HTTP ${res.status} ${text}`);
    }

    return await res.json().catch(function () { return {}; });
  }



  function queueIsanPreviewRecalc() {
    if (typeof window.requestIsanBeforePreviewFromCurrentForm !== 'function') {
      return;
    }
    requestAnimationFrame(() => {
      try {
        window.requestIsanBeforePreviewFromCurrentForm();
      } catch (err) {
        console.error('[isan-preview][mode-change] failed', err);
      }
    });
  }


  // ★注意：FormData(form) は readonly input も送信するため、
  //   split行の hidden 追加は不要（同名要素が増えると後勝ちで値が潰れる原因になる）


  // 初期状態（サーバーサイドで @checked 済みのラジオから判定）
  let initialMode = 'auto';
  modeRadios.forEach(function (r) {
    if (r.checked) {
      initialMode = r.value;
    }
  });
  
  currentMode = initialMode;
  applyInputMode(initialMode);
  applyIsanAssetInputMode();  
  
  document.querySelectorAll('input[name^="id_other_credit["]').forEach(function (el) {
    if (!hasHeirNameInput(el)) {
      disableInput(el);
    }
  });

  
  // ★初期表示：autoなら法定按分表示、manualなら入力可能（退避値復元）
  if (currentMode === 'auto') {
    updateAssetSplitRows();
  } else {
    // manual 初期表示時も同期
    recomputeFromSplitInputsIfManual();
  }
  applyIsanAssetInputMode();


    // ★ 見た目の最終同期
    
    const isCombinedAssetMode = currentAssetInputMode === 'combined';    
    for (let no = 2; no <= 10; no++) {
      const hasName = hasHeirNameByNo(no);
      const cashEl   = document.querySelector(`input[name="id_cash_share[${no}]"]`);
      const otherEl  = document.querySelector(`input[name="id_other_share[${no}]"]`);
      const totalEl  = document.querySelector(`input[name="id_taxable_manu[${no}]"]`);
      const creditEl = document.querySelector(`input[name="id_other_credit[${no}]"]`);

      if (!hasName) {
        [cashEl, otherEl, totalEl, creditEl].forEach(disableInput);
        continue;
      }

      if (isCombinedAssetMode) {
        disableEditableCalcInput(cashEl);
      } else {
        setIsanVisualState(cashEl, currentMode === 'manual' ? 'input' : 'calc');
      }
      setIsanVisualState(otherEl,  currentMode === 'manual' ? 'input' : 'calc');
      setIsanVisualState(totalEl,  'calc');
      setIsanVisualState(creditEl, 'input');
    }



  // ラジオボタン切替時にモードを反映
  modeRadios.forEach(function (r) {
    r.addEventListener('change', async function () {


      if (!r.checked) return;

      const nextMode = r.value; // 'auto' or 'manual'
      if (nextMode === currentMode) {
        return;
      }

      if (nextMode === 'auto') {

        // 手入力 → 法定相続割合：
        // 1) 現在の手入力値を manualStore / cashStore / otherStore に退避
        // 2) saveInheritance へ manual 値を保存完了
        // 3) auto 表示へ切り替え
        // 4) 法定相続割合で preview 再計算
        if (currentMode === 'manual') {

          // ★ split行の現在値も退避（念のため）
          cashShareInputs.forEach(function (el) {
            const m = el.name.match(/^id_cash_share\[(\d+)\]/);
            if (!m) return;
            cashStore[m[1]] = el.value || '';
          });
          otherShareInputs.forEach(function (el) {
            const m = el.name.match(/^id_other_share\[(\d+)\]/);
            if (!m) return;
            otherStore[m[1]] = el.value || '';
          });

          // ★ split行は FormData に含まれるため、hidden 追加は不要


          manualInputs.forEach(function (el) {
            const m = el.name.match(/^id_taxable_manu\[(\d+)\]/);
            if (!m) return;
            const heirNo = m[1];
            manualStore[heirNo] = el.value || '';
          });


          try {
            await autosaveManual();
          } catch (err) {

            console.error('[isan][autosaveManual] failed', err);

            // ★ 保存失敗時は切替を中止して manual に戻す
            r.checked = false;
            const manualRadio = document.querySelector('input[name="input_mode"][value="manual"]');
            if (manualRadio) manualRadio.checked = true;

            currentMode = 'manual';
            applyInputMode('manual');

            alert('手入力した金融資産・その他資産の保存に失敗したため、法定相続割合への切替を中止しました。');
            return;
          }

        }
        applyInputMode('auto');
        // 所有財産が変わるので課税価格合計も再計算
        if (typeof updateTaxablePriceTotal === 'function') {
          updateTaxablePriceTotal();
        }
        queueIsanPreviewRecalc();


      } else {

        // 法定相続割合 → 手入力：
        //  - 表示は manualStore / cashStore / otherStore に保持している
        //    「手入力で保存した値」を復元する
        applyInputMode('manual');

        // 手入力に切り替えた場合も、現在の所有財産に応じて課税価格合計を再計算
        if (typeof updateTaxablePriceTotal === 'function') {
          updateTaxablePriceTotal();
        }
        queueIsanPreviewRecalc();

           
      }

    });
  });

  assetModeRadios.forEach(function (r) {
    r.addEventListener('change', function () {
      currentAssetInputMode = getCurrentAssetInputMode();
      applyIsanAssetInputMode();

      if (typeof queueIsanPreviewRecalc === 'function') {
        queueIsanPreviewRecalc();
      }
    });
  });  
  
});
</script>


<script>
// 生前贈与加算の合計（ヘッダーの1マス）を計算
function updateLifetimeGiftAddition() {
  let lifetimeGiftTotal = 0;

  // 生前贈与加算（2〜10番目の相続人）
  const lifetimeGiftInputs = document.querySelectorAll('input[name^="id_lifetime_gift_addition["]');
  lifetimeGiftInputs.forEach(function (input) {
    const v = (input.value || '').replace(/,/g, '').replace(/[^\d\-]/g, '');
    lifetimeGiftTotal += parseInt(v || '0', 10);
  });

  // 生前贈与加算の合計値を反映（千円として表示）
  const lifetimeGiftTotalInput = document.querySelector('input[name="id_lifetime_gift_addition"]');
  if (lifetimeGiftTotalInput) {
    lifetimeGiftTotalInput.value = lifetimeGiftTotal.toLocaleString();
  }
}

// 課税価格合計（所有財産＋生前贈与加算）を再計算
function updateTaxablePriceTotal() {
  // 全体合計：property[1]（所有財産の合計・千円）＋ 生前贈与加算合計（千円）
  const baseInput   = document.querySelector('input[name="property[1]"]');
  const giftTotal   = document.querySelector('input[name="id_lifetime_gift_addition"]');
  const overallCell = document.querySelector('input[data-role="taxable_total_overall"]');

  if (baseInput && giftTotal && overallCell) {
    const base = parseInt((baseInput.value   || '').replace(/,/g, '').replace(/[^\d\-]/g, ''), 10) || 0;
    const gift = parseInt((giftTotal.value   || '').replace(/,/g, '').replace(/[^\d\-]/g, ''), 10) || 0;
    overallCell.value = (base + gift).toLocaleString();
  }

  // 各相続人：id_taxable_manu[no]（所有財産・千円）＋ id_lifetime_gift_addition[no]（生前贈与加算・千円）
  for (let no = 2; no <= 10; no++) {
    const ownInput  = document.querySelector(`input[name="id_taxable_manu[${no}]"]`);
    const giftInput = document.querySelector(`input[name="id_lifetime_gift_addition[${no}]"]`);
    const totalCell = document.querySelector(`input[data-role="taxable_total_heir"][data-heir-no="${no}"]`);

    if (!ownInput || !giftInput || !totalCell) continue;

    const own  = parseInt((ownInput.value  || '').replace(/,/g, '').replace(/[^\d\-]/g, ''), 10) || 0;
    const gift = parseInt((giftInput.value || '').replace(/,/g, '').replace(/[^\d\-]/g, ''), 10) || 0;

    totalCell.value = (own + gift).toLocaleString();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  // 初期表示時にも一度計算
  updateLifetimeGiftAddition();
  updateTaxablePriceTotal();
});
</script>


@endverbatim


<!-- ★ 遺産分割(現時点)：金額欄に3桁カンマを付ける -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // 金額入力対象（編集可能）…相続人別の所有財産・その他控除
  const moneySelectors = [
    'input[name^="id_other_credit["]'    // その他の税額控除
  ];

  // 入力中は数字とカンマ以外を除去（マイナス不要なら '-' を外す）
  const sanitizeTyping = (el) => {
    el.value = el.value.replace(/[^\d,]/g, '');
  };

  // フォーカスアウトで3桁カンマ整形
  const formatWithComma = (el) => {
    const raw = (el.value || '').replace(/,/g, '').replace(/[^\d]/g, '');
    el.value = raw === '' ? '' : Number(raw).toLocaleString();
  };

  // 初期値にもカンマを付ける
  const formatNow = (el) => {
    if (!el) return;
    if (el.value && !/,/.test(el.value)) formatWithComma(el);
  };

  moneySelectors.forEach(sel => {
    document.querySelectorAll(sel).forEach(el => {
      // 入力イベントで不正文字を抑制
      el.addEventListener('input', () => sanitizeTyping(el));
      // blurでカンマ付与
      el.addEventListener('blur',  () => formatWithComma(el));
      // 初期表示時にも整形
      formatNow(el);
    });
  });
});
</script>


<script>
  let isanPreviewRequestSeq = 0;

  function isanPreviewPane() {
    return document.getElementById('zouyo-tab-input05') || document;
  }

  function isanPreviewSetValue(selector, value) {
    const el = isanPreviewPane().querySelector(selector);
    if (!el) return;
    el.value = value ?? '';
  }

  function isanPreviewSetText(selector, value) {
    const el = isanPreviewPane().querySelector(selector);
    if (!el) return;
    el.textContent = value ?? '';
  }

  function applyIsanPreviewPayload(preview) {
    if (!preview) return;

    const pane = isanPreviewPane();
    const left = preview.left || {};
    const members = preview.members || {};
    const previewMode = String(preview.mode || '').toLowerCase();
    const currentInputMode =
      pane.querySelector('input[name="input_mode"]:checked')?.value ||
      previewMode ||
    'auto';
    const keepFixedLeftTotals = currentInputMode === 'manual';
     

    if (typeof window.setBasicDeductionMasterKyen === 'function') {
      window.setBasicDeductionMasterKyen(
        left.basic_deduction_base_kyen
          ?? left.basic_deduction_base_amount_kyen
          ?? null,
        left.basic_deduction_per_heir_kyen
          ?? left.basic_deduction_per_heir_amount_kyen
          ?? null
      );
    }
   
    isanPreviewSetValue('#isan-customer-name', left.customer_name ?? '');

    // ★ 手入力時は左側の金融資産/その他資産/所有財産(合計)を
    //    preview 応答でも上書きしない
    if (!keepFixedLeftTotals) {
      isanPreviewSetValue('#id_cash_total', left.cash_total ?? '');
      isanPreviewSetValue('#id_other_total', left.other_total ?? '');
      isanPreviewSetValue('#id_taxable_manu_total', left.property_total ?? '');
      isanPreviewSetValue('input[data-role="taxable_total_overall"]', left.taxable_total_overall ?? '');
    } else if (typeof updateTaxablePriceTotal === 'function') {
      updateTaxablePriceTotal();
    }

    isanPreviewSetText('#basic-deduction-label', left.basic_deduction_label ?? '');
    isanPreviewSetValue('#basic_deduction_amount', left.basic_deduction_amount ?? '');
    isanPreviewSetValue('#isan-total-taxable-estate', left.taxable_estate ?? '');
    isanPreviewSetValue('#isan-total-anbun-ratio', left.anbun_ratio_total ?? '');
    isanPreviewSetValue('#isan-total-sozoku-tax', left.sozoku_tax_total ?? '');
    isanPreviewSetValue('#isan-total-sanzutsu', left.sanzutsu_total ?? '');
    isanPreviewSetValue('#isan-total-two-tenths', left.two_tenths_total ?? '');
    isanPreviewSetValue('#isan-total-calendar-credit', left.gift_tax_credit_total ?? '');
    isanPreviewSetValue('#isan-total-spouse-relief', left.spouse_relief_total ?? '');
    isanPreviewSetValue('#isan-total-other-credit', left.other_credit_total ?? '');
    isanPreviewSetValue('#isan-total-credit', left.credit_total ?? '');
    isanPreviewSetValue('#isan-total-sashihiki', left.sashihiki_total ?? '');
    isanPreviewSetValue('#isan-total-settlement-credit', left.settlement_credit_total ?? '');
    isanPreviewSetValue('#isan-total-payable', left.payable_total ?? '');
    isanPreviewSetValue('#isan-total-refund', left.refund_total ?? '');

    Object.entries(members).forEach(([no, row]) => {
      isanPreviewSetValue(`input[name="id_cash_share[${no}]"]`, row.cash_share ?? '');
      isanPreviewSetValue(`input[name="id_other_share[${no}]"]`, row.other_share ?? '');
      isanPreviewSetValue(`input[name="id_taxable_manu[${no}]"]`, row.taxable_manu ?? '');
      isanPreviewSetValue(`input[name="id_other_credit[${no}]"]`, row.other_credit ?? '');
      isanPreviewSetValue(`input[name="id_lifetime_gift_addition[${no}]"]`, row.lifetime_gift_addition ?? '');
      isanPreviewSetValue(`input[data-role="taxable_total_heir"][data-heir-no="${no}"]`, row.taxable_total ?? '');
      isanPreviewSetValue(`input[data-role="taxable_estate_share"][data-heir-no="${no}"]`, row.taxable_estate_share ?? '');
      isanPreviewSetValue(`input[data-role="legal_share_text"][data-heir-no="${no}"]`, row.legal_share_text ?? '');
      isanPreviewSetValue(`input[data-role="legal_tax"][data-heir-no="${no}"]`, row.legal_tax ?? '');
      isanPreviewSetValue(`input[data-role="anbun_ratio"][data-heir-no="${no}"]`, row.anbun_ratio ?? '');
      isanPreviewSetValue(`input[data-role="sanzutsu_tax"][data-heir-no="${no}"]`, row.sanzutsu_tax ?? '');
      isanPreviewSetValue(`input[data-role="two_tenths_amount"][data-heir-no="${no}"]`, row.two_tenths_amount ?? '');
      isanPreviewSetValue(`input[data-role="gift_tax_credit_calendar"][data-heir-no="${no}"]`, row.gift_tax_credit_calendar ?? '');
      isanPreviewSetValue(`input[data-role="spouse_relief"][data-heir-no="${no}"]`, row.spouse_relief ?? '');
      isanPreviewSetValue(`input[data-role="credit_total"][data-heir-no="${no}"]`, row.credit_total ?? '');
      isanPreviewSetValue(`input[data-role="sashihiki_tax"][data-heir-no="${no}"]`, row.sashihiki_tax ?? '');
      isanPreviewSetValue(`input[data-role="settlement_gift_tax"][data-heir-no="${no}"]`, row.settlement_gift_tax ?? '');
      isanPreviewSetValue(`input[data-role="payable_tax"][data-heir-no="${no}"]`, row.payable_tax ?? '');
      isanPreviewSetValue(`input[data-role="refund_tax"][data-heir-no="${no}"]`, row.refund_tax ?? '');
    });

    try {
      // ★ auto preview で手入力ストアを上書きしない
      if (previewMode === 'manual' && typeof window.z_syncIsanManualStores === 'function') {
        window.z_syncIsanManualStores();
      }
    } catch (_) {}


    try {
      // ★ preview 応答で cash_share が再投入されても、
      //    「金融資産を分けずに入力する」時は必ず
      //    空欄・入力不可・グレーへ戻す
      if (typeof window.applyIsanAssetInputMode === 'function') {
        window.applyIsanAssetInputMode();
      }
    } catch (_) {}


    requestAnimationFrame(() => {
      try {
        if (typeof syncIsanTableHeights === 'function') {
          syncIsanTableHeights();
        }
      } catch (_) {}
    });
  }

  async function requestIsanBeforePreviewFromCurrentForm() {
    const form = document.getElementById('zouyo-input-form');
    if (!form) return;

    const token =
      form.querySelector('input[name="_token"]')?.value ||
      document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
      '';


    const fd =
      typeof window.buildIsanPreviewFormDataFromCurrentForm === 'function'
        ? window.buildIsanPreviewFormDataFromCurrentForm()
        : new FormData(form);


    const dataId = fd.get('data_id') || form.querySelector('input[name="data_id"]')?.value || '';
    if (!dataId) return;

    fd.set('data_id', String(dataId));
    fd.set('active_tab', 'input05');
    

    try {
      console.info('[isan-preview][request]', {
        data_id: fd.get('data_id'),
        input_mode: fd.get('input_mode'),
        bunsi_2: fd.get('bunsi[2]'),
        bunbo_2: fd.get('bunbo[2]'),
        bunsi_3: fd.get('bunsi[3]'),
        bunbo_3: fd.get('bunbo[3]'),
        customer_name: fd.get('customer_name'),
      });
    } catch (_) {}    
    

    const seq = ++isanPreviewRequestSeq;

    try {
      const res = await fetch(@json(route('zouyo.preview.inheritance_before')), {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: fd,
        credentials: 'same-origin',
      });

      if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(`HTTP ${res.status} ${text}`);
      }

      const json = await res.json();
      if (seq !== isanPreviewRequestSeq) return;
      if ((json?.status || '') !== 'ok') {
        throw new Error(json?.message || 'preview failed');
      }


      try {
        console.info('[isan-preview][response]', {
          debug: json?.debug ?? null,
          preview_left: json?.preview?.left ?? null,
        });
      } catch (_) {}


      applyIsanPreviewPayload(json.preview || {});
    } catch (err) {
      console.error('[isan-preview] failed', err);
    }
  }

  window.requestIsanBeforePreviewFromCurrentForm = requestIsanBeforePreviewFromCurrentForm;
</script>



