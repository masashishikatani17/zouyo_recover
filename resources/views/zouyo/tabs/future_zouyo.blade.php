<!--   future_zouyo..blade  -->
<style>
  .small-text {
    font-size: 14px;
  }
  

  input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    font-size: 12px;
  }



   .a {
     font-size: 11px;
   }

  /* ▼ このタブの注意書きだけ小さく（印刷も配慮） */
  .note-small {
    font-size: 12px;      /* ベースより小さめ */
     line-height: 1.35;
    color: #000000;
    text-decoration: none; /* ★ アンダーラインを消す */
   }


  /* ★ アンカーステートでも下線が付かないように明示 */
  a.note-small:link,
  a.note-small:visited,
  a.note-small:hover,
  a.note-small:active {
    text-decoration: none;
    color: #000000;
  }

  /*@media print {
    .note-small { font-size: 9px; }
  }
  */

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
   input[type="text"] {
     width: 100%;
     box-sizing: border-box;
     font-size: 11px;
     height: 20px;        /* ★ここが追加 */
     padding: 0 4px;      /* ★上下詰めて横だけ少し余裕 */
     line-height: 1.2;    /* ★行間の詰め */
   }
 
   .input-small {
     font-size: 11px;
     height: 20px;        /* class指定があるならこちらにも */
     padding: 0 4px;
   }
 
 </style>

<style>
  /* 年齢セルを狭い列幅(3桁＋「歳」)に収めるための調整 */
  td.age-cell {
    position: relative;
    width: 40px;       /* ★ 年齢カラム自体の幅 */
    min-width: 40px;
    max-width: 40px;
  }
  td.age-cell .age-input {
    width: 36px;       /* ★ 入力欄を3桁ぶんに固定 */
    box-sizing: border-box;
    /* 右に「歳」を重ねるための余白を確保 */
    padding-right: 12px !important;
    font-size: 11px;     /* 微調整して収まりやすく */
    height: 20px;
    line-height: 1.2;
  }
  td.age-cell .age-suffix {
    position: absolute;
    right: 2px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    color: #333;
    pointer-events: none; /* クリック等を妨げない */
  }
  
    /* 回数・贈与年の列幅（テーブル全体を詰める用） */
  th.col-count, td.col-count {
    width: 26px;
    max-width: 26px;
  }
  th.col-year, td.col-year {
    width: 40px;
    max-width: 40px;
  }
  
</style>

<style>
  /* 保存の色分け */
  .save-pill.is-saving   { background:#fff7e6; border-color:#ffd591; color:#ad6800; }
  .save-pill.is-success  { background:#f6ffed; border-color:#b7eb8f; color:#237804; }
  .save-pill.is-error    { background:#fff1f0; border-color:#ffa39e; color:#a8071a; }
  .save-pill.is-idle     { background:#f9f9f9; border-color:#ddd;    color:#666;    }

  /* どうしても回数など最初の3列の幅が狭くならないので力技
　　★ 将来贈与テーブル専用：列幅を強制 */
  #future-gift-table {
    table-layout: auto !important;  /* 他のどんな fixed より優先させる */
  }

  /* 1列目：回数 */
  #future-gift-table th:nth-child(1),
  #future-gift-table td:nth-child(1) {
    width: 28px !important;
    max-width: 28px !important;
  }

  /* 2列目：贈与年 */
  #future-gift-table th:nth-child(2),
  #future-gift-table td:nth-child(2) {
    width: 42px !important;
    max-width: 42px !important;
  }

  /* 3列目：年齢（中身は age-input でさらに絞る） */
  #future-gift-table th:nth-child(3),
  #future-gift-table td:nth-child(3) {
    width: 34px !important;
    max-width: 34px !important;
  }

  /* 入力可 / 参照 / 自動計算 を見分けやすくする */
  .future-legend {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 4px 0 8px 40px;
    font-size: 11px;
  }

  .future-legend__chip {
    display: inline-flex;
    align-items: center;
    padding: 1px 8px;
    border-radius: 999px;
    border: 1px solid;
    line-height: 1.5;
    white-space: nowrap;
  }

  .future-legend__chip--input {
    background: #fff8db;
    border-color: #d4a72c;
    color: #7c5a00;
  }

  .future-legend__chip--ref {
    background: #edf6ff;
    border-color: #9ec5fe;
    color: #1f3b5b;
  }

  .future-legend__chip--calc {
    background: #eef2f6;
    border-color: #cbd5e1;
    color: #4b5563;
  }

  .future-field-input {
    background: #fff8db !important;
    border: 2px solid #d4a72c !important;
    color: #111827 !important;
  }

  .future-field-input:focus {
    background: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
    outline: none;
  }
  

  /* 精算課税贈与が入った年以降の暦年課税入力欄を無効化 */
  .future-field-disabled-by-settlement {
    background: #e9ecef !important;
    border: 1px solid #cbd5e1 !important;
    color: #6b7280 !important;
    cursor: not-allowed !important;
  }  

  .future-field-ref {
    background: #edf6ff !important;
    border: 1px solid #9ec5fe !important;
    color: #1f3b5b !important;
  }

  .future-field-calc {
    background: #eef2f6 !important;
    border: 1px solid #cbd5e1 !important;
    color: #4b5563 !important;
  }

  .future-field-ref:focus,
  .future-field-calc:focus {
    box-shadow: none !important;
    outline: none;
  }

  .future-edit-col-header {
    background: #ffe69c !important;

  }
  
   /* ※ を上寄せにする */
    .va-top {
      vertical-align: top !important;
       padding-top: 1;         /* 必要なら余白も詰める */
    }
</style>

 {{-- ★ CSRF トークン（JSの fetch で使用） --}}
 {{-- ※ レイアウトに @stack('head') がある場合は以下を推奨：
   @push('head')
     <meta name="csrf-token" content="{{ csrf_token() }}">
   @endpush
 --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

@php
    // ◆ 贈与者（行1）の氏名
    // 優先順：1) $family 2) $prefillFamily 3) old()/request()
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

    // ◆ 受贈者候補（2〜10行目）の氏名＋特例・生年月日メタ
    // 優先順：1) $family 2) $prefillFamily 3) old()/request()
    $heirNames = [];
    $heirMeta  = []; // ['tokurei'=>int,'by'=>int,'bm'=>int,'bd'=>int]

    for ($no = 2; $no <= 10; $no++) {
        $key = (string)$no;

        $nameCandidates = [
            \Illuminate\Support\Arr::get($family ?? [],        $key . '.name'),
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.name'),
            old("name.$no"),
            request()->input("name.$no"),
        ];
        $name = '';
        foreach ($nameCandidates as $cand) {
            $cand = is_string($cand) ? trim($cand) : '';
            if ($cand !== '') { $name = $cand; break; }
        }
        $heirNames[$no] = $name;

        // 特例贈与フラグ／生年月日は family → prefillFamily の順で取得（なければ 0）
        $tokurei = (int)\Illuminate\Support\Arr::get(
            $family ?? [],
            $key . '.tokurei_zouyo',
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.tokurei_zouyo', 0)
        );
        $by = (int)\Illuminate\Support\Arr::get(
            $family ?? [],
            $key . '.birth_year',
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.birth_year', 0)
        );
        $bm = (int)\Illuminate\Support\Arr::get(
            $family ?? [],
            $key . '.birth_month',
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.birth_month', 0)
        );
        $bd = (int)\Illuminate\Support\Arr::get(
            $family ?? [],
            $key . '.birth_day',
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.birth_day', 0)
        );

        $heirMeta[$no] = [
            'tokurei' => $tokurei,
            'by'      => $by,
            'bm'      => $bm,
            'bd'      => $bd,
        ];
    }

@endphp



  {{-- JS用 back-up。基本は form[data-data-id] / APP_DATA_ID を使用 --}}
  <input type="hidden" name="data_id" value="{{ $data->id ?? request('data_id') }}" readonly>
  

  {{-- ★ past_gift_inputs の相続開始日（月日）を既定値としてJSに渡す --}}
  @php
    // Controller 側で $pastGiftInput を渡している想定（なければ null 安全）
    $inhMonthFromDb = old('inherit_month', $pastGiftInput->inherit_month ?? null);
    $inhDayFromDb   = old('inherit_day',   $pastGiftInput->inherit_day   ?? null);
    // DB未設定時のフォールバック（5/7）。必要なら変更可。
    $inhMonthBase   = $inhMonthFromDb ?: 5;
    $inhDayBase     = $inhDayFromDb   ?: 7;
  @endphp
  <input type="hidden" name="inherit_base_month" value="{{ $inhMonthBase }}">
  <input type="hidden" name="inherit_base_day"   value="{{ $inhDayBase }}">



  <table class="table-base ms-10" style="width: 360px;">
      <tr class="border-b align-middle">
        <th style="width: 120px;">第1回目贈与年月日</th>
        <td class="px-2 py-1">
          <div class="flex items-center gap-1">


            <input type="text" class="form-control d-inline input-small text-end future-field-input" name="future_base_year"  style="width: 60px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('future_base_year', $prefillFuture['header']['year'] ?? '') }}">

            <span>年</span>

            <input type="text" class="form-control d-inline input-small text-end future-field-input" name="future_base_month" style="width: 45px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('future_base_month', $prefillFuture['header']['month'] ?? '') }}">

            <span>月</span>

            <input type="text" class="form-control d-inline input-small text-end future-field-input" name="future_base_day"   style="width: 45px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('future_base_day', $prefillFuture['header']['day'] ?? '') }}">

            <span>日</span>
          </div>
        </td>
      </tr>

      {{-- ※ 相続開始（死亡）日の共通基準は hidden で上に注入済み --}}      

  </table>

    <div style="display: none;">
        <p>過年度贈与額: <span id="gift-amount-display">0</span>円</p>
        <p>贈与加算累計額 (2025年〜2031年): <span id="accumulated-amount-display">0</span>円</p>
    </div>

  
@php
  $fsel = old('future_recipient_no', $prefillFuture['recipient_no'] ?? '');
@endphp

  <table class="table-base ms-10" style="width: 300px;">
    <tbody>
      <tr>
        <th style="width: 120px;">贈与者</th>
        <td class="px-2 py-1">
            <input
              type="text"
              class="form-control d-inline input-small future-field-ref"
              name="customer_name"
              style="width: 100px; background-color: #f0f0f0; text-align:left;"              
              readonly
              tabindex="-1"
              value="{{ $donorName }}"
            >
         </td>
      </tr>
      <tr>
        <th>受贈者</th>

          <td class="px-2 py-1">

            @php
                $selFuture = old('future_recipient_no', $prefillFuture['recipient_no'] ?? null);
            @endphp


            <select class="form-control d-inline input-small future-field-input"
                     id="future-recipient-no"
                     name="future_recipient_no"
                     data-fetch-url="{{ route('zouyo.future.fetch', [], false) }}"
                     data-save-url="{{ route('zouyo.save', [], false) }}"  {{-- ★ 既存の自動保存エンドポイントを利用 --}}
                     data-data-id="{{ $data->id ?? request('data_id') }}" 
                     style="width: 100px; text-align:left;">
              
              @foreach($heirNames as $no => $name)
                @if($name !== '')
                  @php
                    $meta = $heirMeta[$no] ?? ['tokurei' => 0, 'by' => 0, 'bm' => 0, 'bd' => 0];
                  @endphp
                  <option
                    value="{{ $no }}"
                    data-tokurei="{{ (int)($meta['tokurei'] ?? 0) }}"
                    data-by="{{ (int)($meta['by'] ?? 0) }}"
                    data-bm="{{ (int)($meta['bm'] ?? 0) }}"
                    data-bd="{{ (int)($meta['bd'] ?? 0) }}"
                    @selected((string)$selFuture === (string)$no)
                  >
                    {{ $name }}
                  </option>
                @endif
              @endforeach

            </select>

            <div style="display: none;">
              {{-- ▼ 保存ステータス表示（受贈者変更の保存結果を可視化） --}}
              <span id="future-save-status"
                    class="save-pill"
                    aria-live="polite"
                    style="margin-left:8px;display:inline-block;min-width:86px;padding:2px 8px;border-radius:12px;border:1px solid #ddd;background:#f9f9f9;font-size:11px;text-align:center;">
                -
              </span>
            </div>
       </tr>
    </tbody>
  </table>


            {{-- 保険：JSが hidden 経由でも拾えるよう、特例フラグを埋め込む（2..10） --}}
            @for ($j = 2; $j <= 10; $j++)
              <input type="hidden" name="tokurei_zouyo[{{ $j }}]" value="{{ (int)($heirMeta[$j]['tokurei'] ?? 0) }}">
            @endfor


            {{-- ★ 年齢計算用：各受贈者の生年月日を常時 hidden で埋める（JS の確実な取得元） --}}
            @for ($j = 2; $j <= 10; $j++)
              <input type="hidden" name="birth_year[{{ $j }}]"  value="{{ (int)($heirMeta[$j]['by'] ?? 0) }}">
              <input type="hidden" name="birth_month[{{ $j }}]" value="{{ (int)($heirMeta[$j]['bm'] ?? 0) }}">
              <input type="hidden" name="birth_day[{{ $j }}]"   value="{{ (int)($heirMeta[$j]['bd'] ?? 0) }}">
            @endfor



    <div class="future-legend">
      <span class="future-legend__chip future-legend__chip--input">入力欄</span>
      
      {{--
      <span class="future-legend__chip future-legend__chip--ref">参照</span>
      <span class="future-legend__chip future-legend__chip--calc">自動計算</span>
      --}}
      
    </div>



    <table class="table-auto small-text mt-4" style="width: 980px;">
        <!-- ★ 単位表示用の最初の行を追加 -->
        <tr>
          <th class="text-end small-text" style="border: none; font-weight: normal;">(単位:千円)</th>
        </tr>
    </table>
      
    <table class="table-compact-p ms-5 mb-3" id="future-gift-table">
      <colgroup>
        <col style="26px;">
        <col style="40px;">
        <col style="40px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
        <col style="70px;">
      </colgroup>
        <tr class="bg-blue">
          <th colspan="3"></th>
          <th colspan="5">暦年贈与</th>
          <th colspan="6">精算課税贈与</th>
        </tr>
        <tr class="bg-grey">
         <th>回数</th>   {{-- 幅は CSS (#future-gift-table ...) が担当 --}}
          <th>贈与年</th>
          <th>年齢</th>
          <th class="future-edit-col-header">贈与額</th>
          <th>基礎控除</th>
          <th>基礎控除後</th>
          <th id="cal-tax-header">(一般税率)<br>贈与税額</th>          
          <th>贈与加算<br>累計額</th>
          <th class="future-edit-col-header">贈与額</th>
          <th>110万円<br>基礎控除</th>
          <th>基礎控除後</th>
          <th>2500万円<br>特別控除後</th>
          <th>20%の<br>贈与税額</th>
          <th>贈与加算<br>累計額</th>
        </tr>
      <tbody>
        <tr>
          <th colspan="3">過年度分の合計</th>

          <?php
            $i = 0;
            $kojoTotal = $prefillFuture['rekinen']['total']['kojo'] ?? 0;
          ?>
            

          <?php
            $i = 0;
            $zoyoTotal = $prefillFuture['rekinen']['total']['zoyo'] ?? 0;
            $kojoTotal = $prefillFuture['rekinen']['total']['kojo'] ?? 0;
            $basicK = 1100 * count(array_filter($prefillFuture['rekinen']['year'] ?? [])); // 贈与年数
            $afterBasic = max($zoyoTotal - $basicK, 0);
          ?>
          
          
            
          <!-- 暦年課税贈与 -->
          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc"
                   name="cal_amount[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('cal_amount.'.$i, isset($prefillFuture['plan']['cal_amount'][$i]) ? number_format($prefillFuture['plan']['cal_amount'][$i]) : '') }}">
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0" name="cal_basic[{{ $i }}]" style=" ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" value="{{ number_format($basicK * -1) }}">
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc"            
                   name="cal_after_basic[{{ $i }}]" style=" ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('cal_after_basic.'.$i, isset($prefillFuture['plan']['cal_after_basic'][$i]) ? number_format($prefillFuture['plan']['cal_after_basic'][$i]) : '') }}" value="{{ number_format($afterBasic) }}">
          </td>


          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0"
                   name="cal_tax[{{ $i }}]"
                   style="ime-mode: disabled; background-color: #f0f0f0;" 
                   readonly tabindex="-1" inputmode="numeric"
                   value="{{ number_format($kojoTotal) }}" value="{{ number_format($kojoTotal) }}">
          </td>



          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0 future-field-calc"            
                   name="cal_cum[{{ $i }}]"
                   style="ime-mode: disabled; background-color: #f0f0f0;" 
                   readonly tabindex="-1" inputmode="numeric"
                   value="0">
          </td>


          <!-- 精算課税贈与 -->
          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc"
                   name="set_amount[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('set_amount.'.$i, isset($prefillFuture['plan']['set_amount'][$i]) ? number_format($prefillFuture['plan']['set_amount'][$i]) : '') }}">
          </td>

          <td class="border px-1 py-0">
          <input type="text" class="form-control suji8 comma decimal0 future-field-calc"            
                   name="set_basic110[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('set_basic110.'.$i, isset($prefillFuture['plan']['set_basic110'][$i]) ? number_format($prefillFuture['plan']['set_basic110'][$i]) : '') }}">
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc"
                   name="set_after_basic[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('set_after_basic.'.$i, isset($prefillFuture['plan']['set_after_basic'][$i]) ? number_format($prefillFuture['plan']['set_after_basic'][$i]) : '') }}">
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc"
                   name="set_after_25m[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('set_after_25m.'.$i, isset($prefillFuture['plan']['set_after_25m'][$i]) ? number_format($prefillFuture['plan']['set_after_25m'][$i]) : '') }}">
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0"
                   name="set_tax20[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('set_tax20.'.$i, isset($prefillFuture['plan']['set_tax20'][$i]) ? number_format($prefillFuture['plan']['set_tax20'][$i]) : '') }}">
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc"
                   name="set_cum[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric"
                   value="{{ old('set_cum.'.$i, isset($prefillFuture['plan']['set_cum'][$i]) ? number_format($prefillFuture['plan']['set_cum'][$i]) : '') }}">
          </td>

        </tr>

        <!-- 1-10 -->
        @for ($i = 1; $i <= 20; $i++)
        <tr>

          <td class="text-end">{{ $i }}</td>

          <td>{{ (2024 + $i) }}</td>

          <td class="border py-0 age-cell" style="padding-left:0; padding-right:0;">
            <input
              type="text"
              class="form-control input-small text-end age-input"
              name="age_dynamic"
              data-recipient-no="{{ $prefillFuture['recipient_no'] ?? '' }}"
              data-birth-year="{{ $prefillFamily[$prefillFuture['recipient_no']]['birth_year'] ?? '' }}"
              data-birth-month="{{ $prefillFamily[$prefillFuture['recipient_no']]['birth_month'] ?? '' }}"
              data-birth-day="{{ $prefillFamily[$prefillFuture['recipient_no']]['birth_day'] ?? '' }}"
              data-base-year="{{ 2024 + $i }}"
              readonly
              tabindex="-1"
              style="ime-mode: disabled; background-color: #f0f0f0;"
            >
            <span class="age-suffix" aria-hidden="true">歳</span>
          </td>
            
          <!-- 暦年贈与 -->

          {{-- ★行単位の贈与日（UIを崩さない hidden。必要な行だけ値を入れてください） --}}
          <td class="border px-1 py-0">

            <input type="text" class="form-control suji8 comma decimal0 future-field-input"
                   name="cal_amount[{{ $i }}]" style=" ime-mode: disabled;" inputmode="numeric" >
            {{-- ★行単位の贈与日（UIを崩さない hidden。必要な行だけ値を入れてください） --}}
            <input type="hidden" name="gift_month[{{ $i }}]" value="{{ old('gift_month.'.$i, $prefillFuture['plan']['gift_month'][$i] ?? '') }}">


            {{-- ★行単位の贈与日（UIを崩さない hidden。必要な行だけ値を入れてください） --}}
            <input type="hidden" name="gift_day[{{ $i }}]"   value="{{ old('gift_day.'.$i,   $prefillFuture['plan']['gift_day'][$i]   ?? '') }}">
            
            <input type="hidden" name="gift_year[{{ $i }}]" value="{{ 2024 + $i }}">
          </td>

 
          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0 future-field-calc" name="cal_basic[{{ $i }}]" style=" ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="cal_after_basic[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0 future-field-calc" name="cal_tax[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="cal_cum[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <!-- 精算課税贈与 -->
          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-input" name="set_amount[{{ $i }}]" style="ime-mode: disabled;" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0 future-field-calc" name="set_basic110[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="set_after_basic[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="set_after_25m[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji7 comma decimal0 future-field-calc" name="set_tax20[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

          <td class="border px-1 py-0">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="set_cum[{{ $i }}]" style="ime-mode: disabled; background-color: #f0f0f0;" readonly tabindex="-1" inputmode="numeric" >
          </td>

        </tr>
        @endfor
        
        
        <tr class="bg-cream">
          <td class="border px-1 py-1 text-center" colspan="3">合　　計</td>
          <td class="border px-1 py-1">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="cal_amount[110]" style=" background-color: #f0f0f0;" readonly tabindex="-1">
          </td>
          <td class="border px-1 py-1"></td>
          <td class="border px-1 py-1"></td>
          <td class="border px-1 py-1">
            <input type="text" class="form-control suji7 comma decimal0" name="cal_tax[110]" style=" background-color: #f0f0f0;" readonly tabindex="-1">
          </td>
          <td class="border px-1 py-1"></td>
          <td class="border px-1 py-1">
            <input type="text" class="form-control suji8 comma decimal0 future-field-calc" name="set_amount[110]" style=" background-color: #f0f0f0;" readonly tabindex="-1">
          </td>
          <td class="border px-1 py-1"></td>
          <td class="border px-1 py-1"></td>
          <td class="border px-1 py-1"></td>
          <td class="border px-1 py-1">
            <input type="text" class="form-control suji7 comma decimal0 future-field-calc" name="set_tax20[110]" style="background-color: #f0f0f0;" readonly tabindex="-1">
          </td>
          <td class="border px-1 py-1"></td>
        </tr>
      </tbody>
    </table>
  <table class="g-table--none mt-3 margin-right: auto" style="width: 860px;">
      <colgroup>
        <col style="width:10px;">
        <col style="width:850px;">
      </colgroup>
      <tr>
        <td class="text-end pe-3 va-top">※</td>
        <td class="text-start">
          暦年贈与の場合、取戻し期間が従来３年だったものが2024年より１年ずつ増えていき2027年から最長７年間となります。
        </td>
      </tr>
       <tr>
        <td class="text-end pe-3 va-top">※</td>
        <td class="text-start">
         贈与加算累計額は贈与がなかったものとして相続財産に加算される額のことですが、既に納付済みの贈与税は相続税から控除されます。また控除しきれない場合には還付されます(ただし精算課税贈与の場合のみ)。
        </td>
      </tr>
       <tr>
        <td class="text-end pe-3 va-top">※</td>
        <td class="text-start">
          暦年贈与の場合、取り戻される額は基礎控除後の額ではなく贈与額そのものです。一方、精算課税贈与の場合は2024年より110万円控除が適用されるようになり取り戻される額は110万円控除後の額です。。
        </td>
      </tr>
       <tr>
        <td class="text-end pe-3 va-top">※</td>
        <td class="text-start">
          ここに計算表示されている暦年贈与の贈与加算累計額は3年超の贈与に適用される110万円控除を適用済みです。
        </td>
      </tr>
  </table>

      <br>
      <br>

@php
  // ★税率は年で変わらない前提：DBに存在する最新の kih u_year を常に使用する
  $tokYear = \App\Models\ZouyoTokureiRate::whereNull('company_id')->max('kihu_year') ?: 2026;
  $genYear = \App\Models\ZouyoGeneralRate::whereNull('company_id')->max('kihu_year') ?: 2026;

  $verTokurei = \App\Models\ZouyoTokureiRate::whereNull('company_id')
                  ->where('kihu_year',$tokYear)->max('version') ?: 1;
  $verGeneral = \App\Models\ZouyoGeneralRate::whereNull('company_id')
                  ->where('kihu_year',$genYear)->max('version') ?: 1;

  $tokureiRates = \App\Models\ZouyoTokureiRate::whereNull('company_id')
                  ->where('kihu_year',$tokYear)->where('version',$verTokurei)
                  ->orderBy('seq')->get(['lower','upper','rate','deduction_amount'])->toArray();
  $generalRates = \App\Models\ZouyoGeneralRate::whereNull('company_id')
                  ->where('kihu_year',$genYear)->where('version',$verGeneral)
                  ->orderBy('seq')->get(['lower','upper','rate','deduction_amount'])->toArray();
@endphp

<script>
// 税率表（円単位）を window に注入（このブロックは【本体JSより前】に配置）
window.GIFT_RATES = {
  tokurei: @json($tokureiRates, JSON_UNESCAPED_UNICODE),
  general: @json($generalRates, JSON_UNESCAPED_UNICODE)
};


      /** ---------- 共通ユーティリティ ---------- */
      const KYEN = 1000; // 千円→円
      // 入力編集中フラグ（参照順序問題を避けるため最上流で一度だけ定義）
      let isEditing = false;


// 数値を整数に変換する関数
const toInt = (v, d = 0) => {
    const s = String(v).replace(/[^\d\-]/g, ''); // 数字以外の文字を削除
    const n = parseInt(s, 10);
    return Number.isFinite(n) ? n : d; // 数値であれば返し、そうでなければデフォルト値を返す
};

// 3桁カンマ形式にフォーマットする関数
const fmtK = (k) => (Number(k) || 0).toLocaleString();

// 入力フィールドに値をセットする関数
const setK = (name, idx, v) => {
    const el = document.querySelector(`input[name="${name}[${idx}]"]`);
    if (!el) return;
    el.value = (v === '' || v === null || v === undefined) ? '' : fmtK(v);
};


      /** ---------- 年齢表示 ---------- */
      const getRecipientBirth = (recipientNo) => {
        // hidden を唯一のSoTとする（古い dataset の持ち越しは使わない）
        const y = toInt(document.querySelector(`input[name="birth_year[${recipientNo}]"]`)?.value, 0);
        const m = toInt(document.querySelector(`input[name="birth_month[${recipientNo}]"]`)?.value, 0);
        const d = toInt(document.querySelector(`input[name="birth_day[${recipientNo}]"]`)?.value, 0);
        return { y, m, d };
      };


      // recalcAges：hidden だけをSoTにする（datasetには一切フォールバックしない）
      window.recalcAges = function (recipientNo) {
        const { y, m, d } = getRecipientBirth(recipientNo);
        document.querySelectorAll('input[name="age_dynamic"]').forEach((el) => {
          const baseYear = toInt(el.dataset.baseYear, 0);
          // hidden が揃っている時のみ確定。揃っていなければ何もしない（既存表示を保持）
          if (y && m && d && baseYear) {
            el.value = calcAgeAsOfJan1(y, m, d, baseYear);
          }
        });
      };


      
      // 年齢を計算するための関数
      const calcAgeAsOfJan1 = (by, bm, bd, baseYear) => {
        if (!by || !bm || !bd || !baseYear) return '';
        const base = new Date(baseYear, 0, 1);
        const birth = new Date(by, bm - 1, bd);
        let age = baseYear - by;
        const birthdayThisYear = new Date(baseYear, birth.getMonth(), birth.getDate());
        if (birthdayThisYear > base) age -= 1;
        return age >= 0 && age <= 130 ? String(age) : '';
      };


      // ★ 年齢計算系をグローバルに公開して、IIFE外（applyFuturePayload等）からも確実に呼べるようにする
      window.__getRecipientBirth = getRecipientBirth;



// getLookbackRange 関数の定義
const getLookbackRange = (deathDate) => {
    const r8End      = new Date(2026, 11, 31); 
    const r9Start    = new Date(2027,  0,  1);
    const r12End     = new Date(2030, 11, 31);
    const fixedStart = new Date(2024,  0,  1);


    const end = deathDate;
    if (deathDate <= r8End) {
        const start = new Date(deathDate);
        start.setFullYear(start.getFullYear() - 3);  // 3年前
        return [start, end];
    } else if (deathDate >= r9Start && deathDate <= r12End) {
        return [fixedStart, end];  // 2024年1月1日から2030年12月31日
    } else {
        const start = new Date(deathDate);
        start.setFullYear(start.getFullYear() - 7);  // 7年前
        return [start, end];
    }
};



// calcRekinenCumK 関数の定義
// ---------- 過年度(rekinen)のルックバック累計(千円)を算出 ----------
// 2025年死亡例：2022/5/7〜2025/5/7 だけを対象にする（3年を厳密に含む）
// 2027〜2030年は 2024/1/1 固定開始、2031年以降は7年（超過分は 1,100千円控除後を加算）
// ※控除(1,100千円)は「3年超の範囲」が存在する場合のみ適用
//
const calcRekinenCumK = (rekinen, deathDate) => {
    if (!rekinen || !rekinen.year || !rekinen.month || !rekinen.day) return null;

    const Y = rekinen.year  || {};
    const M = rekinen.month || {};
    const D = rekinen.day   || {};
    const Z = (rekinen.zoyo ?? rekinen.zouyo) || {};

    const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
    const end = deathDate instanceof Date ? new Date(deathDate) : new Date();
    const [rangeStart, rangeEnd] = getLookbackRange(end);  // getLookbackRange を呼び出し
    const threeYearsAgo = new Date(end);
    threeYearsAgo.setFullYear(end.getFullYear() - 3);

    let within3 = 0;
    let over3 = 0;

    for (const k of Object.keys(Y)) {
        const y = +Y[k] || 0;
        const m = +M[k] || 0;
        const d = +D[k] || 0;
        const kz = + (Z[k] || 0);
        if (!y || !m || !d || !kz) continue;

        const dt = new Date(y, clamp(m - 1, 0, 11), clamp(d, 1, 31));

        if (dt < rangeStart || dt > rangeEnd) continue;

        if (dt >= threeYearsAgo) within3 += Math.trunc(kz);
        else over3 += Math.trunc(kz);
    }

    const OVER3_DEDUCTION_K = 1000;
    return within3 + Math.max(0, over3 - OVER3_DEDUCTION_K);
};


      // 全角→半角の正規化
      const normalizeNum = (s) => String(s ?? '')
        .replace(/[０-９]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0))
        .replace(/[，、．]/g, ch => (ch === '．' ? '.' : ','));

      // ★ IIFE外（applyFuturePayload 等）からも使えるよう公開
      window.normalizeNum = normalizeNum;
      window.toInt = toInt;
      window.setK = setK;




      const indexFromName = (name) => {
        const m = String(name || '').match(/\[(\d+)\]$/);
        return m ? Number(m[1]) : NaN;
      };



      /** ---------- 行集合（動的） ---------- */
      const getCalRows = () =>
        Array.from(document.querySelectorAll('input[name^="cal_amount["]'))
          .map(el => indexFromName(el.name))
          .filter(i => Number.isFinite(i) && i >= 1 && i !== 110)
          .sort((a, b) => a - b);

      const getSetRows = () =>
        Array.from(document.querySelectorAll('input[name^="set_amount["]'))
          .map(el => indexFromName(el.name))
          .filter(i => Number.isFinite(i) && i >= 1 && i !== 110)
          .sort((a, b) => a - b);



      // 現在選択されている受贈者番号を取得する関数
      const getCurrentRecipientNo = () => {
          const sel = document.getElementById('future-recipient-no');
          return sel ? String(sel.value || '').trim() : '';
      };
      // ★ モジュール/スコープ差吸収：グローバル公開して他ブロックからも参照可能に
      window.getCurrentRecipientNo = getCurrentRecipientNo;



      const toggleCalTaxHeader = (isTokurei) => {
        const hdr = document.getElementById('cal-tax-header');
        if (!hdr) return;

        hdr.innerHTML = isTokurei
          ? '(特例税率)<br>贈与税額'
          : '(一般税率)<br>贈与税額';

      };

      /** ---------- 受贈者の特例フラグ & 見出し ---------- */
      const resolveTokureiFlag = (recipientNo) => {
        // ★ 修正：テンプレートリテラルを正しく使用（バッククォート）
        const opt = document.querySelector(
          `#future-recipient-no option[value="${recipientNo}"]`
        );
        if (opt?.dataset?.tokurei != null) {
          return String(opt.dataset.tokurei).trim() === '1';
        }
        const hid = document.querySelector(`input[name="tokurei_zouyo[${recipientNo}]"]`);
        return hid ? String(hid.value).trim() === '1' : false;
      };




      /** ---------- 暦年：贈与税額（千円） ---------- */
        const calcGiftTaxKyen = (afterK, isTokurei) => {
        const table = isTokurei ? (window.GIFT_RATES?.tokurei || []) : (window.GIFT_RATES?.general || []);
        const baseY = Math.max(0, Number(afterK) || 0) * KYEN;
        if (!table.length || baseY <= 0) return 0;
        let taxY = 0;
        for (const b of table) {
          const upper = b.upper && b.upper > 0 ? b.upper : Number.MAX_SAFE_INTEGER;
          if (baseY >= (b.lower || 0) && baseY <= upper) {
            taxY = Math.max(0, Math.round(baseY * b.rate) - (b.quick || 0));
            break;
          }
        }
        return Math.round(taxY / KYEN);
      };


      

      /** ---------- 暦年：全行一括再計算（cal_cum はルックバックで集計） ---------- */
      const recalcAllRowsCal = () => {
          const rn = document.getElementById('future-recipient-no')?.value || '';
          // ★ 受贈者フィルタで使用する現在値（未定義参照の修正）
          const rnSel = getCurrentRecipientNo();

          const isTokurei = resolveTokureiFlag(rn);
          toggleCalTaxHeader(isTokurei);
      
          const rows = getCalRows();
          const BASIC_K = 1100;  // 基礎控除額（110万円）
      
          const giftMonth = toInt(document.querySelector('input[name="future_base_month"]')?.value, 12);
          const giftDay = toInt(document.querySelector('input[name="future_base_day"]')?.value, 31);
      
          const inhBaseMonth = toInt(document.querySelector('input[name="inherit_base_month"]')?.value, giftMonth || 12);
          const inhBaseDay = toInt(document.querySelector('input[name="inherit_base_day"]')?.value, giftDay || 31);
      
          const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));

           const giftDates = {};
           for (const j of rows) {
               const gy = 2024 + j; // 行番号が 1=2025年 になるよう、確実に固定（ズレ対策）               
               const gm = toInt(document.querySelector(`input[name="gift_month[${j}]"]`)?.value, giftMonth || 12);
               const gd = toInt(document.querySelector(`input[name="gift_day[${j}]"]`)?.value, giftDay || 31);
               const mm = clamp(gm - 1, 0, 11);
               const dd = clamp(gd, 1, 31);
               giftDates[j] = new Date(gy, mm, dd);
           }

            for (const i of rows) {
              const amtEl = document.querySelector(`input[name="cal_amount[${i}]"]`);
              if (!amtEl) continue;
          
              const amountK_self = toInt(amtEl.value, 0);
              const afterK = Math.max(amountK_self - BASIC_K, 0);
              const taxK = calcGiftTaxKyen(afterK, isTokurei);
          
              const inhM = toInt(document.querySelector(`input[name="inherit_month[${i}]"]`)?.value, inhBaseMonth || 12);
              const inhD = toInt(document.querySelector(`input[name="inherit_day[${i}]"]`)?.value, inhBaseDay || 31);
              const deathDate = new Date(2024 + i, Math.max(0, Math.min(11, inhM - 1)), Math.max(1, Math.min(31, inhD)));
              const [rangeStart, rangeEnd] = getLookbackRange(deathDate);
              const threeYearsAgo = new Date(deathDate);
              threeYearsAgo.setFullYear(deathDate.getFullYear() - 3);
          
              let sumWithin3K = 0;
              let sumOver3K = 0;
              let sumPastWithin3K = 0;
              let sumPastOver3K = 0;
          
              for (const j of rows) {
                const dt = giftDates[j];
                const valK = toInt(document.querySelector(`input[name="cal_amount[${j}]"]`)?.value, 0);
                if (!dt || !valK) continue;
                if (dt < rangeStart || dt > rangeEnd) continue;
                if (dt >= threeYearsAgo) sumWithin3K += valK;
                else sumOver3K += valK;
              }
          


                    // ② 過年度贈与（window.PAST_GIFTS）も同一ルールで加算
                    //    期待形式: { y, m, d, k }  ※k: 千円
                    // ▼ PAST_GIFTS が配列でも「空」なら rekinen をフォールバック使用する
                    const _pg = Array.isArray(window.PAST_GIFTS) ? window.PAST_GIFTS : [];
                    if (_pg.length > 0) {
                       // 現在の受贈者のみ集計
                      for (const g of _pg.filter(x => !x.rn || String(x.rn) === rnSel)) {
                         const gy = Number(g?.y || g?.year || 0);
                         const gm = Number(g?.m || g?.month || 0);
                         const gd = Number(g?.d || g?.day || 0);
                         const k  = Number(g?.k || g?.amount_k || 0);
                         if (!gy || !gm || !gd || !k) continue;
                         const dt = new Date(gy, Math.max(0, Math.min(11, gm - 1)), Math.max(1, Math.min(31, gd)));
                         if (dt < rangeStart || dt > rangeEnd) continue;
                         if (dt >= threeYearsAgo) sumPastWithin3K += k; else sumPastOver3K += k;
                       }
                    }
                    if (_pg.length === 0 && window.__LAST_FUTURE_PAYLOAD?.rekinen) {
                       // ★ フォールバック：サーバ返却の rekinen から分割集計
                       const split = calcRekinenSplit(window.__LAST_FUTURE_PAYLOAD.rekinen, deathDate);
                       sumPastWithin3K += split.within3K;
                       sumPastOver3K   += split.over3K;
                    }


                    // ③ 両方の合計を足し合わせる
                    const totalWithin3K = sumWithin3K + sumPastWithin3K;
                    const totalOver3K = sumOver3K + sumPastOver3K;
                    
                    // ④ 累計額の計算
                    const OVER3_DEDUCTION_K = 1000;
                    let cumK = totalWithin3K + Math.max(0, totalOver3K - OVER3_DEDUCTION_K);
                    
              //console.debug(`2025_11_10_01 Row ${i}: sumPastOver3K=${sumPastOver3K}, sumOver3K=${sumOver3K}, cumK=${cumK}`);


                    // ★ 最終的な累計額をセット（期間判定済みの過年度も含む）
                    setK('cal_cum', i, Math.trunc(cumK));


                    //setK('cal_cum', 0, 0);
                    
              if (window.console && console.debug) {
                //console.debug('[recalcAllRowsCal]', { row: i, sumWithin3K, sumOver3K, cumK });
              }


              setK('cal_basic', i, amountK_self > 0 ? -BASIC_K : '');
              setK('cal_after_basic', i, afterK);
              setK('cal_tax', i, taxK);

              //console.debug(`Row ${i}: rnSel=${rnSel}, sumWithin3K=${sumWithin3K}, sumOver3K=${sumOver3K}, pastWithin3K=${sumPastWithin3K}, pastOver3K=${sumPastOver3K}, cumK=${cumK}`);


          }





          // 行単位の更新後、上部集計も更新
          updateTopCounters();

          // 行0も同期しておく（過年度のみの累計）
          recalcPastOnlyCum0();
          
      };
      


      // ★ 公開（applyFuturePayload から使用）
      window.recalcAllRowsCal = recalcAllRowsCal;




      /** ---------- 精算課税（110万/2,500万/20%／加算累計は「基礎控除後」） ---------- */
      const recalcSettlementAllRows = () => {
        const BASIC_1100 = 1100;
        let remain25m = 25000;
        const rows = getSetRows();

        // 0行目（過年度）の最新値を先に確定させてから将来行を積み上げる
        try { recalcPastSettlementRow0 && recalcPastSettlementRow0(); } catch (_) {}

        // ★ 累計の開始値を「過年度の基礎控除後（set_after_basic[0]）」で初期化
        let baseAfterBasic0 =
          toInt(document.querySelector('input[name="set_after_basic[0]"]')?.value ?? '', 0);

        // ここで「基礎控除後（set_after_basic）」の累計を走らせる
        let cumAfterBasicK = baseAfterBasic0; // ← 千円単位の累計（set_cum に入れる）
        

        // ★ 表示も同期：0行目 set_cum[0] に“現時点の afterBasic(0)”を出す
        //   （過年度の afterBasic をそのまま表示しておくことで、将来行が空でも
        //    「現時点の累計」が常に確認できる）
        setK('set_cum', 0, baseAfterBasic0);
        


        for (const i of rows) {
          const el = document.querySelector(`input[name="set_amount[${i}]"]`);
          if (!el) continue;

          const amountK = toInt(el.value, 0);
          setK('set_basic110', i, amountK > 0 ? -BASIC_1100 : '');

          const afterBasic = Math.max(amountK - BASIC_1100, 0);
          const useThis = Math.min(remain25m, afterBasic);
          const after25m = Math.max(afterBasic - useThis, 0);
          remain25m -= useThis;

          const tax20 = Math.round(after25m * 0.2);

          setK('set_after_basic', i, afterBasic);
          setK('set_after_25m', i, after25m);
          setK('set_tax20', i, tax20);
          

          // ★ 精算課税の「贈与加算累計額」は「基礎控除後（afterBasic）」の累計
          cumAfterBasicK += afterBasic;
          setK('set_cum', i, cumAfterBasicK);          
          
          
          // 精算課税　贈与加算累計額　0行目は0
          //setK('set_cum', 0, 0);          
          
        }
      };

      //（必要なら公開：今は applyFuturePayload から直接呼んでいませんが、将来の再利用に備えて）
      // window.recalcSettlementAllRows = recalcSettlementAllRows;



      /** ---------- 精算課税入力に応じて暦年課税入力欄をロック ---------- */
      const syncCalendarGiftLockBySettlement = () => {
        const setRows = getSetRows();
        const calRows = getCalRows();

        let firstSettlementRow = null;

        // ★ 過年度（行0）に精算課税贈与がある場合は、
        //    将来分の暦年課税は1年目からすべて不可にする
        const pastSettlementEl = document.querySelector('input[name="set_amount[0]"]');
        const pastSettlementK = toInt(pastSettlementEl?.value, 0);
        if (pastSettlementK > 0) {
          firstSettlementRow = 1;
        }

        // ★ 過年度に無い場合のみ、将来行の最初の精算課税入力年を探す
        if (firstSettlementRow === null) {
          for (const i of setRows) {
            const setEl = document.querySelector(`input[name="set_amount[${i}]"]`);
            const amountK = toInt(setEl?.value, 0);
            if (amountK > 0) {
              firstSettlementRow = i;
              break;
            }
          }
        }

        for (const i of calRows) {
          const calEl = document.querySelector(`input[name="cal_amount[${i}]"]`);
          if (!calEl) continue;

          const shouldLock = firstSettlementRow !== null && i >= firstSettlementRow;

          if (shouldLock) {
            calEl.value = '0';
            calEl.disabled = true;
            calEl.classList.remove('future-field-input');
            calEl.classList.add('future-field-disabled-by-settlement');
          } else {
            calEl.disabled = false;
            calEl.classList.remove('future-field-disabled-by-settlement');
            calEl.classList.add('future-field-input');
          }
        }
      };

      window.syncCalendarGiftLockBySettlement = syncCalendarGiftLockBySettlement;



document.addEventListener('DOMContentLoaded', function () {
  const futureEnterSelector = [
    '#future-recipient-no',
    'input[name="future_base_year"]',
    'input[name="future_base_month"]',
    'input[name="future_base_day"]',
    'input[name^="cal_amount["]',
    'input[name^="set_amount["]'
  ].join(', ');

  function isVisible(el) {
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function isFutureEnterTarget(el) {
    if (!(el instanceof HTMLElement)) return false;
    if (!el.matches(futureEnterSelector)) return false;

    if (el instanceof HTMLInputElement) {
      if (el.type === 'hidden') return false;
      if (/^(cal_amount|set_amount)\[(0|110)\]$/.test(el.name)) return false;
    }

    return !el.disabled && !el.readOnly && el.tabIndex !== -1 && isVisible(el);
  }

  function getFutureFocusable() {
    return Array.from(document.querySelectorAll(futureEnterSelector))
      .filter(isFutureEnterTarget);
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.isComposing) return;

    const target = e.target;
    if (!isFutureEnterTarget(target)) return;

    // 既定の submit（＝「計算開始」）を止める
    e.preventDefault();
    e.stopPropagation();

    const focusable = getFutureFocusable();
    const currentIndex = focusable.indexOf(target);
    if (currentIndex === -1) return;

    const next = focusable[currentIndex + 1];
    if (next) {
      next.focus();

      if (next instanceof HTMLInputElement && typeof next.select === 'function') {
        next.select();
      }
      return;
    }

    // 最終項目でも submit させず、blur だけ発火して既存の再計算処理は走らせる
    if (target instanceof HTMLElement && typeof target.blur === 'function') {
      target.blur();
    }
  }, true);
});




  // タブ切替時：#zouyo-tab-input04 が開いた時にだけ呼ぶ
  document.addEventListener('shown.bs.tab', function (e) {
    const target = e.target?.getAttribute('data-bs-target');
    if (target === '#zouyo-tab-input04') {
      window.z_onFutureTabShown?.();
    }
  });




  // （削除）※受贈者変更時の処理は下の専用ハンドラで一元化するため、この簡易リスナーは撤去



  document.addEventListener('DOMContentLoaded', () => {
  
    // 即時実行関数（IIFE）の開始（初期データ取得のため async 化）
    (async function () {

      'use strict';

      // ここから下に処理を書いていく

      /* ===============================
         future_zouyo.blade 統合JS（差し替え用）
         ・暦年：贈与額入力→即時計算（全角数字OK）
         ・精算課税：110万/2,500万/20% 計算
         ・年齢表示（各行の基準年 1/1 時点）
         ・(特例/一般)見出し自動切替
         ・暦年の「贈与加算累計額」= ルックバック規則で当年までの対象額合計
         ・行は DOM から動的収集（1..20 決め打ちに依存しない）
       =============================== */


      
      



     



      // ★ 公開（applyFuturePayload から使用）
      window.calcRekinenCumK = calcRekinenCumK;

      /** ---------- 過年度贈与（千円）保持領域（選択受贈者ごとに注入） ---------- */
       // 形式: [{ y:2023, m:12, d:25, k:500 }, ...]  ※k は千円
       
       //console.log(window.PAST_GIFTS);
       // ★ 未定義/不正型のときだけ初期化。配列なら残す（＝消さない）
       if (!Array.isArray(window.PAST_GIFTS)) window.PAST_GIFTS = [];
       window.__LAST_FUTURE_PAYLOAD = window.__LAST_FUTURE_PAYLOAD || null;
      
      /** ---------- 上部カウンタ表示（過年度合計・2031年時点の加算累計） ---------- */
      // giftAmount（上段「過年度贈与額」）はここで計算：
      //   Σ(window.PAST_GIFTS[].k) * 1000（千円→円）
      // accumulated-amount（2031年仮死亡基準）：
      //   ルックバック範囲に入る「これからの贈与(行1=2025〜行7=2031)」＋「過年度贈与」
      //   を 3年以内/3年超に区分し、 3年以内 + max(3年超 - 1000千円, 0) を円で表示
      const fmtYen = (n) => (Number(n)||0).toLocaleString();
      const updateTopCounters = () => {
      
        const rnSel = getCurrentRecipientNo();
      
      

        // 1) 過年度贈与額（合計）… 千円→円（無ければサーバ値でフォールバック）
        let pastTotalK = (Array.isArray(window.PAST_GIFTS) ? window.PAST_GIFTS : [])
          .filter(g => !g.rn || String(g.rn) === rnSel) // ★ 現受贈者のみ
          .reduce((s,g)=> s + (Number(g?.k||g?.amount_k||0)||0), 0);

        let pastTotalY = pastTotalK * 1000;
        if (!pastTotalY && window.__LAST_FUTURE_PAYLOAD?.past) {
          const p = window.__LAST_FUTURE_PAYLOAD.past;
          // サーバが円で返す or 千円で返す両対応
          pastTotalY = Number(p.giftAmountYen ?? p.giftAmount ?? 0);
          if (!pastTotalY) {
            const k = Number(p.giftAmountK ?? p.total_k ?? 0);
            pastTotalY = k * 1000;
          }
        }
        
        const $past = document.getElementById('gift-amount-display');
        if ($past) $past.textContent = fmtYen(pastTotalY);

        // 2) 贈与加算累計額 (2025年〜2031年)
        const inhBaseMonth = toInt(document.querySelector('input[name="inherit_base_month"]')?.value, 12);
        const inhBaseDay   = toInt(document.querySelector('input[name="inherit_base_day"]')?.value,   31);
        const death2031 = new Date(2031, Math.max(0, Math.min(11, inhBaseMonth-1)), Math.max(1, Math.min(31, inhBaseDay)));
        const [rangeStart, rangeEnd] = getLookbackRange(death2031);
        const threeYearsAgo = new Date(death2031); threeYearsAgo.setFullYear(threeYearsAgo.getFullYear()-3);

        // 「これからの贈与」側（行1..20のうち、2031年まで=行1..7）を対象
        const rows = getCalRows();
        const giftMonthDefault = toInt(document.querySelector('input[name="future_base_month"]')?.value, 12);
        const giftDayDefault   = toInt(document.querySelector('input[name="future_base_day"]')?.value,   31);
        const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
        let within3K = 0, over3K = 0;

        for (const i of rows) {
          if (i > 7) break; // 2025〜2031のみ
          const vK = toInt(document.querySelector(`input[name="cal_amount[${i}]"]`)?.value, 0);
          if (!vK) continue;
          const gm = toInt(document.querySelector(`input[name="gift_month[${i}]"]`)?.value, giftMonthDefault);
          const gd = toInt(document.querySelector(`input[name="gift_day[${i}]"]`)?.value,   giftDayDefault);
          const dt = new Date(2024 + i, clamp((gm||12)-1,0,11), clamp(gd||31,1,31));
          if (dt < rangeStart || dt > rangeEnd) continue;
          if (dt >= threeYearsAgo) within3K += vK; else over3K += vK;
        }

        // 過年度贈与も合算
        // 過年度贈与も合算（空配列なら rekinen をフォールバック）
        const _pg = Array.isArray(window.PAST_GIFTS) ? window.PAST_GIFTS : [];
        if (_pg.length > 0) {
 
          for (const g of _pg.filter(x => !x.rn || String(x.rn) === rnSel)) {
 
             const gy = Number(g?.y || g?.year || 0);
             const gm = Number(g?.m || g?.month || 0);
             const gd = Number(g?.d || g?.day   || 0);
             const k  = Number(g?.k || g?.amount_k || 0);
             if (!gy || !gm || !gd || !k) continue;
             const dt = new Date(gy, clamp(gm-1,0,11), clamp(gd,1,31));
             if (dt < rangeStart || dt > rangeEnd) continue;
             if (dt >= threeYearsAgo) within3K += k; else over3K += k;
 
           }
        }
        if (_pg.length === 0 && window.__LAST_FUTURE_PAYLOAD?.rekinen) {
          const split = calcRekinenSplit(window.__LAST_FUTURE_PAYLOAD.rekinen, death2031);
          within3K += split.within3K;
          over3K   += split.over3K;
        }



        const OVER3_DEDUCTION_K = 1000;
        let cumK = within3K + Math.max(0, over3K - OVER3_DEDUCTION_K);
        // クライアント算出が 0 のときのみ、サーバ返却のフォールバックを適用
        if (!cumK && window.__LAST_FUTURE_PAYLOAD?.past) {
          const p = window.__LAST_FUTURE_PAYLOAD.past;
          const yen = Number(p?.accumulatedAmountYen ?? p?.accumulatedAmount ?? 0);
          const k   = Number(p?.accumulatedAmountK    ?? p?.accumulated_k    ?? 0);
          if (yen) cumK = Math.round(yen / 1000);
          else if (k) cumK = k;
        }

        const $acc = document.getElementById('accumulated-amount-display');
        if ($acc) $acc.textContent = fmtYen(cumK * 1000); // 千円→円

        // ★ フォールバック：2031年基準の直算（rekinenのみ）で 0 を上書き（表示上のわかりやすさ）
        if ($acc && (cumK === 0) && window.__LAST_FUTURE_PAYLOAD?.rekinen) {
          try {
            const mb = toInt(document.querySelector('input[name="inherit_base_month"]')?.value, 12);
            const db = toInt(document.querySelector('input[name="inherit_base_day"]')?.value,   31);
            const death2031F = new Date(2031, Math.max(0, Math.min(11, mb-1)), Math.max(1, Math.min(31, db)));
            const k2031 = calcRekinenCumK(window.__LAST_FUTURE_PAYLOAD.rekinen, death2031F) || 0;            
            $acc.textContent = fmtYen(k2031 * 1000);
          } catch (_) {}
        }

        // === ▼ 贈与税額（過年度 + これからの全行）の合計を cal_tax[110] にセット ===
        try {
          const pastKojoK = Number(window.__LAST_FUTURE_PAYLOAD?.rekinen?.total?.kojo ?? 0);
          let futureKojoK = 0;
        
          // 将来行（1〜20）の贈与税額を加算
          const rows = getCalRows();
          for (const i of rows) {
            const el = document.querySelector(`input[name="cal_tax[${i}]"]`);
            const v = el ? toInt(el.value) : 0;
            futureKojoK += v;
          }
        
          // 合計を行110（合計行）に出力
          const totalKojo = pastKojoK + futureKojoK;
          setK('cal_tax', 110, totalKojo);
          
          // ▼ ここに追加！
          setK('cal_tax', 0, pastKojoK);    // ← 過年度欄（0行目）          
        

          // === ▼ 暦年・精算課税の金額合計も行110に出力する（過年度 + 将来行） ===

          // 暦年贈与：金額合計（過年度0行 + 将来1..20行）
          const pastCalAmountK =
            toInt(document.querySelector('input[name="cal_amount[0]"]')?.value ?? '', 0);
          let futureCalAmountK = 0;
          for (const i of rows) {
            const amtEl = document.querySelector(`input[name="cal_amount[${i}]"]`);
            futureCalAmountK += amtEl ? toInt(amtEl.value, 0) : 0;
          }
          setK('cal_amount', 110, pastCalAmountK + futureCalAmountK);

          // 精算課税：贈与額合計（過年度0行 + 将来1..20行）
          const setRows = getSetRows();
          const pastSetAmountK =
            toInt(document.querySelector('input[name="set_amount[0]"]')?.value ?? '', 0);
          let futureSetAmountK = 0;
          for (const i of setRows) {
            const amtEl = document.querySelector(`input[name="set_amount[${i}]"]`);
            futureSetAmountK += amtEl ? toInt(amtEl.value, 0) : 0;
          }
          setK('set_amount', 110, pastSetAmountK + futureSetAmountK);

          // 精算課税：税額合計（過年度0行 + 将来1..20行）
          const pastSetTax20K =
            toInt(document.querySelector('input[name="set_tax20[0]"]')?.value ?? '', 0);
          let futureSetTax20K = 0;
          for (const i of setRows) {
            const taxEl = document.querySelector(`input[name="set_tax20[${i}]"]`);
            futureSetTax20K += taxEl ? toInt(taxEl.value, 0) : 0;
          }
          setK('set_tax20', 110, pastSetTax20K + futureSetTax20K);
      
        } catch (err) {
          console.warn('[updateTopCounters] cal_tax[110] update skipped due to error:', err);
        }
        
      
      };

      // ★ 公開（applyFuturePayload から使用）
      window.updateTopCounters = updateTopCounters;


      /** ---------- ★ 過年度のみ：行0用の贈与加算累計額（暦年）を再計算してセット ---------- */
      const calcCumFromRekinen = (rekinen, death, baseMonth, baseDay) => {
        // ★ 'zoyo' / 'zouyo' の両対応
        if (!rekinen || !rekinen.year || !rekinen.month || !rekinen.day || (!rekinen.zoyo && !rekinen.zouyo)) return null;

        const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
        const [rangeStart, rangeEnd] = getLookbackRange(death);
        const threeYearsAgo = new Date(death); threeYearsAgo.setFullYear(threeYearsAgo.getFullYear()-3);
        let within3K = 0, over3K = 0;
        for (const k of Object.keys(rekinen.year)) {
          const y = Number(rekinen.year[k]  ?? 0);
          const m = Number(rekinen.month[k] ?? 0);
          const d = Number(rekinen.day[k]   ?? 0);

          const zMap = (rekinen.zoyo ?? rekinen.zouyo) || {};
          const z = Number(zMap[k] ?? 0); // 単位：千円想定
          
          
          
          if (!y || !m || !d || !z) continue;
          const dt = new Date(y, clamp(m-1,0,11), clamp(d,1,31));
          if (dt < rangeStart || dt > rangeEnd) continue;
          if (dt >= threeYearsAgo) within3K += z; else over3K += z;
        }
        const OVER3_DEDUCTION_K = 1000;
        return within3K + Math.max(0, over3K - OVER3_DEDUCTION_K);
      };
      
      
      // ▼ フォールバック合成用：rekinen を「3年以内／3年超」に分割して返す
      const calcRekinenSplit = (rekinen, death) => {
        const res = { within3K: 0, over3K: 0 };
        if (!rekinen || !rekinen.year || !rekinen.month || !rekinen.day) return res;
        const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
        const [rangeStart, rangeEnd] = getLookbackRange(death);
        const threeYearsAgo = new Date(death); threeYearsAgo.setFullYear(threeYearsAgo.getFullYear() - 3);
        const Z = (rekinen.zoyo ?? rekinen.zouyo) || {};
        for (const k of Object.keys(rekinen.year)) {
          const y = Number(rekinen.year[k]  ?? 0);
          const m = Number(rekinen.month[k] ?? 0);
          const d = Number(rekinen.day[k]   ?? 0);
          const z = Number(Z[k] ?? 0);
          if (!y || !m || !d || !z) continue;
          const dt = new Date(y, clamp(m-1,0,11), clamp(d,1,31));
          if (dt < rangeStart || dt > rangeEnd) continue;
          if (dt >= threeYearsAgo) res.within3K += z; else res.over3K += z;
        }
        return res;
      };
      window.calcRekinenSplit = calcRekinenSplit;

      

      // ★ 公開（applyFuturePayload から使用する保険計算）
      window.calcCumFromRekinen = calcCumFromRekinen;



      const recalcPastOnlyCum0 = () => {
      
        const rnSel = getCurrentRecipientNo();
      
      // ★仕様変更：
      // 行0（過年度分の合計）の「贈与加算累計額」は常に 0 を表示する。
      // 受贈者切替・再計算・payload再適用時も必ず 0 に固定する。
      setK('cal_cum', 0, 0);


      };
      // 外部でも呼べるように公開（初期化や手動更新用）
      window.recalcPastOnlyCum0 = recalcPastOnlyCum0;




    })(); // IIFEの終了
    
    
    
    
    
    
    

});     // DOMContentLoaded の終了

/** ---------- 保存・取得・反映（受贈者切替用） ---------- */
// 不要なreadCsrfの重複を削除
function readCsrf() {
  const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  return meta || '';
}

// ▼ shim: module 側で本実装が載る前でも参照できるように no-op を用意
window.setSaveStatus = window.setSaveStatus || function(){};


function resolveDataIdFuture() {
  const pane = document.getElementById('zouyo-tab-input04');
  const form = document.getElementById('zouyo-input-form');
  return (
    pane?.dataset?.dataId ||
    form?.dataset?.dataId ||
    (typeof window.APP_DATA_ID !== 'undefined' && window.APP_DATA_ID) ||
    pane?.querySelector('input[name="data_id"]')?.value ||
    form?.querySelector('input[name="data_id"]')?.value ||
    ''
  );
}



 // === shim: IIFE外でも使えるローカル数値ヘルパ（applyFuturePayload専用） ===
 const zNormalizeNum = (s) =>
   String(s ?? '')
     .replace(/[０-９]/g, (ch) => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0))
     .replace(/[，、．]/g, (ch) => (ch === '．' ? '.' : ','));
 const zToInt = (v, d = 0) => {
   const s = zNormalizeNum(v).replace(/,/g, '').replace(/[^\d\-]/g, '');
   const n = parseInt(s, 10);
   return Number.isFinite(n) ? n : d;
 };
 const zFmtK = (k) => (Number(k) || 0).toLocaleString();


function mapObjFormatted(obj, prefix) {
  if (!obj) return;

  // Iterate through each key in the object
  Object.keys(obj).forEach(k => {
    const idx = String(k);
    const v = obj[k];
    if (v == null) return; // Skip if the value is null or undefined

    const el = document.querySelector(`input[name="${prefix}[${idx}]"]`);
    if (el) el.value = formatValue(v);
  });
}

// Helper function to format the value as needed (e.g., adding commas for thousands)
function formatValue(value) {
  if (typeof value === 'number') {
    return value.toLocaleString(); // Format number with commas
  }
  return value;
}

function mapObjFormattedMinus(obj, prefix) {
  if (!obj) return;

  // Iterate through each key in the object
  Object.keys(obj).forEach(k => {
    const idx = String(k);
    const v = obj[k];
    if (v == null) return; // Skip if the value is null or undefined

    const el = document.querySelector(`input[name="${prefix}[${idx}]"]`);
    if (el) el.value = formatValueMinus(v);
  });
}

function formatValueMinus(value) {
  if (typeof value === 'number') {
    // Add a negative sign for negative numbers and format with commas
    return value < 0 ? `-${Math.abs(value).toLocaleString()}` : value.toLocaleString();
  }
  return value;
}


  // 未来タブ表示時のフォールバック
  window.z_onFutureTabShown = function () {
    const sel   = document.getElementById('future-recipient-no');
    if (!sel) return;

    // デフォルトで先頭 option を選択（明示的な値が無ければ）
    if (!sel.value && sel.options.length) {
      sel.selectedIndex = 0; // 先頭を選んでおく（上の placeholder を除くなら最初が "2" など）
    }

    const dataId = (document.querySelector('#zouyo-input-form [name="data_id"]')?.value || '').trim();
    const rn     = (sel.value || '').trim();

    if (!rn) {
      // ここで何も選ばれていない＝押し戻すだけ
      const $fetch = document.getElementById('past-fetch-status');
      if ($fetch) $fetch.textContent = '-';
      return;
    }


    const fetchUrl = sel.dataset.fetchUrl; // 例: /zouyo/future/fetch
    if (!fetchUrl) {
      console.warn('[future] missing fetch URL');
      return; // Exit early if the URL is missing
    }

    
    
    const qs = new URLSearchParams({ data_id: dataId, future_recipient_no: rn });
    const url = `${fetchUrl}?${qs}`;

    // 必要なら事前クリア（未来タブ自身の項目だけを対象にする）
    if (typeof window.clearFuturePlanInputs === 'function') {
      try { window.clearFuturePlanInputs(); } catch (e) {}
    }


    fetch(url, { method: 'GET', credentials: 'include', headers: { 'Accept': 'application/json' } })
      .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(json => {
        const $fetch = document.getElementById('past-fetch-status');
        if ($fetch) $fetch.textContent = 'OK';
        const dbg = document.getElementById('past-debug-json');
        if (dbg) { dbg.textContent = JSON.stringify(json, null, 2); }
        if (typeof window.applyFuturePayload === 'function') {
          window.applyFuturePayload(json);
        }
      })
      .catch(err => {
        console.error('[fetchAndFillFuture] error:', err);
        const $fetch = document.getElementById('past-fetch-status');
        if ($fetch) $fetch.textContent = `NG(${err.message || err})`;

        // 失敗時、視覚的な混在を避けるため最低限クリア
        setK('cal_amount', 0, 0); setK('cal_basic', 0, 0);
        setK('cal_after_basic', 0, 0);
        setK('cal_tax', 0, 0);
        
        //2025.11.10
        //setK('cal_cum', 0, 0);


      })
  };





// 受贈者選択時に年齢表示が出ない問題を回避：初期表示時にも計算（安全ガード付き）
document.addEventListener('DOMContentLoaded', () => {
  const rn0 = document.getElementById('future-recipient-no')?.value;
  if (rn0 && typeof window.recalcAges === 'function') {
    window.recalcAges(rn0);
  }
  
  // ★ 追加：初期表示時に将来行(1..20)の set_cum[i] も算出しておく
  try { recalcSettlementAllRows && recalcSettlementAllRows(); } catch(_) {}
  try { syncCalendarGiftLockBySettlement && syncCalendarGiftLockBySettlement(); } catch(_) {}
  try { recalcAllRowsCal && recalcAllRowsCal(); } catch(_) {}
 
   
});



document.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('future-recipient-no');
  if (!sel) return;
  // ★ 二重バインド防止（このブロックが複数あっても1回だけ attach）
  if (sel.dataset.ageHandlerBound === '1') return;
  sel.dataset.ageHandlerBound = '1';
  // ★ 変更前の受贈者番号を保持（change 直前の値を追跡）
  sel.dataset.prevRn = sel.value || '';
  sel.addEventListener('focus', () => { sel.dataset.prevRn = sel.value || ''; }, { passive:true });


  // ★ hidden[birth_*] が 0-0-0 のときに option の data-* から補完する
  const ensureBirthHidden = (recipientNo) => {
    const yEl = document.querySelector(`input[name="birth_year[${recipientNo}]"]`);
    const mEl = document.querySelector(`input[name="birth_month[${recipientNo}]"]`);
    const dEl = document.querySelector(`input[name="birth_day[${recipientNo}]"]`);
    const curY = Number(yEl?.value || 0);
    const curM = Number(mEl?.value || 0);
    const curD = Number(dEl?.value || 0);
    if (curY && curM && curD) return false; // 既に埋まっている
    const opt = sel.querySelector(`option[value="${recipientNo}"]`);
    const by = Number(opt?.dataset.by || 0);
    const bm = Number(opt?.dataset.bm || 0);
    const bd = Number(opt?.dataset.bd || 0);
    if (!by || !bm || !bd) return false; // option 側にも無ければ何もしない
    if (yEl) yEl.value = String(by);
    if (mEl) mEl.value = String(bm);
    if (dEl) dEl.value = String(bd);
    // age_dynamic 側の data-* も同期（見た目の一貫性）
    document.querySelectorAll('input[name="age_dynamic"]').forEach((el) => {
      el.dataset.birthYear  = String(by);
      el.dataset.birthMonth = String(bm);
      el.dataset.birthDay   = String(bd);
    });
    //console.debug('[future] ensured birth hidden by option dataset', { recipientNo, by, bm, bd });
    return true;
  };


  // ★ 受贈者選択の累計回数（全体/受贈者別）を記録するデバッグ用の箱
  window.__selCount = window.__selCount || {};        // 受贈者別: { '2': n, '3': n, ... }
  sel.dataset.changeCount = sel.dataset.changeCount || '0';  // 全体累計（selectのdata属性）

  // デバッグ: 2回目以降か、どの受贈者が何回目かを記録＆表示
  const markSelection = (recipientNo) => {
    // select全体の累計（1,2,3...）
    const total = (parseInt(sel.dataset.changeCount || '0', 10) + 1);
    sel.dataset.changeCount = String(total);
    // 受贈者別の累計（受贈者ごとに1,2,3...）
    const per = (window.__selCount[recipientNo] || 0) + 1;
    window.__selCount[recipientNo] = per;
    // 現在の hidden 生年月日と一緒にログ出力（年齢が固定化する場合の原因特定に有効）
    const birth = {
      y: document.querySelector(`input[name="birth_year[${recipientNo}]"]`)?.value || '(none)',
      m: document.querySelector(`input[name="birth_month[${recipientNo}]"]`)?.value || '(none)',
      d: document.querySelector(`input[name="birth_day[${recipientNo}]"]`)?.value || '(none)',
    };
    console.debug('[future] selection', {
      totalChangeCount: total,         // ← ここが2以上なら「2回目以降」
      perRecipientCount: per,          // ← 受贈者ごとの選択回数
      recipientNo,
      birthHidden: birth
    });
    
    
    // 画面にも簡易表示（必要なければコメントアウト可）
    /*
    let badge = document.getElementById('age-debug');
    if (!badge) {
      badge = document.createElement('div');
      badge.id = 'age-debug';
      badge.style.cssText = 'position:sticky;top:0;z-index:9999;background:#fff3cd;border:1px solid #ffeeba;padding:4px 6px;margin:6px 0;font-size:12px;';
      (document.getElementById('zouyo-tab-input04') || document.body).prepend(badge);
    }
    //badge.textContent = `選択回数: 全体 ${total}回 / 受贈者${recipientNo} は ${per}回目  (birth: ${birth.y}-${birth.m}-${birth.d})`;
    */
    
  };

  // ★ hidden → age_dynamic の data-* を都度同期するユーティリティ
  const syncAgeBirthDataFromHidden = (recipientNo) => {
    const by = Number(document.querySelector(`input[name="birth_year[${recipientNo}]"]`)?.value || 0);
    const bm = Number(document.querySelector(`input[name="birth_month[${recipientNo}]"]`)?.value || 0);
    const bd = Number(document.querySelector(`input[name="birth_day[${recipientNo}]"]`)?.value || 0);
    if (!by || !bm || !bd) return; // hidden が未設定なら触らない
    document.querySelectorAll('input[name="age_dynamic"]').forEach((el) => {
      el.dataset.birthYear  = String(by);
      el.dataset.birthMonth = String(bm);
      el.dataset.birthDay   = String(bd);
    });
  };


  // ★ 受贈者変更はこの単一ハンドラに集約
  sel.addEventListener('change', async (e) => {
    const newRn  = String(e.target.value || '');
    const prevRn = String(sel.dataset.prevRn || '');
    if (!newRn) return; // 未選択なら終了

    // ★ ここで「2回目以降か」が分かります
    markSelection(newRn);    

    
    try {

      // 1) 変更「前」の受贈者データを先に保存（行データを含めてサーバへ）
      const form = document.getElementById('zouyo-input-form');
      if (!form) throw new Error('フォームが見つかりません');


      const saveUrl = sel.dataset.saveUrl || form.action || '/zouyo/save';

      //window.setSaveStatus('saving', '前受贈者を保存中…');
      okPrev = prevRn
        ? await saveCurrentInputs(
            saveUrl,
            // ★ 受贈者切替時にヘッダ（基準贈与日：future_base_year/month/day など）も保存する
            { recipientOverride: prevRn, includeRows: true, includeHeader: true }
          )
        : true; // 初回など prevRn 空ならスキップ

      //window.setSaveStatus(okPrev ? 'success' : 'error', okPrev ? '保存OK' : '保存失敗');


      // 2) UI を新受贈者表示用にクリア（過年度行0は維持）
      if (typeof window.clearFuturePlanInputs === 'function') window.clearFuturePlanInputs();


      // 変更後の受贈者番号を prevRn として保持更新
      sel.dataset.prevRn = newRn;
      // hidden の birth_* が 0-0-0 なら、まず option の data-* で補完
      ensureBirthHidden(newRn);

      // 3) 新しい受贈者のデータを no-cache で取得 → 適用
      const baseUrl =
        document.getElementById('future-recipient-no')?.dataset.fetchUrl
        || '/zouyo/future/fetch';
      const dataId =
        resolveDataIdFuture()
        || document.querySelector('#zouyo-input-form [name="data_id"]')?.value
        || document.querySelector('#zouyo-tab-input04 input[name="data_id"]')?.value
        || '';
      const qs = new URLSearchParams();
      if (dataId) qs.set('data_id', String(dataId));

      if (newRn)  qs.set('future_recipient_no', String(newRn));
      // ★ キャッシュ回避トークン付与
      qs.set('_ts', String(Date.now()));
      const fetchUrl = `${baseUrl}?${qs.toString()}`;

      try {
        const response = await fetch(fetchUrl, {
          method: 'GET',

          // ★ キャッシュ無効化（古いJSONの再利用防止）
          cache: 'no-store',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache'
          },

        });
        if (!response.ok) {
          // 422 などの詳細を拾ってログに出す（デバッグ容易化）
          let detail = '';
          try { detail = JSON.stringify(await response.json()); } catch (_) {}
          //window.setSaveStatus('error', '取得失敗');

          throw new Error(`Failed to fetch future data (new rn=${newRn}) ${response.status} ${detail}`);
          
          
        }
        const data = await response.json();

        applyFuturePayload(data);                                 // ← 必ず最初に適用
        try { toggleCalTaxHeader(resolveTokureiFlag(newRn)); } catch (_) {}
        

        // 3.5) サーバが birth を返さない場合の保険
        ensureBirthHidden(newRn);
        syncAgeBirthDataFromHidden(newRn);
        if (typeof window.recalcAges === 'function') { window.recalcAges(newRn); }

        // 再計算と合計の同期
        try { updateTopCounters && updateTopCounters(); } catch (_) {}
        // ★ 追加：取得直後に精算課税の「加算累計（set_cum[i]）」を同期
        try { recalcSettlementAllRows && recalcSettlementAllRows(); } catch (_) {}
        // ★ 過年度（行0）の精算課税も確実に同期待ち
        try { window.recalcPastSettlementRow0(); } catch (_) {}

        //window.setSaveStatus('success', '切替完了');

      } catch (innerErr) {
        console.error('20251104_01 受贈者選択後の保存/取得に失敗:', innerErr);
        //window.setSaveStatus('error', '取得失敗');
        
        // 取得に失敗した場合も一度クリアしておく（視覚的な取り違い防止）
        setK('cal_amount', 0, 0);
        setK('cal_basic',  0, 0);
        setK('cal_after_basic', 0, 0);
        setK('cal_tax',    0, 0);
        setK('cal_cum',    0, 0);

      }

      // 4) 金額系の再計算（安全ガード）
      //2025.11.10
      //if (typeof recalcAllRowsCal === 'function') { recalcAllRowsCal(); }

    } catch (err) {
      console.error('20251104_02 受贈者変更ハンドラでエラー:', err);
      //window.setSaveStatus('error', '処理失敗');
    }
  });
});



      /** ---------- 税率表（安全フォールバック + 正規化） ---------- */
      window.GIFT_RATES = (function () {
        const gr = (typeof window.GIFT_RATES === 'object' && window.GIFT_RATES)
          ? window.GIFT_RATES
          : { tokurei: [], general: [] };
        const normalize = (rs) => (rs || []).map((r) => {
          let rate = Number(r.rate);
          if (rate > 1) rate = rate / 100.0; // 20 → 0.2
          return {
            lower: Number(r.lower || 0),
            upper: Number(r.upper || 0), // 0 は上限なし
            rate,
            quick: Number(r.deduction_amount || 0),
          };
        });
        gr.tokurei = normalize(gr.tokurei);
        gr.general = normalize(gr.general);
        return gr;
      })();


          // 入力中は計算しないようにする
          document.querySelectorAll('input[name^="cal_amount["]').forEach(input => {
          
          
            // 入力開始時にフラグを立てる
            input.addEventListener('focus', () => {
              isEditing = true;
            });
        
            // 入力終了時（フォーカスが外れた）に計算を実行
            input.addEventListener('blur', () => {
              isEditing = false;
              recalcAllRowsCal(); // 計算関数を呼び出す
              updateTopCounters();
              recalcPastOnlyCum0();
            });
        
            // EnterキーまたはTabキーを押したときに計算
            input.addEventListener('keydown', (e) => {
              if ((e.key === 'Enter' || e.key === 'Tab') && !isEditing) {
                recalcAllRowsCal(); // 計算関数を呼び出す
              }
            });
        
            // 入力変更を検知して計算しないようにする
            input.addEventListener('input', (e) => {
              // 編集中であれば計算を行わない
              if (isEditing) {
                return;
              }
            });
        
          });




      // 「基準贈与日（future_base_*）」変更でも cal_cum を再計算
      ['future_base_month','future_base_day'].forEach((n) => {
        const el = document.querySelector(`input[name="${n}"]`);
        if (el) el.addEventListener('blur', recalcAllRowsCal); // ← 関数参照を渡す（即時実行しない）
      });


      // ★行ごとの贈与日（gift_month/day）が変わったら再計算
      document.addEventListener('input', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement)) return;
        if (!/^gift_(month|day)\[\d+\]$/.test(t.name)) return;
        recalcAllRowsCal();
      });
      document.addEventListener('blur', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement)) return;
        if (!/^gift_(month|day)\[\d+\]$/.test(t.name)) return;
        recalcAllRowsCal();
      }, true);



        // --- 暦年：贈与額入力 → リアルタイム計算（blur でフォーマット）
        document.addEventListener('input', (e) => {
          const t = e.target;
          if (!(t instanceof HTMLInputElement)) return;
          if (!/^\s*cal_amount\[\d+\]\s*$/.test(t.name)) return;
        
          // 入力中は計算しないようにする
          if (isEditing) return;  // isEditingフラグがtrueの場合は計算しない
        
          t.value = normalizeNum(t.value).replace(/[^\d,]/g, '');
        });
        


        /**
         * グローバルな「全 input を整数フォーマットする」処理が
         * 文字列入力（氏名など）まで 0 にしてしまう原因だったため、
         * 対象を数値系の input に限定してバインドする。
         */

        function bindNumericBlurFormatter(el) {
          if (!el || el.dataset.blurFormatterBound === '1') return;
          el.dataset.blurFormatterBound = '1';
          el.addEventListener('focus', () => { isEditing = true; });
          el.addEventListener('blur', () => {

            isEditing = false;

            // 数値系のみフォーマット（氏名などの文字列には一切触れない）
            // ★ 修正：空欄は '' のままにし、0 を入れない
            const raw = String(el.value ?? '').trim();
            if (raw === '') {
              el.value = '';
            } else if (el.name === 'future_base_year') {
              // ★ future_base_year は「西暦年」なので 3 桁カンマは付けない
              //   全角→半角＋数字以外除去のみ行い、そのまま設定
              const normalized = normalizeNum(raw).replace(/[^\d\-]/g, '');
              el.value = normalized;
            } else {
              el.value = fmtK(toInt(raw));
            }


            // 必要な場合のみ再計算も行う
            if (
              /^\s*(cal_amount|set_amount|gift_month|gift_day)\[\d+\]\s*$/.test(el.name) ||
              ['future_base_year','future_base_month','future_base_day',
               'inherit_base_month','inherit_base_day',
               'header_year','header_month','header_day'].includes(el.name)
            ) {
              try { recalcAllRowsCal(); } catch (_) {}
              try { updateTopCounters && updateTopCounters(); } catch (_) {}
              try { recalcPastOnlyCum0 && recalcPastOnlyCum0(); } catch (_) {}
            }
          }, true);
        }


        // 初期表示時：数値系フィールドだけにバインド
        document.querySelectorAll(
          'input[name^="cal_amount["],input[name^="set_amount["],'+
          'input[name^="gift_month["],input[name^="gift_day["],'+
          'input[name="future_base_year"],input[name="future_base_month"],input[name="future_base_day"],'+
          'input[name="inherit_base_month"],input[name="inherit_base_day"],'+
          'input[name="header_year"],input[name="header_month"],input[name="header_day"]'
        ).forEach(bindNumericBlurFormatter);


        // 動的にフォーカスされた要素が数値系なら、その場で一度だけバインド
        document.addEventListener('focusin', (e) => {
          const t = e.target;
          if (!(t instanceof HTMLInputElement)) return;
          if (
            /^(cal_amount|set_amount|gift_month|gift_day)\[\d+\]$/.test(t.name) ||
            ['future_base_year','future_base_month','future_base_day',
             'inherit_base_month','inherit_base_day',
             'header_year','header_month','header_day'].includes(t.name)
          ) {
            bindNumericBlurFormatter(t);
          }
        });


        // --- blur で計算（★空欄は 0 にしない）
        document.addEventListener('blur', (e) => {
          const t = e.target;
          if (!(t instanceof HTMLInputElement)) return;
          if (!/^\s*cal_amount\[\d+\]\s*$/.test(t.name)) return;
        
          const raw = String(t.value ?? '').trim();
          if (raw === '') {
            t.value = '';
          } else {
            t.value = fmtK(toInt(raw));  // 値があるときだけフォーマット
          }

          recalcAllRowsCal();
          updateTopCounters();
          recalcPastOnlyCum0();          
        }, true);

      // --- 精算課税：贈与額入力（編集中は再計算しない）／blur時のみ再計算
      document.addEventListener('input', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement)) return;
        if (!/^\s*set_amount\[\d+\]\s*$/.test(t.name)) return;
        // 入力中は見た目の正規化のみ。再計算はしない（blurで実施）
        t.value = normalizeNum(t.value).replace(/[^\d,]/g, '');
      });
      document.addEventListener('blur', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement)) return;
        if (!/^\s*set_amount\[\d+\]\s*$/.test(t.name)) return;
        t.value = fmtK(toInt(t.value));
        recalcSettlementAllRows();
        syncCalendarGiftLockBySettlement();
        recalcAllRowsCal();
        updateTopCounters();
        recalcPastOnlyCum0();        
      }, true);




// collectRowData 関数を定義
function collectRowData(prefix, numRows) {
    const data = [];
    for (let i = 0; i < numRows; i++) {
        const rowData = {};

        // 各列（cal_amount, cal_basic, cal_after_basic など）のデータを収集
        const columns = [
            'cal_amount', 'cal_basic', 'cal_after_basic', 'cal_tax', 'cal_cum',
            'set_amount', 'set_basic110', 'set_after_basic', 'set_after_25m', 'set_tax20', 'set_cum'
        ];

        // 各列のinputフィールドからデータを収集
        columns.forEach((column) => {
            const input = document.querySelector(`input[name="${column}[${i}]"]`);
            if (input) {
                rowData[column] = input.value; // 入力された値を格納
            }
        });

        data.push(rowData); // 収集した行データをdata配列に追加
    }

    return data; // 収集したデータを返す
}






function applyFuturePayload(p) {
  // 期待構造（例）:
  // {
  //   header: { year, month, day },
  //   recipient_no,
  //   plan: {
  //     cal_amount: {i: kvalue, ...}, cal_basic:{}, cal_after_basic:{}, cal_tax:{}, cal_cum:{},
  //     set_amount: {...}, set_basic110:{}, set_after_basic:{}, set_after_25m:{}, set_tax20:{}, set_cum:{},
  //     gift_month: {...}, gift_day: {...}
  //   },
  //   birth: { year, month, day } // 任意
  // }
  if (!p || typeof p !== 'object') return;

  // ヘッダ（日付）
  if (p.header) {
    if (p.header.year  != null) setInputValue('future_base_year',  p.header.year);
    if (p.header.month != null) setInputValue('future_base_month', p.header.month);
    if (p.header.day   != null) setInputValue('future_base_day',   p.header.day);
  }

  // 行データ
  if (p.plan && typeof p.plan === 'object') {
    // 共通: object の値を input[name="prefix[idx]"] に流し込む
    const mapObj = (obj, prefix) => {
      if (!obj) return;
      Object.keys(obj).forEach(k => {
        const idx = String(k);
        const v   = obj[k];
        if (v == null) return; // nullやundefinedの値は無視
        const el  = document.querySelector(`input[name="${prefix}[${idx}]"]`);
        if (el) el.value = v ?? '';
      });
    };

    mapObjFormatted(p.plan.cal_amount, 'cal_amount');
    mapObjFormattedMinus(p.plan.cal_basic, 'cal_basic');  // 1100 → -1,100
    mapObjFormatted(p.plan.cal_after_basic, 'cal_after_basic');
    mapObjFormatted(p.plan.cal_tax, 'cal_tax');
    mapObjFormatted(p.plan.cal_cum, 'cal_cum');
    mapObjFormatted(p.plan.set_amount, 'set_amount');
    mapObjFormattedMinus(p.plan.set_basic110, 'set_basic110');  // 1100 → -1,100
    mapObjFormatted(p.plan.set_after_basic, 'set_after_basic');
    mapObjFormatted(p.plan.set_after_25m, 'set_after_25m');
    mapObjFormatted(p.plan.set_tax20, 'set_tax20');
    mapObj(p.plan.gift_month, 'gift_month');
    mapObj(p.plan.gift_day, 'gift_day');
    
    // ★ 追加：ペイロード適用直後に精算課税の行累計（set_cum[i]）を必ず再計算
    try { recalcSettlementAllRows && recalcSettlementAllRows(); } catch (_) {}
    try { syncCalendarGiftLockBySettlement && syncCalendarGiftLockBySettlement(); } catch (_) {}
    try { recalcAllRowsCal && recalcAllRowsCal(); } catch (_) {}
 

    
  }

  // 直近ペイロード保持（フォールバック表示用）
  window.__LAST_FUTURE_PAYLOAD = p;



  // 現在の受贈者番号（サーバ返却があればそれを最優先）
  const rnNow = String(p?.recipient_no ?? getCurrentRecipientNo() ?? '').trim();

  // 過年度贈与データを保持する関数（受贈者単位でマージ置換）
  const pickPastArray = (past) => {
    if (!past) return null;
  
    // settlement_entries を追加して過年度データを取り出す
    if (Array.isArray(past.calendar_entries)) {
      return past.calendar_entries.filter(entry =>
        entry.gift_year && entry.gift_month && entry.gift_day && entry.amount_thousand
      );
    }
  
    if (Array.isArray(past.settlement_entries)) {
      return past.settlement_entries.filter(entry => {
        return entry.gift_year && entry.gift_month && entry.gift_day && entry.amount_thousand; // 必要な項目がすべて揃っている場合のみ返す
      });
    }
  
    // 他のキー（例: calendar_entries, past_gifts）が存在すればそれらを試す
    const possibleKeys = ['calendar', 'list', 'items', 'gifts', 'past_gifts', 'calendar_entries'];
    for (const key of possibleKeys) {
      if (Array.isArray(past[key])) return past[key];
    }
  
    // past 自体が配列の場合も対応
    if (Array.isArray(past)) return past;
  
    return null;
  };

  const toKyen = (obj) => {
    const k = Number(obj.amount_thousand ?? obj.amount_k ?? obj.k ?? obj.amountK ?? NaN);
    if (Number.isFinite(k)) return k;
    const yen = Number(obj.amount_yen ?? obj.amountYen ?? obj.amount ?? NaN);
    if (Number.isFinite(yen)) return Math.round(yen / 1000);
    return 0;
  };

  
    // past データがある場合のみ PAST_GIFTS を更新
    if (p.past) {
      const pastData = pickPastArray(p.past);

      //console.log('2025_11_07_01 [applyFuturePayload] picked past data:', pastData);  // 過年度データの構造を確認

      //console.log('Server Response:', p.past);

      if (Array.isArray(pastData)) {
        window.PAST_GIFTS = pastData.map(g => {
          return {
            y: g.gift_year,
            m: g.gift_month,
            d: g.gift_day,
            k: toKyen(g), // 贈与金額（千円単位）
            rn: rnNow, // 受贈者番号をセット
          };
        }).filter(g => g.y && g.m && g.d && g.k); // 無効データを除外
      }
    }

  //console.log('2025_11_07_02 Updated PAST_GIFTS:', window.PAST_GIFTS);

  // ここで過年度データが更新された後に再計算を実行
  recalcAllRowsCal(); // 全行再計算
  updateTopCounters(); // 上部カウンタを更新
  recalcPastOnlyCum0(); // 行0の累計も再計算
  
  // ★ 追加：将来行(1..20)の精算課税 set_cum[i] もこのタイミングで同期
  try { recalcSettlementAllRows && recalcSettlementAllRows(); } catch (_) {}
  try { syncCalendarGiftLockBySettlement && syncCalendarGiftLockBySettlement(); } catch (_) {}
  try { recalcAllRowsCal && recalcAllRowsCal(); } catch (_) {}  


  // ▼ 重要：past がキーとして存在したら「該当受贈者の PAST_GIFTS を必ず置換」
  if (!Array.isArray(window.PAST_GIFTS)) window.PAST_GIFTS = [];
  if (Object.prototype.hasOwnProperty.call(p || {}, 'past')) {
    const src = pickPastArray(p.past);
    const others = (Array.isArray(window.PAST_GIFTS) ? window.PAST_GIFTS : [])
      .filter(x => String(x?.rn ?? '') !== rnNow);
    let mapped = [];
    if (Array.isArray(src) && src.length > 0) {
      mapped = src.map((g) => {
        const y = Number(g.gift_year ?? g.year ?? g.y ?? 0);
        const m = Number(g.gift_month ?? g.month ?? g.m ?? 0);
        const d = Number(g.gift_day ?? g.day ?? g.d ?? 0);
        const k = toKyen(g);
        return { y, m, d, k, rn: rnNow };
      }).filter(g => g.y && g.m && g.d && g.k);
    }


   if (mapped.length > 0) {
     window.PAST_GIFTS = others.concat(mapped);
     console.debug('[applyFuturePayload] PAST_GIFTS replaced for rn=', rnNow, ' size=', mapped.length);
   } else {
     console.warn('[applyFuturePayload] skip replacing PAST_GIFTS: mapped is empty or invalid');
   }


    
    
    console.debug('[applyFuturePayload] PAST_GIFTS replaced for rn=', rnNow, ' size=', mapped.length);
  }


  // データ妥当性（k,y,m,d 基準で軽く検証）
  if (Array.isArray(window.PAST_GIFTS)) {
    window.PAST_GIFTS.forEach((gift, index) => {
      const ok = Number(gift?.k) && Number(gift?.y) && Number(gift?.m) && Number(gift?.d);
      if (!ok) console.warn(`Invalid gift data at index ${index}`, gift);
    });
  }

  // 受贈者変更時の処理（再計算を確実に行うための修正）
  //2025.11.10
  //try { recalcAllRowsCal(); } catch (_) {}  // 受贈者変更後に再計算
  //try { recalcPastOnlyCum0(); } catch (_) {}  // 行0の累計も再計算


  // rekinen（過年度）反映：データがある時のみセット
  if (p?.rekinen && p.rekinen.year && p.rekinen.month && p.rekinen.day) {
    const zMap = (p.rekinen.zoyo ?? p.rekinen.zouyo) || {};
    const totalK = Object.values(zMap).reduce((s,v)=> s + (+v||0), 0);
    if (Number.isFinite(totalK)) setK('cal_amount', 0, totalK);

    // ★仕様変更：行0（過年度合計）の「贈与加算累計額」は常に 0
    setK('cal_cum', 0, 0);

  } else {
    // ★ データが無い場合は行0をクリア（前受贈者の残骸を消す）
    setK('cal_amount', 0, 0);
    setK('cal_basic',  0, 0);
    setK('cal_after_basic', 0, 0);
    setK('cal_tax',    0, 0);
    setK('cal_cum',    0, 0);
  }
  
  // 再計算完了後に結果を表示
  try { updateTopCounters && updateTopCounters(); } catch (_) {}
  try { recalcPastOnlyCum0 && recalcPastOnlyCum0(); } catch (_) {}


  // ★ 追加：受贈者切替直後の見え方を安定させるため、再度 set_cum[i] を確定
  try { recalcSettlementAllRows && recalcSettlementAllRows(); } catch (_) {}
  try { syncCalendarGiftLockBySettlement && syncCalendarGiftLockBySettlement(); } catch (_) {}
  try { recalcAllRowsCal && recalcAllRowsCal(); } catch (_) {}

  // 受贈者の誕生日（サーバが返す場合）→ hidden を上書き/upsert + 年齢欄 data-* を更新
  if (p.birth) {
    const rn = document.getElementById('future-recipient-no')?.value || '';
    const upsertHidden = (key, val) => {
      let el = document.querySelector(`input[name="${key}[${rn}]"]`);
      if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = `${key}[${rn}]`;
        (document.getElementById('zouyo-tab-input04') || document.body).appendChild(el);
      }
      el.value = val ?? '';
    };
    if (p.birth.year != null) upsertHidden('birth_year', p.birth.year);
    if (p.birth.month != null) upsertHidden('birth_month', p.birth.month);
    if (p.birth.day != null) upsertHidden('birth_day', p.birth.day);
    document.querySelectorAll('input[name="age_dynamic"]').forEach((el) => {
      if (p.birth.year != null) el.dataset.birthYear = String(p.birth.year);
      if (p.birth.month != null) el.dataset.birthMonth = String(p.birth.month);
      if (p.birth.day != null) el.dataset.birthDay = String(p.birth.day);
    });

    if (typeof window.recalcAges === 'function') {
      window.recalcAges(rn);
    }
    
    // ★ 反映直後にカウンタと行0の累計を同期（適用→再計算の順序を保証）
    try { updateTopCounters && updateTopCounters(); } catch (_) {}
    try { recalcPastOnlyCum0 && recalcPastOnlyCum0(); } catch (_) {}
    // ★ 過年度（行0）の精算課税も同期
    try { recalcPastSettlementRow0 && recalcPastSettlementRow0(); } catch (_) {}
     
  }
  
  
  
  
  // PAST_GIFTS 更新後に再描画を明示
  try { updateTopCounters(); } catch (_) {}
  try { recalcPastOnlyCum0(); } catch (_) {}

  // ★ 過年度（行0）の精算課税（DOM未配置でもペイロードから復元）を同期
  try { recalcPastSettlementRow0 && recalcPastSettlementRow0(); } catch (_) {}


    if (p.rekinen) {
      const zMap = (p.rekinen.zoyo ?? p.rekinen.zouyo) || {};
      const totalK = Object.values(zMap).reduce((sum,v)=> sum + (+v||0), 0);
      const nYears = Object.values(p.rekinen.year || {}).filter(y => !!y).length;
      const basicK = nYears * 1100;
      const afterK = Math.max(totalK - basicK, 0);
    
      const rn = getCurrentRecipientNo();
      const isTokurei = resolveTokureiFlag(rn); // ←★ここ重要！
      const kojoK = calcGiftTaxKyen(afterK, isTokurei); // ←★JS関数で贈与税額を算出！
    
      setK('cal_amount', 0, totalK);
      setK('cal_basic', 0, -basicK);
      setK('cal_after_basic', 0, afterK);
      setK('cal_tax', 0, kojoK);  // ←今度は正しく表示される！
      setK('cal_cum', 0, 0);      // ←過年度分の合計の贈与加算累計額は常に 0      
    } else {
      // ★ データが無い場合は行0をクリア（前受贈者の残骸を消す）
      setK('cal_amount', 0, 0);
      setK('cal_basic',  0, 0);
      setK('cal_after_basic', 0, 0);
      setK('cal_tax',    0, 0);
      setK('cal_cum',    0, 0);
    }

  
  // 過年度データの更新（精算課税）：
  // - 対象受贈者 rn のレコードだけ置換
  // - フィールド t:'seisan' を付与して暦年と区別できるようにする（後方互換のため t 無しも許容）
  if (p?.past?.settlement_entries) {
    const rnNow = String(p?.recipient_no ?? getCurrentRecipientNo() ?? '').trim();
    const mapped = p.past.settlement_entries
      .filter(e => e?.gift_year && e?.gift_month && e?.gift_day && (e?.amount_thousand ?? e?.amount_k ?? e?.amount_yen))
      .map(e => ({
        y: Number(e.gift_year),
        m: Number(e.gift_month),
        d: Number(e.gift_day),
        k: Number(e.amount_thousand ?? (Number(e.amount_yen)||0)/1000 ?? e.amount_k ?? 0),
        rn: rnNow,
        t: 'seisan', // ★ 追加：精算課税フラグ
      }))
      .filter(g => g.y && g.m && g.d && g.k);

    const others = (Array.isArray(window.PAST_GIFTS) ? window.PAST_GIFTS : [])
      .filter(x => String(x?.rn ?? '') !== rnNow || (x?.t ?? '') !== 'seisan');
    window.PAST_GIFTS = others.concat(mapped);
    console.debug('[applyFuturePayload] settlement_entries merged (t=seisan) rn=', rnNow, ' size=', mapped.length);
  }


  // 過年度の計算
  recalcPastSettlementRow0(); // ここで再計算をトリガー




}  // ここで関数を閉じる



// 外部から呼べるように公開
window.applyFuturePayload = applyFuturePayload;


function setInputValue(name, v) {
  const el = document.querySelector(`input[name="${name}"]`);
  if (el) el.value = v ?? '';
}


/**
 * ============================================================
 * 家族構成等タブ(title.blade)で氏名を追加・変更したら、
 * 「これからの贈与」タブの受贈者プルダウンをその場で同期する
 * 対象: 2〜10行目
 * ============================================================
 */
function futureFindLiveField(name) {
  return document.querySelector(
    `input[name="${name}"]:not([type="hidden"]), select[name="${name}"], textarea[name="${name}"]`
  );
}

function futureReadFieldValue(name, defaultValue = '') {
  const liveEl = futureFindLiveField(name);
  const el = liveEl || document.querySelector(`[name="${name}"]`);
  if (!el) return defaultValue;

  if (el instanceof HTMLInputElement) {
    if (el.type === 'checkbox') {
      return el.checked ? (el.value || '1') : '0';
    }
    if (el.type === 'radio') {
      const checked = document.querySelector(`input[type="radio"][name="${name}"]:checked`);
      return checked ? checked.value : defaultValue;
    }
    return el.value ?? defaultValue;
  }

  if (el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement) {
    return el.value ?? defaultValue;
  }

  return defaultValue;
}

function futureUpsertHidden(name, value) {
  const pane = document.getElementById('zouyo-tab-input04') || document.body;
  let el =
    pane.querySelector(`input[type="hidden"][name="${name}"]`) ||
    document.querySelector(`input[type="hidden"][name="${name}"]`);

  if (!el) {
    el = document.createElement('input');
    el.type = 'hidden';
    el.name = name;
    pane.appendChild(el);
  }

  el.value = value ?? '';
}

function syncFutureRecipientSelectFromFamily() {
  const sel = document.getElementById('future-recipient-no');
  if (!sel) return;

  const currentValue = String(sel.value || '').trim();
  const rows = [];

  for (let no = 2; no <= 10; no++) {
    const name = String(futureReadFieldValue(`name[${no}]`, '')).trim();
    const tokurei = String(futureReadFieldValue(`tokurei_zouyo[${no}]`, '0')).trim() === '1' ? '1' : '0';
    const by = toInt(futureReadFieldValue(`birth_year[${no}]`, ''), 0);
    const bm = toInt(futureReadFieldValue(`birth_month[${no}]`, ''), 0);
    const bd = toInt(futureReadFieldValue(`birth_day[${no}]`, ''), 0);

    // future_zouyo 側 hidden も最新値へ同期
    futureUpsertHidden(`tokurei_zouyo[${no}]`, tokurei);
    futureUpsertHidden(`birth_year[${no}]`,  by || '');
    futureUpsertHidden(`birth_month[${no}]`, bm || '');
    futureUpsertHidden(`birth_day[${no}]`,   bd || '');

    if (name === '') continue;

    rows.push({
      no,
      name,
      tokurei,
      by,
      bm,
      bd,
    });
  }

  sel.innerHTML = '';

  rows.forEach((row) => {
    const opt = document.createElement('option');
    opt.value = String(row.no);
    opt.textContent = row.name;
    opt.dataset.tokurei = row.tokurei;
    opt.dataset.by = String(row.by || 0);
    opt.dataset.bm = String(row.bm || 0);
    opt.dataset.bd = String(row.bd || 0);
    sel.appendChild(opt);
  });

  const nextValue = rows.some((row) => String(row.no) === currentValue)
    ? currentValue
    : (rows[0] ? String(rows[0].no) : '');

  if (nextValue !== '') {
    sel.value = nextValue;
  } else {
    sel.selectedIndex = -1;
  }

  sel.dataset.prevRn = nextValue;

  try {
    if (nextValue) {
      toggleCalTaxHeader(resolveTokureiFlag(nextValue));
      if (typeof window.recalcAges === 'function') {
        window.recalcAges(nextValue);
      }
    }
  } catch (_) {}

  // 未来タブが表示中で、選択値が変わった場合は画面内容も同期
  const pane = document.getElementById('zouyo-tab-input04');
  const isFutureTabVisible = !!pane && (pane.classList.contains('show') || pane.classList.contains('active'));
  if (isFutureTabVisible && currentValue !== nextValue) {
    if (nextValue && typeof window.fetchAndFillFuture === 'function') {
      window.fetchAndFillFuture(nextValue);
    } else if (typeof window.clearFuturePlanInputs === 'function') {
      window.clearFuturePlanInputs();
    }
  }
}

window.syncFutureRecipientSelectFromFamily = syncFutureRecipientSelectFromFamily;

let futureRecipientSyncTimer = null;
function queueFutureRecipientSync() {
  if (futureRecipientSyncTimer) {
    clearTimeout(futureRecipientSyncTimer);
  }
  futureRecipientSyncTimer = setTimeout(() => {
    futureRecipientSyncTimer = null;
    syncFutureRecipientSelectFromFamily();
  }, 0);
}

function isFutureRecipientSourceField(target) {
  if (!(target instanceof Element)) return false;
  const name = target.getAttribute('name') || '';
  const m = name.match(/^(name|tokurei_zouyo|birth_year|birth_month|birth_day)\[(\d+)\]$/);
  if (!m) return false;

  const no = Number(m[2]);
  return no >= 2 && no <= 10;
}

document.addEventListener('DOMContentLoaded', () => {
  queueFutureRecipientSync();
});

document.addEventListener('input', (e) => {
  if (!isFutureRecipientSourceField(e.target)) return;
  queueFutureRecipientSync();
}, true);

document.addEventListener('change', (e) => {
  if (!isFutureRecipientSourceField(e.target)) return;
  queueFutureRecipientSync();
}, true);
 
 
</script>



<script type="module">

// fetchAndFillFuture をグローバルに公開（module スコープ外からも呼べるように）
window.fetchAndFillFuture = async (recipientNo) => {
  try {
    // ▼ ベースURLは select の data-fetch-url を優先し、なければ既定パス
    const sel = document.getElementById('future-recipient-no');
    const baseUrl = sel?.dataset?.fetchUrl || '/zouyo/future/fetch';
    // data_id はフォーム（またはパネル）から取得
    const dataId =
      document.querySelector('#zouyo-input-form [name="data_id"]')?.value ||
      document.querySelector('#zouyo-tab-input04 input[name="data_id"]')?.value ||
      '';
    // ▼ クエリを構築（サーバ側の想定: future_recipient_no / data_id）
    const qs = new URLSearchParams();
    if (dataId) qs.set('data_id', String(dataId));
    if (recipientNo) qs.set('future_recipient_no', String(recipientNo));
    const fetchUrl = `${baseUrl}?${qs.toString()}`;

    const response = await fetch(fetchUrl, {

      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },

    });

    if (!response.ok) {
      throw new Error('Failed to fetch future data');
    }

    const data = await response.json();
    // データ適用と PAST_GIFTS の更新は applyFuturePayload 側で一元管理
    applyFuturePayload(data);
    // 直後に上部カウンタと過年度(行0)を同期
    try { updateTopCounters && updateTopCounters(); } catch (_) {}
    try { recalcPastOnlyCum0 && recalcPastOnlyCum0(); } catch (_) {}
    // ★ 過年度（行0）の精算課税も同期
    try { recalcPastSettlementRow0 && recalcPastSettlementRow0(); } catch (_) {}


  } catch (error) {
    console.error('Error fetching future data:', error);
    alert('Error fetching recipient data. Please try again.');
  }
};
  
  

// 保存結果のUI更新（グローバル公開）
window.setSaveStatus = function (state, text) {
  const el = document.getElementById('future-save-status');
  if (!el) return;
  el.classList.remove('is-saving','is-success','is-error','is-idle');
  if (state === 'saving')  el.classList.add('is-saving');
  else if (state === 'success') el.classList.add('is-success');
  else if (state === 'error')   el.classList.add('is-error');
  else el.classList.add('is-idle');
  el.textContent = text || '-';
}

// 保存実行：行データの同時保存にも対応。recipientOverride/flags で挙動を制御可能
// options: { recipientOverride?: string, includeRows?: boolean=true, includeHeader?: boolean=true }
window.saveCurrentInputs = async function (saveUrl, options = {}) {
  const {
    recipientOverride = null,
    includeRows = true,
    includeHeader = true,
  } = options;
 
  try {
    const form  = document.getElementById('zouyo-input-form');
    const token = document.querySelector('input[name="_token"]').value;

    // ★ “必要キーのみ”を新規作成（堅牢に data_id を解決）
    const fd = new FormData();
    fd.set('_token', token);
    fd.set('autosave', '1');
    const dataIdFromForm   = form?.querySelector('input[name="data_id"]')?.value || '';
    const dataIdFromGlobal = document.querySelector('input[name="data_id"]')?.value || '';
    const dataIdFromFn     = (typeof resolveDataIdFuture === 'function') ? (resolveDataIdFuture() || '') : '';
    const dataId = String(dataIdFromForm || dataIdFromFn || dataIdFromGlobal || '').trim();
    if (!dataId) { throw new Error('data_id missing'); }
    fd.set('data_id', dataId);

    // 受贈者：override があればそれを、無ければ現在のセレクト値
    const rnDOM = document.getElementById('future-recipient-no')?.value || '';
    const rn = (recipientOverride ?? rnDOM) || '';
    if (!rn) throw new Error('future_recipient_no missing');
    fd.set('future_recipient_no', rn);

    if (includeHeader) {
      const headKeys = [
        'future_base_year','future_base_month','future_base_day',
        'inherit_base_month','inherit_base_day','customer_name'
      ];
      headKeys.forEach((k) => {
        const el = document.querySelector(`input[name="${k}"]`);
        if (el && el.value !== '') fd.set(k, el.value);
      });
    }

    if (includeRows) {
      for (let i = 1; i <= 20; i++) {
        const ca = document.querySelector(`input[name="cal_amount[${i}]"]`)?.value ?? '';
        const sa = document.querySelector(`input[name="set_amount[${i}]"]`)?.value ?? '';
        if (ca !== '') fd.set(`cal_amount[${i}]`, String(toInt(ca)));
        if (sa !== '') fd.set(`set_amount[${i}]`, String(toInt(sa)));
        const gyEl = document.querySelector(`input[name="gift_year[${i}]"]`);
        if (gyEl && gyEl.value !== '') fd.set(`gift_year[${i}]`, gyEl.value);
      }
    }

    // API 送信（レスポンス詳細もデバッグ出力）
    const response = await fetch(saveUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: fd,
    });

    if (!response.ok) {
      // 422 の詳細をできるだけログに残す
      let detail = '';
      try { detail = await response.text(); } catch (_) {}
      throw new Error(`HTTP ${response.status}${detail ? ` => ${detail}` : ''}`);
    }
    const json = await response.json().catch(() => ({}));
    const ok = (json && (json.ok === true || json.status === 'ok'));
    return !!ok;

  } catch (error) {
    console.error('保存中にエラーが発生しました:', error);
    return false;
  }
}




// （削除）重複のグローバル change リスナーは競合の原因になるため撤去



// 受贈者変更時に過年度データを保存
const savePastData = (recipientNo) => {
  const saveUrl = '/path/to/save/endpoint'; // 保存先のURL
  const pastData = {
    recipient_no: recipientNo,
    past_gifts: window.PAST_GIFTS,  // 過年度贈与データを送信
  };

  fetch(saveUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    },
    body: JSON.stringify(pastData),
  })
    .then(response => response.json())
    .then(data => {
      console.log('過年度データ保存成功:', data);
    })
    .catch(error => {
      console.error('過年度データ保存エラー:', error);
    });
};







// ==============================
// ★ 未来タブ専用クリア関数（過年度は触らない & 行0は絶対に消さない）
// ==============================
window.clearFuturePlanInputs = function () {
  // 未来タブの「将来行(1..20)」だけを初期化する。
  // 行0（過年度合計）と合算表示はサーバ/PAST_GIFTSから即時に再計算されるため消さない。
  const prefixes = [
    'cal_amount','cal_basic','cal_after_basic','cal_tax','cal_cum',
    'set_amount','set_basic110','set_after_basic','set_after_25m','set_tax20','set_cum',
    'gift_month','gift_day'
  ];
  const maxRows = 120; // 念のため広めに（1..20 と 110 などを包含）

  // 将来行のみ（1..maxRows）をクリア。行0は触らない！
  for (let i = 1; i <= maxRows; i++) {
    prefixes.forEach(p => {

      const el = document.querySelector(`input[name="${p}[${i}]"]`);
      // 念のため age_dynamic は一切触らない（将来仕様変更で name が配列化しても安全）
      if (el && el.name !== 'age_dynamic') {
        el.value = '';
      }


    });
  }

  // ★ 保険：クリア直後に年齢を再計算して空戻りの見え方を回避
  try { if (typeof window.recalcAges === 'function') window.recalcAges(getCurrentRecipientNo()); } catch (_) {}
  // 上部カウンタや行0の再描画は「適用後(applyFuturePayload)」で行う


};


// 互換：旧名で呼ばれても ReferenceError にならないように別名を用意
window.clearFutureInputs = window.clearFuturePlanInputs;

</script>

<script>
// （削除）重複していた change リスナーは一本化する（下の DOMContentLoaded 内に集約）


  // 受贈者選択時に年齢表示が出ない問題を回避するため、初期状態でも年齢を表示
  (function () {
    const rn0 = document.getElementById('future-recipient-no')?.value;
    if (rn0 && typeof window.recalcAges === 'function') {
      window.recalcAges(rn0);
    }

    // ★ 初期表示時にも 0 行目の精算課税を一度同期
    try { recalcPastSettlementRow0 && recalcPastSettlementRow0(); } catch(_) {}
    
  })();
</script>

<script>
 // 贈与額入力後、未入力の贈与日に基準日を補完する処理
 document.querySelectorAll('input[name^="cal_amount["]').forEach((input) => {
   input.addEventListener('blur', (e) => {
     const name = e.target.name;
     const match = name.match(/^cal_amount\[(\d+)\]$/);
     if (!match) return;
     const i = parseInt(match[1], 10);
     if (i < 1 || i > 20) return; // 1〜20行のみ対象（過年度行0除外）

     const gm = document.querySelector(`input[name="gift_month[${i}]"]`);
     const gd = document.querySelector(`input[name="gift_day[${i}]"]`);
     const gy = document.querySelector(`input[name="gift_year[${i}]"]`);     

     // 未設定時のみ補完（空欄のときだけ）
     if ((gm?.value === '' || gm?.value === '0') || (gd?.value === '' || gd?.value === '0')) {
       const baseMonth = toInt(document.querySelector('input[name="future_base_month"]')?.value, 12);
       const baseDay   = toInt(document.querySelector('input[name="future_base_day"]')?.value, 31);
       const baseYear  = toInt(document.querySelector('input[name="future_base_year"]')?.value, 2024);
       if (gm && gm.value === '') gm.value = baseMonth;
       if (gd && gd.value === '') gd.value = baseDay;
       if (gy && gy.value === '') gy.value = baseYear;
     }
     
     if ((gm?.value === '' || gm?.value === '0') || (gd?.value === '' || gd?.value === '0')) {
         gm.value = baseMonth;
         gd.value = baseDay;
         gy.value = baseYear;
     }
     

     // 補完後は即再計算
     recalcAllRowsCal();
   });
 });
</script>


<script>
// 受贈者変更時に過年度計算を呼び出す(暦年課税)
document.getElementById('future-recipient-no').addEventListener('change', () => {
  try {
    // 受贈者が変更された後に過年度の計算を再実行
    recalcPastSettlementRow0(); // 過年度の計算を明示的に呼び出し
  } catch (e) {
    console.warn('[recalcPastSettlementRow0] Error during recalculation:', e);
  }
});

// 受贈者選択時に、過年度贈与額（行0）の更新処理
/*
 * ==============================
 * ★ 過年度（行0）：精算課税の集計
 *  - 第一ソース：name="seisan_zoyo[i]"（千円）＋ 年は gift_year[i] / seisan_year[i]
 *  - フォールバック：window.__LAST_FUTURE_PAYLOAD から精算課税の過年度データを推定
 *    （候補キー：past.seisan / past.settlement / rekinen.seisan_zoyo / past.calendar[*].mode 等）
 *  - 出力先：name="set_*[0]"
 *    set_amount[0]     = Σ（精算課税・千円）
 *    set_basic110[0]   = -1100 × 件数（year>=2024 の件数。年不明は後方互換で件数に含める）
 *    set_after_basic[0]= max( set_amount[0] - 1100×件数(2024+) , 0 )
 *    set_after_25m[0]  = max( set_after_basic[0] - 25000 , 0 )
 *    set_tax20[0]      = round( set_after_25m[0] × 0.2 )
 *    set_cum[0]        = set_amount[0]
 * ==============================
*/
function recalcPastSettlementRow0() {
  try {
    // ★ スコープ安全化：window 経由で参照し、無ければDOMから直接取得
    const rnSel = (typeof window.getCurrentRecipientNo === 'function')
      ? String(window.getCurrentRecipientNo() || '').trim()
      : String(document.getElementById('future-recipient-no')?.value || '').trim();
    
    let entries = [];

    // ① サーバ返却の精算課税エントリを優先
    if (window.__LAST_FUTURE_PAYLOAD?.past?.settlement_entries) {
      entries = (window.__LAST_FUTURE_PAYLOAD.past.settlement_entries || [])
        .filter(e => e?.gift_year && e?.gift_month && e?.gift_day && (e?.amount_thousand ?? e?.amount_k ?? e?.amount_yen))
        .map(e => ({
          y: Number(e.gift_year),
          m: Number(e.gift_month),
          d: Number(e.gift_day),
          k: Number(e.amount_thousand ?? (Number(e.amount_yen)||0)/1000 ?? e.amount_k ?? 0),
        }));
    }

    // ② 無ければ PAST_GIFTS（t==='seisan' & rn一致）からフォールバック
    if (entries.length === 0 && Array.isArray(window.PAST_GIFTS)) {
      entries = window.PAST_GIFTS
        .filter(g => (!g.rn || String(g.rn) === rnSel) && (g.t === 'seisan'))
        .map(g => ({ y:Number(g.y), m:Number(g.m), d:Number(g.d), k:Number(g.k) }))
        .filter(e => e.y && e.m && e.d && e.k);
    }

    // ③ さらに無ければ DOM（name="seisan_zoyo[i]"）を最後の手段として読む
    if (entries.length === 0) {
      const nodes = Array.from(document.querySelectorAll('input[name^="seisan_zoyo["]'));
      entries = nodes.map(el => {
        const vK = Number(toInt(el.value, 0)) || 0;
        const m = el.name.match(/\[(\d+)\]$/);
        const idx = m ? parseInt(m[1], 10) : null;
        const yEl = idx != null ? document.querySelector(`input[name="seisan_year[${idx}]"]`) : null;
        const y = yEl ? Number(toInt(yEl.value, 0)) : NaN;
        return { y, m: 0, d: 0, k: vK };
      }).filter(e => e.k > 0);
    }

    // 集計（2024年以降の件数で110万円基礎控除件数を数える）
    let sumK = 0;
    let cnt2024 = 0;
    entries.forEach(e => {
      sumK += Math.trunc(Number(e.k) || 0);
      if (!Number.isFinite(e.y) || e.y === 0) {
        // 年不明は1件として扱う（後方互換）
        cnt2024 += 1;
      } else if (e.y >= 2024) {
        cnt2024 += 1;
      }
    });

    const basic110K   = 1100 * cnt2024;
    const afterBasicK = Math.max(sumK - basic110K, 0);
    const after25mK   = Math.max(afterBasicK - 25000, 0);
    const tax20K      = Math.floor(after25mK * 0.2);

    // 0行目へ反映（ご要望どおり「過年度の贈与の合計」を set_*[0] に集約表示）
    setK('set_amount',       0, sumK);
    setK('set_basic110',     0, basic110K ? -basic110K : 0);
    setK('set_after_basic',  0, afterBasicK);
    setK('set_after_25m',    0, after25mK);
    setK('set_tax20',        0, tax20K);
    // ★ 現時点の set_cum[0] も可視化：定義どおり「基礎控除後（afterBasic）」を採用
    setK('set_cum',          0, afterBasicK);
    
  } catch (e) {
    console.warn('[recalcPastSettlementRow0] skipped due to error:', e);
  }
}


// どこからでも呼べるよう公開（※ このブロックは早い段階でロードされる）
window.recalcPastSettlementRow0 = recalcPastSettlementRow0;




// 受贈者変更時に過年度計算を呼び出す(精算課税)
const _selForSeisan = document.getElementById('future-recipient-no');
if (_selForSeisan) _selForSeisan.addEventListener('change', () => {
   try {
     // 受贈者が変更された後に過年度の計算を再実行
     recalcPastSettlementRow0(); // 過年度の計算を明示的に呼び出し
   } catch (e) {
     console.warn('[recalcPastSettlementRow0] Error during recalculation:', e);
   }
});

 
</script>
