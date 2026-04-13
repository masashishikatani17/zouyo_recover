<!--   title.blade  -->
<style>
  .small-text {
    font-size: 14px;
  }
  
</style>

<style>
  table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 742pt;
  }

  td, th {
    border: 1px solid #ccc;
    padding: 1px 2px; /* ★行間詰め */
    font-size: 12px;
    font-family: "游ゴシック", sans-serif;
    line-height: 1.2;  /* ★文字行間を詰める */
    height: 20px;      /* ★最低限の行高を指定（必要に応じて） */
  }

  .text-right {
    text-align: right;
  }

  .bg-gray {
    background-color: #f0f0f0;
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


  /* 入力可 / 参照 / 自動計算 の見た目を明確化 */
  .title-field-input {
    background: #fff8db !important;
    border: 2px solid #d4a72c !important;
    color: #111827 !important;
  }

  .title-field-input:focus {
    background: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
    outline: none;
  }

  .title-field-ref {
    background: #edf6ff !important;
    border: 1px solid #9ec5fe !important;
    color: #1f3b5b !important;
  }

  .title-field-calc {
    background: #eef2f6 !important;
    border: 1px solid #cbd5e1 !important;
    color: #4b5563 !important;
  }

  .title-field-ref:focus,
  .title-field-calc:focus {
    box-shadow: none !important;
    outline: none;
  }


  /* ★合計（自動計算）表示用 */
  .readonly-money {
    background-color:#f0f0f0;
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
  
  /* 以下テーブル2つ左右ドッキング */
      .family-split {
      display: flex;
      align-items: flex-start;
      gap: 0; /* くっつける */
    }
    
    .family-left {
      flex: 0 0 auto;
    }
    
    .family-right-wrap {
      flex: 1 1 auto;
      overflow-x: auto;
    }
    
    .family-left table,
    .family-right-wrap table {
      margin: 0;
    }
    
    /* 左右の境界線が二重になるのが嫌なら右側の最左線を消す */
    .family-right-wrap table td:first-child,
    .family-right-wrap table th:first-child {
      border-left: none !important;
    }
    

    /* 氏名空欄時の右側グレー表示 */
    .row-disabled-cell {
      background-color: #f0f0f0 !important;
    }

    .row-disabled-checkbox {
      opacity: 0.6;
      pointer-events: none;
  }

  /* 氏名空欄で入力不可になった欄は input/select 自体もグレー表示 */
  .title-field-disabled {
    background: #f0f0f0 !important;
    border: 1px solid #cfcfcf !important;
    color: #666 !important;
  }

  .title-field-disabled:focus {
    box-shadow: none !important;
    outline: none !important;
  }

  /* checkbox セル専用：入力可 / 入力不可 を見分けやすくする */
  .title-check-cell-input {
    background: #fff8db !important;
    border: 2px solid #d4a72c !important;
  }

  .title-check-cell-disabled {
    background: #f0f0f0 !important;
    border: 1px solid #cfcfcf !important;
  }

  .title-check-cell-input .form-check-input,
  .title-check-cell-disabled .form-check-input {
    margin-top: 0;
    vertical-align: middle;
  }

  .title-check-cell-input .form-check-input {
    cursor: pointer;
  }

  .title-check-cell-disabled .form-check-input {
    cursor: not-allowed;
  }

  .title-check-cell-input.row-disabled-cell,
  .title-check-cell-disabled.row-disabled-cell {
    background: #f0f0f0 !important;    
  }    
    
</style>

@php
    $relationships = config('relationships');
    $defaultHeaderTitle = '贈与における相続対策のご提案';
    $today = today();    
@endphp

  <div class="mt-5 ms-0 mb-2">
    <h13>■提案書の表題など</h13>
  </div>
  <table class="table-base ms-0" style="width: 500px;">
    <tbody>
      <tr>
        <th style="width: 120px;">お客様名</td>
        <td class="text-start">
             <input type="text" class="form-control suji11 title-field-input"             
                    name="header_customer_name" style="ime-mode:active; text-align:left;"
                    value="{{ old('header_customer_name', data_get($prefillHeader ?? [], 'customer_name', '')) }}"
                    placeholder="" >
         様</td>
      </tr>
      <tr class="border-b">
        <th>提案書の表題</th>
        <td class="text-start">
            <input type="text" class="form-control kana20 title-field-input"            
                   name="header_title" style="ime-mode:active;"
                   value="{{ old('header_title', data_get($prefillHeader ?? [], 'title', $defaultHeaderTitle)) }}"
                   placeholder="" inputmode="text">
         </td>
      </tr>
      <tr class="border-b align-middle">
        <th>提案書の日付</th>
        <td class="text-start">
          <div class="flex items-center gap-1">

            <input type="text" class="form-control suji4 title-field-input"            
                   name="header_year" style="ime-mode:disabled;" inputmode="numeric"
                   value="{{ old('header_year', data_get($prefillHeader ?? [], 'year', $today->year)) }}">
                   年

            <input type="text" class="form-control suji2 title-field-input"            
                   name="header_month" style="ime-mode:disabled;" inputmode="numeric"
                   value="{{ old('header_month', data_get($prefillHeader ?? [], 'month', $today->month)) }}">
                   月

            <input type="text" class="form-control suji2 title-field-input"            
                   name="header_day" style="ime-mode:disabled;" inputmode="numeric"
                   value="{{ old('header_day', data_get($prefillHeader ?? [], 'day', $today->day)) }}">
                   日
          </div>
        </td>
      </tr>
      <tr class="border-b">
        <th>提案者名</th>
        <td class="text-start">

            <input type="text" class="form-control kana30 title-field-input"            
                   name="header_proposer_name" style=" ime-mode:active;"
                   value="{{ old('header_proposer_name', $prefillHeader['proposer_name'] ?? '') }}"
                   placeholder="" inputmode="text">
         </td>
      </tr>
    </tbody>
  </table>

  <div class="mt-5 ms-0 mb-2">
    <h13>■家族構成、所有財産など</h13>
  </div>
  <div class="family-split">

  {{-- ===== 左：固定（番号・氏名） ===== --}}
    <div class="family-left mt-2">
      <table class="table-compact-p ms-0 mb-3" style="width:144px;">
        <colgroup>
          <col style="width:22px;"><!-- 番号 -->
          <col style="width:122px;"><!-- 氏名 -->
        </colgroup>
  
        <thead>
          <tr class="bg-blue text-center">
            <th style="height:40px; vertical-align:middle;">番号</th>
            <th style="height:40px; vertical-align:middle;">氏 名</th>
          </tr>
        </thead>


        <tbody>
          {{-- 1行目（被相続人） --}}
          <tr style="height:25px;">
            <td class="text-end">1</td>
            <td>
                <input type="text" class="form-control kana8 title-field-input"              
                     name="name[1]" style="ime-mode:active;"
                     value="{{ old('name.1', $prefillFamily[1]['name'] ?? '') }}"
                     placeholder="">
            </td>
          </tr>
  
          {{-- 相続人2-10 --}}
          @for ($i = 2; $i <= 10; $i++)
            <tr style="height:25px;">
              <td class="border px-1 py-1 text-end">{{ $i }}</td>
              <td class="border px-1 py-1">
                <input type="text" class="form-control kana8 title-field-input"
                       name="name[{{ $i }}]" style="ime-mode:active;"
                       value="{{ old('name.'.$i, $prefillFamily[$i]['name'] ?? '') }}"
                       placeholder="">
              </td>
            </tr>
          @endfor
  
          {{-- 合計行（左側はラベルだけ） --}}
          <tr class="bg-cream" style="height:23.5px;">
            <td colspan="2">合 計</td>
          </tr>
        </tbody>
      </table>
    </div>

  {{-- ===== 右：横スクロール（残り全部） ===== --}}
    <div class="family-right-wrap mt-2">
      <table class="table-compact-p ms-0 mb-3" style="table-layout: fixed;">
        <colgroup>
          <col style="width:36px;"><!-- 性別 -->
          <col style="width:76px;"><!-- 続柄 -->
          <col style="width:74px;"><!-- 養子縁組 -->
          <col style="width:120px;"><!-- 相続人区分 -->
          <col style="width:36px;"><!-- 民法 分子 -->
          <col style="width:15px;"><!-- / -->
          <col style="width:36px;"><!-- 民法 分母 -->
          <col style="width:36px;"><!-- 税法 分子 -->
          <col style="width:15px;"><!-- / -->
          <col style="width:36px;"><!-- 税法 分母 -->
          <col style="width:36px;"><!-- 2割加算 -->
          <col style="width:36px;"><!-- 特例贈与 -->
          <col style="width:65px;"><!-- 生年月日 年 -->
          <col style="width:50px;"><!-- 生年月日 月 -->
          <col style="width:50px;"><!-- 生年月日 日 -->
          <col style="width:54px;"><!-- 年齢 -->
          <col style="width:110px;"><!-- 金融資産 -->
          <col style="width:110px;"><!-- その他資産 -->
          <col style="width:110px;"><!-- 合計 -->
        </colgroup>
  
        <thead>
          <tr class="bg-blue text-center">
            <th rowspan="2">性別</th>
            <th rowspan="2">続 柄</th>
            <th rowspan="2">養子縁組</th>
            <th rowspan="2">相続人の区分</th>
            <th colspan="6">法定相続割合</th>
            <th rowspan="2">2割加算</th>
            <th rowspan="2">特例贈与</th>
            <th colspan="3" rowspan="2">生年月日</th>
            <th rowspan="2">年 齢</th>
            <th colspan="3">所　有　財　産</th>
          </tr>
          <tr class="bg-blue text-center">
            <th colspan="3">民法上</th>
            <th colspan="3">税法上</th>
            <th>金融資産</th>
            <th>その他資産</th>
            <th>合　計</th>
          </tr>
        </thead>


        <tbody>
          {{-- 1行目（被相続人）※「番号・氏名」は左へ移動したのでここには置かない --}}
          <tr>
            <td>
              <select class="form-control kana2 title-field-input" name="gender[1]">                
                @php $g = old('gender.1', $prefillFamily[1]['gender'] ?? ''); @endphp
                <option value="" @selected($g==='')></option>                
                <option value="男" @selected($g==='男')>男</option>
                <option value="女" @selected($g==='女')>女</option>
              </select>
            </td>
  
            <td>
              @php
                $selfRelationshipKey = array_search('本人', $relationships, true);
                if ($selfRelationshipKey === false) {
                    $selfRelationshipKey = array_key_exists('本人', $relationships) ? '本人' : '';
                }
                $selfRelationshipLabel = $relationships[$selfRelationshipKey] ?? '本人';
              @endphp
              <input type="hidden" name="relationship[1]" value="{{ $selfRelationshipKey }}">
              <select class="form-control kana4 title-field-ref" disabled tabindex="-1">
                <option value="{{ $selfRelationshipKey }}" selected>{{ $selfRelationshipLabel }}</option>
              </select>
            </td>
  
            <td class="text-center">－</td>
  
            <td>
              <input type="hidden" name="souzokunin[1]" value="被相続人">
              <select class="form-control kana7 title-field-ref" disabled tabindex="-1">
                <option value="被相続人" selected>被相続人</option>
              </select>
            </td>
  
            <td class="text-center" colspan="3">－</td>
            <td class="text-center" colspan="3">－</td>
            <td class="text-center">－</td>
            <td class="text-center">－</td>
  
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji4 title-field-input"
                     name="birth_year[1]" style="ime-mode:disabled;" inputmode="numeric"
                     value="{{ old('birth_year.1', $prefillFamily[1]['birth_year'] ?? '') }}">年
            </td>
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji2 title-field-input"              
                     name="birth_month[1]" style="ime-mode:disabled;" inputmode="numeric"
                     value="{{ old('birth_month.1', $prefillFamily[1]['birth_month'] ?? '') }}">月
            </td>
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji2 title-field-input"              
                     name="birth_day[1]" style="ime-mode:disabled;" inputmode="numeric"
                     value="{{ old('birth_day.1', $prefillFamily[1]['birth_day'] ?? '') }}">日
            </td>
  
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji3 title-field-calc"              
                     name="age[1]" style="background-color:#f0f0f0;" readonly tabindex="-1"
                     value="{{ old('age.1', $prefillFamily[1]['age'] ?? '') }}">歳
            </td>
  
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji8 comma decimal0 title-field-input"
                     name="cash[1]" style="ime-mode:disabled;" inputmode="numeric"
                     value="{{ old('cash.1', isset($prefillFamily[1]['cash']) ? number_format($prefillFamily[1]['cash']) : '') }}">千円
            </td>
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji8 comma decimal0 title-field-input"              
                     name="other_asset[1]" style="ime-mode:disabled;" inputmode="numeric"
                     value="{{ old('other_asset.1',
                          isset($prefillFamily[1]['property'])
                            ? number_format(max(0, (int)$prefillFamily[1]['property'] - (int)($prefillFamily[1]['cash'] ?? 0)))
                            : ''
                     ) }}">千円
            </td>
            <td class="border px-1 py-0">
              <input type="text" class="form-control suji8 comma decimal0 readonly-money"
                     name="property[1]" readonly tabindex="-1"
                     value="{{ old('property.1', isset($prefillFamily[1]['property']) ? number_format($prefillFamily[1]['property']) : '') }}">千円
            </td>
          </tr>
  
          {{-- 相続人2-10 --}}
          @for ($i = 2; $i <= 10; $i++)
            <tr>
              <td class="border px-1 py-0">
                <select class="form-control kana2 text-center title-field-input" name="gender[{{ $i }}]">
                  @php $g = old('gender.'.$i, $prefillFamily[$i]['gender'] ?? ''); @endphp
                  <option value="" @selected($g==='')></option>                  
                  <option value="男" @selected($g==='男')>男</option>
                  <option value="女" @selected($g==='女')>女</option>
                </select>
              </td>
  
              <td class="border px-1 py-0">
                @php $rel = old('relationship.'.$i, $prefillFamily[$i]['relationship_code'] ?? ''); @endphp
                <select name="relationship[{{ $i }}]" class="form-control kana4 title-field-input">
                  <option value="" @selected((string)$rel==='')></option>                
                  @foreach($relationships as $key => $label)
                    <option value="{{ $key }}" @selected((string)$rel===(string)$key)>{{ $label }}</option>
                  @endforeach
                </select>
              </td>
  
              <td class="border px-1 py-1">
                <input type="text" class="form-control kana4 title-field-input"                
                       name="yousi[{{ $i }}]" style="ime-mode: active;"
                       value="{{ old('yousi.'.$i, $prefillFamily[$i]['yousi'] ?? '') }}">
              </td>
  
              <td class="border px-1 py-1">
                @php $sz = old('souzokunin.'.$i, $prefillFamily[$i]['souzokunin'] ?? null); @endphp
                <select class="form-control kana7 title-field-input" name="souzokunin[{{ $i }}]">
                  <option value="" @selected($sz===null || $sz==='')></option>
                  <option value="被相続人"      @selected($sz==='被相続人')>被相続人</option>
                  <option value="法定相続人"    @selected($sz==='法定相続人')>法定相続人</option>
                  <option value="法定相続人以外" @selected($sz==='法定相続人以外')>法定相続人以外</option>
                </select>
              </td>
  
              {{-- 民法上 --}}
              <td class="border px-1 py-1">
                <input type="text" class="form-control suji2 title-field-input"                
                       name="civil_share_bunsi[{{ $i }}]" style="ime-mode: disabled;" inputmode="numeric"
                       value="{{ old('civil_share_bunsi.'.$i, $prefillFamily[$i]['civil_share_bunsi'] ?? '') }}">
              </td>
              <td class="slash-cell border px-1 py-1 text-center" style="width: 5px;">/</td>
              <td class="border px-1 py-1">
                <input type="text" class="form-control suji2 title-field-input"
                       name="civil_share_bunbo[{{ $i }}]" style="ime-mode: disabled;" inputmode="numeric"
                       value="{{ old('civil_share_bunbo.'.$i, $prefillFamily[$i]['civil_share_bunbo'] ?? '') }}">
              </td>
  
              {{-- 税法上 --}}
              <td class="border px-1 py-1">
                <input type="text" class="form-control suji2 title-field-input"                
                       name="bunsi[{{ $i }}]" style="ime-mode: disabled;" inputmode="numeric"
                       value="{{ old('bunsi.'.$i, $prefillFamily[$i]['bunsi'] ?? '') }}">
              </td>
              <td class="slash-cell border px-1 py-1 text-center" style="width: 5px;">/</td>
              <td class="border px-1 py-1">
                <input type="text" class="form-control suji2 title-field-input"                
                       name="bunbo[{{ $i }}]" style="ime-mode: disabled;" inputmode="numeric"
                       value="{{ old('bunbo.'.$i, $prefillFamily[$i]['bunbo'] ?? '') }}">
              </td>
  
              {{-- 2割加算 --}}
              <td class="border px-1 py-0 text-center js-twenty-percent-cell" data-row="{{ $i }}">
                @php
                  $twRaw = old('twenty_percent_add.'.$i, $prefillFamily[$i]['twenty_percent_add'] ?? 0);
                  $twChecked = in_array((string)$twRaw, ['1', 'on', 'true', 'yes'], true);
                @endphp
                <input type="hidden" name="twenty_percent_add[{{ $i }}]" value="0">
                <input type="checkbox"
                       name="twenty_percent_add[{{ $i }}]"
                       value="1"
                       class="form-check-input js-twenty-percent-checkbox"
                       @checked($twChecked)>
              </td>
  
              {{-- 特例贈与 --}}
              <td class="border px-1 py-0 text-center js-tokurei-zouyo-cell" data-row="{{ $i }}">
                @php
                  $tzRaw = old('tokurei_zouyo.'.$i, $prefillFamily[$i]['tokurei_zouyo'] ?? 0);
                  $tzChecked = in_array((string)$tzRaw, ['1', 'on', 'true', 'yes'], true);
                @endphp
                <input type="hidden" name="tokurei_zouyo[{{ $i }}]" value="0">
                <input type="checkbox"
                       name="tokurei_zouyo[{{ $i }}]"
                       value="1"
                       class="form-check-input js-tokurei-zouyo-checkbox"
                       @checked($tzChecked)>
              </td>
  
              {{-- 生年月日 --}}
              <td class="border px-1 py-0">
                <input type="text" class="form-control suji4 title-field-input"
                       name="birth_year[{{ $i }}]" style="ime-mode:disabled;" inputmode="numeric"
                       value="{{ old('birth_year.'.$i, $prefillFamily[$i]['birth_year'] ?? '') }}">年
              </td>
              <td class="border px-1 py-0">
                <input type="text" class="form-control suji2 title-field-input"                
                       name="birth_month[{{ $i }}]" style="ime-mode:disabled;" inputmode="numeric"
                       value="{{ old('birth_month.'.$i, $prefillFamily[$i]['birth_month'] ?? '') }}">月
              </td>
              <td class="border px-1 py-0">
                <input type="text" class="form-control suji2 title-field-input"
                       name="birth_day[{{ $i }}]" style="ime-mode:disabled;" inputmode="numeric"
                       value="{{ old('birth_day.'.$i, $prefillFamily[$i]['birth_day'] ?? '') }}">日
              </td>
  
              {{-- 年齢 --}}
              <td class="border px-1 py-0 js-age-cell" data-row="{{ $i }}">
                <input type="text" class="form-control d-inline suji3"
                       name="age[{{ $i }}]" style="background-color:#f0f0f0;" readonly tabindex="-1"
                       value="{{ old('age.'.$i, $prefillFamily[$i]['age'] ?? '') }}">歳
              </td>
  
              {{-- 金融資産/その他/合計 --}}
              <td class="border px-1 py-0">
              <input type="text" class="form-control suji8 comma decimal0 title-field-input"                
                       name="cash[{{ $i }}]" style="ime-mode:disabled;" inputmode="numeric"
                       value="{{ old('cash.'.$i, isset($prefillFamily[$i]['cash']) ? number_format($prefillFamily[$i]['cash']) : '') }}">千円
              </td>
              <td class="border px-1 py-0">
                <input type="text" class="form-control suji8 comma decimal0 title-field-input"
                       name="other_asset[{{ $i }}]" style="ime-mode:disabled;" inputmode="numeric"
                       value="{{ old('other_asset.'.$i,
                            isset($prefillFamily[$i]['property'])
                              ? number_format(max(0, (int)$prefillFamily[$i]['property'] - (int)($prefillFamily[$i]['cash'] ?? 0)))
                              : ''
                       ) }}">千円
              </td>
              <td class="border px-1 py-0 js-property-cell" data-row="{{ $i }}">                
                <input type="text" class="form-control suji8 comma decimal0 readonly-money title-field-calc"
                       name="property[{{ $i }}]" readonly tabindex="-1"
                       value="{{ old('property.'.$i, isset($prefillFamily[$i]['property']) ? number_format($prefillFamily[$i]['property']) : '') }}">千円
              </td>
            </tr>
          @endfor
  
          {{-- 合計行（右側：colspan を 18 → 16 に変更） --}}
          <tr class="bg-cream">
            <td colspan="16"> </td>
            <td class="border px-1 py-1">
              <input type="text" class="form-control suji8 comma decimal0 title-field-calc"
                     name="cash[110]" readonly tabindex="-1"
                     value="{{ old('cash.110', isset($prefillHeader['cash_110']) ? number_format($prefillHeader['cash_110']) : '') }}">千円
            </td>
            <td class="border px-1 py-1">
              <input type="text" class="form-control suji8 comma decimal0 title-field-calc"
                     name="other_asset[110]" readonly tabindex="-1"
                     value="{{ old('other_asset.110',
                          (isset($prefillHeader['property_110'], $prefillHeader['cash_110']))
                            ? number_format(max(0, (int)$prefillHeader['property_110'] - (int)$prefillHeader['cash_110']))
                            : ''
                     ) }}">千円
            </td>
            <td class="border px-1 py-1">
              <input type="text" class="form-control suji8 comma decimal0 title-field-calc"              
                     name="property[110]" readonly tabindex="-1"
                     value="{{ old('property.110', isset($prefillHeader['property_110']) ? number_format($prefillHeader['property_110']) : '') }}">千円
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-3 ms-3">
      ※一番上には被相続人を入力してください。	<br>
      ※金融資産の運用利回り(税引後)	
            <input type="text" class="form-control d-inline input-small text-end title-field-input"
                   name="per" style="width:60px; ime-mode:disabled;" inputmode="numeric"
                   value="{{ old('per', data_get($prefillHeader ?? [], 'per', '1.0')) }}">
      ％	<br>
      ※相続税の２割加算の対象になる人は「ﾚ」を付けて下さい。	<br>
      ※暦年課税の贈与で特例贈与の適用を受けられる受贈者は「ﾚ」を付けて下さい。年齢による判定は自動で行ないます。	<br>
      <div>
          ※税法上の法定相続人の人数
          <input type="text"
                 class="form-control d-inline-block suji2 title-field-calc"
                 name="houtei_ninzu"
                 readonly
                 tabindex="-1"
                 style="width:50px; vertical-align:middle;">
          人
      </div>
  </div>

      <br>
      <br>

<script>
document.addEventListener('DOMContentLoaded', function () {


  {{-- 氏名が空欄の時はその右側も空欄 --}}
      function isNameBlank(i) {
        const nameInput = document.querySelector(`input[name="name[${i}]"]`);
        return !nameInput || String(nameInput.value ?? '').trim() === '';
      }

      function applyDisabledVisual(el, blank) {
        if (!el) return;

        // checkbox / radio にはテキスト入力用の装飾クラスを当てない
        if (el.type === 'checkbox' || el.type === 'radio') {
          return;
        }

        el.classList.toggle('title-field-disabled', blank);

        if (blank) {
          el.classList.remove('title-field-input');
          el.classList.remove('title-field-ref');
        } else {
          // readonly の自動計算欄は calc を維持し、編集欄だけ input を戻す
          if (!el.hasAttribute('readonly')) {
            el.classList.add('title-field-input');
          }
        }
      }

      function applyDisabledVisualToCell(cell, blank) {
        if (!cell) return;
        
        cell.classList.toggle('row-disabled-cell', blank);
        
        cell.classList.remove('title-check-cell-input', 'title-check-cell-disabled');

        // checkbox を含むセルは、セル自体を色分けする
        if (cell.classList.contains('js-twenty-percent-cell') || cell.classList.contains('js-tokurei-zouyo-cell')) {
          if (blank) {
            cell.classList.add('title-check-cell-disabled');
          } else {
            cell.classList.add('title-check-cell-input');
          }
        }        
        

        const fields = cell.querySelectorAll('input, select, textarea');
        fields.forEach((el) => {

          if (el.type === 'hidden') return;
          
          if (el.type === 'checkbox' || el.type === 'radio') {
            if (blank) {
              el.disabled = true;
            } else {
              el.disabled = false;
            }
            return;
          }          

          if (el.hasAttribute('readonly')) return;

          applyDisabledVisual(el, blank);

        });        
      }
    
      function clearElementValue(el) {
        if (!el) return;
    
        if (el.tagName === 'SELECT') {
          el.selectedIndex = 0;
          return;
        }
    
        if (el.type === 'checkbox' || el.type === 'radio') {
          el.checked = false;
          return;
        }
    
        if (el.hasAttribute('readonly')) {
          el.value = '';
          return;
        }
    
        el.value = '';
      }
    
      function toggleRowFields(i) {
        const blank = isNameBlank(i);
        
        const twentyPercentCell = document.querySelector(`.js-twenty-percent-cell[data-row="${i}"]`);
        const tokureiZouyoCell  = document.querySelector(`.js-tokurei-zouyo-cell[data-row="${i}"]`);
        const ageCell           = document.querySelector(`.js-age-cell[data-row="${i}"]`);
        const propertyCell      = document.querySelector(`.js-property-cell[data-row="${i}"]`);
        

        const selectors = [
          `select[name="gender[${i}]"]`,
          `select[name="relationship[${i}]"]`,
          `input[name="yousi[${i}]"]`,
          `select[name="souzokunin[${i}]"]`,
          `input[name="civil_share_bunsi[${i}]"]`,
          `input[name="civil_share_bunbo[${i}]"]`,
          `input[name="bunsi[${i}]"]`,
          `input[name="bunbo[${i}]"]`,
          `input[name="birth_year[${i}]"]`,
          `input[name="birth_month[${i}]"]`,
          `input[name="birth_day[${i}]"]`,
          `input[name="age[${i}]"]`,
          `input[name="cash[${i}]"]`,
          `input[name="other_asset[${i}]"]`,
          `input[name="property[${i}]"]`
        ];
    
        selectors.forEach((selector) => {
          const el = document.querySelector(selector);
          if (!el) return;
    
          if (blank) {
          
            applyDisabledVisual(el, true);
          
            clearElementValue(el);

            if (!el.hasAttribute('readonly')) {
              el.disabled = true;
            }
          } else {
            if (!el.hasAttribute('readonly')) {
              el.disabled = false;
              
              applyDisabledVisual(el, false);              
            }
          }
        });
        

        [twentyPercentCell, tokureiZouyoCell, ageCell, propertyCell].forEach((cell) => {
                   
          if (!cell) return;
          cell.classList.toggle('row-disabled-cell', blank);
        });


        // チェックボックスを持つセル自体の input風背景も同期
        applyDisabledVisualToCell(twentyPercentCell, blank);
        applyDisabledVisualToCell(tokureiZouyoCell, blank);

        // readonly の年齢・合計欄は既存の calc 表示を優先
        if (ageCell) ageCell.querySelectorAll('input').forEach((el) => el.classList.add('title-field-calc'));
        if (propertyCell) propertyCell.querySelectorAll('input').forEach((el) => el.classList.add('title-field-calc'));
 
        const twentyPercentCheckbox = document.querySelector(`input[name="twenty_percent_add[${i}]"][type="checkbox"]`);
        const tokureiZouyoCheckbox  = document.querySelector(`input[name="tokurei_zouyo[${i}]"][type="checkbox"]`);


        [twentyPercentCheckbox, tokureiZouyoCheckbox].forEach((checkbox) => {
          if (!checkbox) return;
          if (blank) {
            checkbox.checked = false;
            checkbox.disabled = true;
          } else {
            checkbox.disabled = false;
          }
        });


        [twentyPercentCheckbox, tokureiZouyoCheckbox].forEach((checkbox) => {
          if (!checkbox) return;
          checkbox.classList.toggle('row-disabled-checkbox', blank);
        });

        // checkbox セルの見た目を最終同期
        [twentyPercentCell, tokureiZouyoCell].forEach((cell) => {
          if (!cell) return;
          cell.classList.remove('title-check-cell-input', 'title-check-cell-disabled');
          if (blank) {
            cell.classList.add('title-check-cell-disabled');
          } else {
            cell.classList.add('title-check-cell-input');
          }          
        });        
        
      }


      function prepareRowFieldsForSubmit() {
        for (let i = 1; i <= 10; i++) {
          const blank = isNameBlank(i);

          const selectors = [
            `select[name="gender[${i}]"]`,
            `select[name="relationship[${i}]"]`,
            `input[name="yousi[${i}]"]`,
            `select[name="souzokunin[${i}]"]`,
            `input[name="civil_share_bunsi[${i}]"]`,
            `input[name="civil_share_bunbo[${i}]"]`,
            `input[name="bunsi[${i}]"]`,
            `input[name="bunbo[${i}]"]`,
            `input[name="birth_year[${i}]"]`,
            `input[name="birth_month[${i}]"]`,
            `input[name="birth_day[${i}]"]`,
            `input[name="age[${i}]"]`,
            `input[name="cash[${i}]"]`,
            `input[name="other_asset[${i}]"]`,
            `input[name="property[${i}]"]`
          ];

          selectors.forEach((selector) => {
            const el = document.querySelector(selector);
            if (!el) return;

            if (blank) {
              if (el.tagName === 'SELECT') {
                el.value = '';
              } else if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = false;
              } else {
                el.value = '';
              }
            }

            if (el.disabled) {
              el.disabled = false;
            }
          });
        }
      }



    
      function toggleAllRowFields() {
        for (let i = 1; i <= 10; i++) {
          toggleRowFields(i);
        }
      }



  // 入力フィールドがblurイベントで値をリセットしないように
  const inputFields = document.querySelectorAll('input[name="header_customer_name"], input[name="header_title"], input[name="header_proposer_name"]');

  inputFields.forEach(input => {
    input.addEventListener('blur', function () {
      if (input.value.trim() === '') {
        // 空の場合は入力値を保持する
        input.value = input.defaultValue;
      }
    });
  });


  {{-- 氏名が空欄の時はその右側も空欄 --}}
      for (let i = 1; i <= 10; i++) {
        const nameInput = document.querySelector(`input[name="name[${i}]"]`);
        if (!nameInput) continue;
    
        nameInput.addEventListener('input', function () {
          toggleRowFields(i);
          if (typeof recalcAllTotals === 'function') recalcAllTotals();
          if (typeof countHouteiByBunsi === 'function') countHouteiByBunsi();
        });
    
        nameInput.addEventListener('blur', function () {
          toggleRowFields(i);
          if (typeof recalcAllTotals === 'function') recalcAllTotals();
          if (typeof countHouteiByBunsi === 'function') countHouteiByBunsi();
        });
      }

      toggleAllRowFields();
      
      

      document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', function () {
          prepareRowFieldsForSubmit();
        });
      });      
      

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
    // ★ 金融資産(cash) / その他資産(other_asset) / 合計(property=readonly) を対象にカンマ付与
    const priceInputs = document.querySelectorAll(
      'input[name^="cash["], input[name^="other_asset["], input[name^="property["]'
    );

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

      {{-- 氏名が空欄の時はその右側も空欄 --}}
      function isNameBlank(i) {
        const nameInput = document.querySelector(`input[name="name[${i}]"]`);
        return !nameInput || String(nameInput.value ?? '').trim() === '';
      }

  //年齢計算
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
      
      
        {{-- 氏名が空欄の時はその右側も空欄 --}}
        if (isNameBlank(i)) {
          ageInput.value = '';
          return;
        }
      
      
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
        } else {
          ageInput.value = '';
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
  // ★ 金融資産・その他資産から合計(property)を自動計算し、下段合計(110)も更新
  
      {{-- 氏名が空欄の時はその右側も空欄 --}}
      function isNameBlank(i) {
        const nameInput = document.querySelector(`input[name="name[${i}]"]`);
        return !nameInput || String(nameInput.value ?? '').trim() === '';
      }
 
  
  function sanitizeNumber(str) {
    return parseInt(String(str ?? '').replace(/,/g, '').replace(/[^\d]/g, ''), 10) || 0;
  }
  function formatWithComma(num) {
    return Number(num || 0).toLocaleString();
  }

  window.recalcRowTotal = function recalcRowTotal(i) {  
    const cashInput  = document.querySelector(`input[name="cash[${i}]"]`);
    const otherInput = document.querySelector(`input[name="other_asset[${i}]"]`);
    const totalInput = document.querySelector(`input[name="property[${i}]"]`);
    if (!cashInput || !otherInput || !totalInput) return;


        {{-- 氏名が空欄の時はその右側も空欄 --}}
        if (isNameBlank(i)) {
          cashInput.value = '';
          otherInput.value = '';
          totalInput.value = '';
          return;
        }

    const cash  = sanitizeNumber(cashInput.value);
    const other = sanitizeNumber(otherInput.value);
    const total = cash + other;
    totalInput.value = formatWithComma(total);
  };
  
  

  window.recalcAllTotals = function recalcAllTotals() {  
    let sumCash = 0;
    let sumOther = 0;
    let sumTotal = 0;

    for (let i = 1; i <= 10; i++) {
      recalcRowTotal(i);


      {{-- 氏名が空欄の時はその右側も空欄 --}}
      if (isNameBlank(i)) {
        continue;
      }


      const cashInput  = document.querySelector(`input[name="cash[${i}]"]`);
      const otherInput = document.querySelector(`input[name="other_asset[${i}]"]`);
      const totalInput = document.querySelector(`input[name="property[${i}]"]`);

      if (cashInput)  sumCash  += sanitizeNumber(cashInput.value);
      if (otherInput) sumOther += sanitizeNumber(otherInput.value);
      if (totalInput) sumTotal += sanitizeNumber(totalInput.value);
    }

    const sumCashInput  = document.querySelector(`input[name="cash[110]"]`);
    const sumOtherInput = document.querySelector(`input[name="other_asset[110]"]`);
    const sumTotalInput = document.querySelector(`input[name="property[110]"]`);

    if (sumCashInput)  sumCashInput.value  = formatWithComma(sumCash);
    if (sumOtherInput) sumOtherInput.value = formatWithComma(sumOther);
    if (sumTotalInput) sumTotalInput.value = formatWithComma(sumTotal);
  };

  // 入力イベント付与（1..10：金融資産＋その他資産が入力対象）
  for (let i = 1; i <= 10; i++) {
    const cashInput  = document.querySelector(`input[name="cash[${i}]"]`);
    const otherInput = document.querySelector(`input[name="other_asset[${i}]"]`);
    [cashInput, otherInput].forEach((el) => {
      if (!el) return;
      el.addEventListener('blur', recalcAllTotals);
      el.addEventListener('input', recalcAllTotals);
      el.addEventListener('change', recalcAllTotals);
    });
  }

  // 初期表示
  recalcAllTotals();
});
</script>

<script>
<!-- ★金融資産合計/所有財産合計の個別スクリプトは統合（上の recalcAllTotals で一括管理） -->
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
//法定相続人人数計算

      {{-- 氏名が空欄の時はその右側も空欄 --}}
      function isNameBlank(i) {
        const nameInput = document.querySelector(`input[name="name[${i}]"]`);
        return !nameInput || String(nameInput.value ?? '').trim() === '';
      }

  window.countHouteiByBunsi = function countHouteiByBunsi() {

    let count = 0;

    for (let i = 2; i <= 10; i++) {

      {{-- 氏名が空欄の時はその右側も空欄 --}}
      if (isNameBlank(i)) {
        continue;
      }

      const bunsiInput = document.querySelector(`input[name="bunsi[${i}]"]`);
      if (bunsiInput) {
        const value = parseInt(bunsiInput.value.replace(/[^\d]/g, ''), 10); // 数字以外除外
        if (!isNaN(value) && value >= 1) {
          count++;
        }
      }
    }

    const targetInput = document.querySelector('input[name="houtei_ninzu"]');
    if (targetInput) {
      targetInput.value = count;
    }
  };

  // 各 bunsi[1〜10] に blur イベントを付与
  for (let i = 2; i <= 10; i++) {
    const input = document.querySelector(`input[name="bunsi[${i}]"]`);
    if (input) {
      input.addEventListener('blur', countHouteiByBunsi);
      input.addEventListener('input', countHouteiByBunsi);
      input.addEventListener('change', countHouteiByBunsi);
     }
   }

  // 初期表示時（DOMContentLoaded 直後）にも一度計算して反映
  countHouteiByBunsi();

  
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {

  // readonly等はフォーカス行かない
  function getFocusableElements() {
    return Array.from(document.querySelectorAll(
      'input:not([readonly]):not([disabled]):not([tabindex="-1"]), select:not([disabled]), textarea:not([disabled])'
    )).filter(el => el.offsetParent !== null);
  }

  // Enterキーでフォーカスを次に移動
  getFocusableElements().forEach((el) => {
  
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();

        const focusable = getFocusableElements();
        const index = focusable.indexOf(el);
        let next = focusable[index + 1];        
        if (next) {
          next.focus();
        }
      }
    });
  });
});
</script>

<script>
// header_year は「西暦年」なので 3 桁カンマを付けないようにする
document.addEventListener('DOMContentLoaded', function () {
  const yearInput = document.querySelector('input[name="header_year"]');
  if (!yearInput) return;

  yearInput.addEventListener('blur', function () {
    let raw = String(yearInput.value ?? '').trim();

    if (raw === '') {
      yearInput.value = '';
      return;
    }

    // 全角数字を半角に変換し、数字以外（カンマなど）を除去
    raw = raw.replace(/[０-９]/g, function (ch) {
      return String.fromCharCode(ch.charCodeAt(0) - 0xFEE0);
    });
    raw = raw.replace(/[^\d\-]/g, '');

    yearInput.value = raw;   // ★ ここでは toLocaleString などで 3桁区切りは付けない
  });
});
</script>
