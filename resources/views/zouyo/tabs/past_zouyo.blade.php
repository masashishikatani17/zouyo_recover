<!--   past_zouyo.blade  -->

<style>
  .small-text {
    font-size: 14px;
  }
  
  .input-small {
    font-size: 12px;
  }
  
</style>

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

  input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    font-size: 12px;
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
  .past-field-input {
    background: #fff8db !important;
    border: 2px solid #d4a72c !important;
    color: #111827 !important;
  }

  .past-field-input:focus {
    background: #ffffff !important;
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
    outline: none;
  }

  .past-field-ref {
    background: #edf6ff !important;
    border: 1px solid #9ec5fe !important;
    color: #1f3b5b !important;
  }

  .past-field-calc {
    background: #eef2f6 !important;
    border: 1px solid #cbd5e1 !important;
    color: #4b5563 !important;
  }

  .past-field-ref:focus,
  .past-field-calc:focus {
    box-shadow: none !important;
    outline: none;
  }  
  
  
   /* 第一表・第三表：ヘッダー固定＋ボディのみ縦スクロール */
    .furusato-table-scroll {
      max-height: 161px;         /* 必要に応じて 500〜600px で微調整 */
      overflow-y: auto;          /* ボディ側だけをスクロールさせる */
    }
    .furusato-table-scroll table {
      margin-bottom: 0;          /* スクロール内で余白を詰める */
    }
</style>


{{-- ★ CSRF（このBlade単体でもPOSTできるよう明示） --}}
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

    // ◆ 受贈者候補（2〜10行目）
    // 優先順：1) $family 2) $prefillFamily 3) old()/request()
    $heirNames = [];
    for ($no = 2; $no <= 10; $no++) {
        $key = (string)$no;
        $candidates = [
            \Illuminate\Support\Arr::get($family ?? [],        $key . '.name'),
            \Illuminate\Support\Arr::get($prefillFamily ?? [], $key . '.name'),
            old("name.$no"),
            request()->input("name.$no"),
        ];
        $name = '';
        foreach ($candidates as $cand) {
            $cand = is_string($cand) ? trim($cand) : '';
            if ($cand !== '') { $name = $cand; break; }
        }
        $heirNames[$no] = $name;
    }
@endphp

  
  
  @if (false)        
  
        {{-- 画面　非表示 --}} 
        {{-- ▼ デバッグ用ステータス行（画面右上に小さく表示） --}}
        <div id="past-status"
             style="position:sticky; top:4px; right:4px; float:right; font-size:12px; background:#f8f9fa; border:1px solid #ddd; border-radius:6px; padding:6px 8px;">
          <span>保存: <b id="past-save-status">-</b></span> /
          <span>取得: <b id="past-fetch-status">-</b></span> /
          <span>反映: <b id="past-apply-status">-</b></span>
          <span style="margin-left:6px;color:#888">(<span id="past-recipient-label">-</span>)</span>
        </div>
      
      
        {{-- ▼ デバッグ: 取得した生JSONを確認できる（本番は @if(app()->isLocal()) 等で囲ってOK） --}}
        <details id="past-debug-box" style="float:right; clear:right; width:520px; margin-top:6px;">
          <summary style="cursor:pointer; font-size:12px;">取得JSONプレビュー</summary>
          <pre id="past-debug-json" style="white-space:pre-wrap; word-break:break-all; font-size:11px; max-height:220px; overflow:auto; border:1px solid #ddd; padding:8px; background:#fff;"></pre>
        </details>
      
  @endif
  

  
  {{-- ★ 表示したい場合は name を data_id_display にする（親フォームの data_id と衝突させない） --}}
  <input type="hidden" name="data_id_display" value="{{ $data->id ?? request('data_id') }}" readonly>



  <table class="table-base ms-10" style="width: 300px;">
    <tbody>
      <tr>
        <th style="width: 120px;">贈与者</th>
        <td class="px-2 py-1">

            <input
              type="text"
              class="form-control d-inline input-small past-field-ref"              
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
                $sel = old('recipient_no', $prefillPast['recipient_no'] ?? null);
            @endphp



            <select
              class="form-control d-inline input-small past-field-input"
              name="recipient_no"
              id="past-recipient-no"
              style="width: 100px; text-align:left;"              
              data-fetch-url="{{ url('/zouyo/past/fetch') }}"
              data-data-id="{{ $data->id ?? request('data_id') }}">

              @foreach($heirNames as $no => $name)
                @if($name !== '')
                  <option
                    value="{{ $no }}"
                    @selected((string)$sel === (string)$no)
                  >
                    {{ $name }}
                  </option>
                @endif
              @endforeach

            </select>
          </td>
       </tr>
    </tbody>
  </table>

  <div style="display: none;">
      {{-- ▼ 取得結果のシンプル表示（テーブル外に移動：構文崩れ防止） --}}
      <div id="past-recipient-data" style="margin: 8px 0;">
        <p>受贈者名：<span id="recipient-name">-</span></p>
        <p>贈与額：<span id="recipient-amount">-</span></p>
      </div>
  </div>
  <div class="ms-10 mb-3">  
      <h13>■暦年贈与分（現時点より過去３年分まででOK）</h13> 
　</div>  
  <div class="d-flex justify-content-center">
     <!-- 左側の縦書きラベル -->
     <table class="table-base m-0" style="width: 32px; height:210px;">
       <tbody>
         <tr>
           <th>暦<br>年<br>贈<br>与<br>分</th>
         </tr>
       </tbody>
     </table>
  
     <!-- 右側：ヘッダテーブル＋スクロールテーブルのまとまり -->
     <div>
       <table class="table-compact-p ms-0" style="width: 630px; table-layout: fixed;">
          <colgroup>					  
  					  <col style="width:90px">
  					  <col style="width:70px">
  				    <col style="width:70px">
  				    <col style="width:100px">
  					  <col style="width:130px">
  				    <col style="width:130px">
  		    </colgroup>
           <tr class="bg-blue" style="height:  25px;">
            
            <th colspan="3">贈与年月日(西暦)</th>
            <th>贈与財産の種類</th>
            <th>贈与額</th>
            
            <th>贈与税額</th>
           
          </tr>
        </table>    
  
       <div class="table-responsive furusato-table-scroll" style="width: 650px;">
         <table class="table-compact-p" style="width: 630px; table-layout: fixed;">
          <colgroup>					  
  					  <col style="width:90px">
  					  <col style="width:70px">
  				    <col style="width:70px">
  				    <col style="width:100px">
  					  <col style="width:130px">
  				    <col style="width:130px">
  		    </colgroup>
        <tbody>
          <!-- 1-10 -->
          @for ($i = 1; $i <= 10; $i++)
          <tr>
            <td class="border px-1 py-0">
              <input type="text" class="form-control d-inline input-small text-end past-field-input" name="rekinen_year[{{ $i }}]" style="width: 60px; ime-mode: disabled;" inputmode="numeric"
                     value="{{ old('rekinen_year.'.$i, $prefillPast['rekinen']['year'][$i] ?? '') }}">年</td>
          
            <td class="border px-1 py-0">
              <input type="text" class="form-control d-inline input-small text-end past-field-input" name="rekinen_month[{{ $i }}]" style="width: 45px; ime-mode: disabled;" inputmode="numeric"
                     value="{{ old('rekinen_month.'.$i, $prefillPast['rekinen']['month'][$i] ?? '') }}">月</td>
          
            <td class="border px-1 py-0">
              <input type="text" class="form-control d-inline input-small text-end past-field-input" name="rekinen_day[{{ $i }}]" style="width: 45px; ime-mode: disabled;" inputmode="numeric"
                     value="{{ old('rekinen_day.'.$i, $prefillPast['rekinen']['day'][$i] ?? '') }}">日</td>
            <td class="border px-1 py-0"></td>
            <td class="border px-1 py-0">
              <input type="text" class="form-control d-inline input-small text-end past-field-input" name="rekinen_zoyo[{{ $i }}]" style="width: 90px; ime-mode: disabled;" inputmode="numeric"
                     value="{{ old('rekinen_zoyo.'.$i, isset($prefillPast['rekinen']['zoyo'][$i]) ? number_format($prefillPast['rekinen']['zoyo'][$i]) : '') }}">
            千円</td>
          
            <td class="border px-1 py-0">
              <input type="text" class="form-control d-inline input-small text-end past-field-input" name="rekinen_kojo[{{ $i }}]" style="width: 90px; ime-mode: disabled;" inputmode="numeric"
                     value="{{ old('rekinen_kojo.'.$i, isset($prefillPast['rekinen']['kojo'][$i]) ? number_format($prefillPast['rekinen']['kojo'][$i]) : '') }}">
  
            千円</td>
          </tr>
          @endfor
          
        </tbody>
       </table>
      </div>
     
      <table class="table-compact-p ms-0" style="width: 630px; table-layout: fixed;">
          <colgroup>					  
  					  <col style="width:330px">
  					  <col style="width:130px">
  				    <col style="width:130px">
  		    </colgroup>
           <tr>
            <td class="bg-cream text-center">合 計</td>
            <td class="border px-1 py-1">
              <input type="text" class="form-control d-inline input-small text-end past-field-calc" name="rekinen_zoyo[110]" style="width: 90px; background-color: #f0f0f0;" readonly tabindex="-1"
                     value="{{ isset($prefillPast['rekinen']['total']['zoyo']) ? number_format($prefillPast['rekinen']['total']['zoyo']) : '' }}">
           千円</td>
            <td class="border px-1 py-1">
              <input type="text" class="form-control d-inline input-small text-end past-field-calc" name="rekinen_kojo[110]" style="width: 90px; background-color: #f0f0f0;" readonly tabindex="-1"
                     value="{{ isset($prefillPast['rekinen']['total']['kojo']) ? number_format($prefillPast['rekinen']['total']['kojo']) : '' }}">
            千円</td>
          </tr>
      </table>    
   </div>
  </div>
  <div class="mt-5 ms-10 mb-3">
    <h13>■精算課税分</h13>
  </div>  
    
    <div class="d-flex justify-content-center">
     <!-- 左側の縦書きラベル -->
     <table class="table-base m-0" style="width: 32px; height:210px;">
       <tbody>
         <tr>
           <th>精<br>算<br>課<br>税<br>分</th>
         </tr>
       </tbody>
     </table>
  
     <!-- 右側：ヘッダテーブル＋スクロールテーブルのまとまり -->
     <div>
       <table class="table-compact-p ms-0" style="width: 630px; table-layout: fixed;">
          <colgroup>					  
  					  <col style="width:90px">
  					  <col style="width:70px">
  				    <col style="width:70px">
  				    <col style="width:100px">
  					  <col style="width:130px">
  				    <col style="width:130px">
  		    </colgroup>
           <tr class="bg-blue" style="height:  25px;">
            
            <th colspan="3">贈与年月日(西暦)</th>
            <th>贈与財産の種類</th>
            <th>贈与額</th>
            
            <th>贈与税額</th>
           
          </tr>
        </table>    
  
       <div class="table-responsive furusato-table-scroll" style="width: 650px;">
         <table class="table-compact-p" style="width: 630px; table-layout: fixed;">
          <colgroup>					  
  					  <col style="width:90px">
  					  <col style="width:70px">
  				    <col style="width:70px">
  				    <col style="width:100px">
  					  <col style="width:130px">
  				    <col style="width:130px">
  		    </colgroup>
        <tbody>
        <!-- 1-10 -->
        @for ($i = 1; $i <= 10; $i++)
        <tr>
          <td class="border px-1 py-0">
            <input type="text" class="form-control d-inline input-small text-end past-field-input" name="seisan_year[{{ $i }}]" style="width: 60px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('seisan_year.'.$i, $prefillPast['seisan']['year'][$i] ?? '') }}">
                   年
          </td>
          <td class="border px-1 py-0">
            <input type="text" class="form-control d-inline input-small text-end past-field-input" name="seisan_month[{{ $i }}]" style="width: 45px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('seisan_month.'.$i, $prefillPast['seisan']['month'][$i] ?? '') }}">
                   月
          </td>
          <td class="border px-1 py-0">
            <input type="text" class="form-control d-inline input-small text-end past-field-input" name="seisan_day[{{ $i }}]" style="width: 45px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('seisan_day.'.$i, $prefillPast['seisan']['day'][$i] ?? '') }}">
                   日
          </td>
          <td class="border px-1 py-0"></td>
          <td class="border px-1 py-0">
            <input
              type="text"
              class="form-control d-inline input-small text-end past-field-input"              
              name="seisan_zoyo[{{ $i }}]"
              style="width: 90px; ime-mode: disabled;"
              inputmode="numeric"
              value="{{ old('seisan_zoyo.'.$i, isset($prefillPast['seisan']['zoyo'][$i]) ? number_format($prefillPast['seisan']['zoyo'][$i]) : '') }}">
              千円
          </td>
          <td class="border px-1 py-0">
            <input type="text" class="form-control d-inline input-small text-end past-field-input" name="seisan_kojo[{{ $i }}]" style="width: 90px; ime-mode: disabled;" inputmode="numeric"
                   value="{{ old('seisan_kojo.'.$i, isset($prefillPast['seisan']['kojo'][$i]) ? number_format($prefillPast['seisan']['kojo'][$i]) : '') }}">
                   千円
          </td>
        </tr>
        @endfor
        </tbody>
       </table>
      </div>
     
      <table class="table-compact-p ms-0" style="width: 630px; table-layout: fixed;">
          <colgroup>					  
  					  <col style="width:330px">
  					  <col style="width:130px">
  				    <col style="width:130px">
  		    </colgroup>
           <tr>
            <td class="bg-cream text-center">合 計</td>
            <td class="border px-1 py-1">
            <input type="text" class="form-control d-inline input-small text-end" name="seisan_zoyo[110]" style="width: 90px; background-color: #f0f0f0;" readonly tabindex="-1"
                   value="{{ isset($prefillPast['seisan']['total']['zoyo']) ? number_format($prefillPast['seisan']['total']['zoyo']) : '' }}">
                   千円
            </td>
            <td class="border px-1 py-1">
              <input type="text" class="form-control d-inline input-small text-end past-field-calc" name="seisan_kojo[110]" style="width: 90px; background-color: #f0f0f0;" readonly tabindex="-1"
                     value="{{ isset($prefillPast['seisan']['total']['kojo']) ? number_format($prefillPast['seisan']['total']['kojo']) : '' }}">
                     千円
            </td>
          </tr>
      </table>
      
      <br>
      <br>
      <br>

   </div>
</div>



<script>
  // 3桁区切りは金額だけ
  function formatNumber(input) {
    let raw = input.value.replace(/,/g, '').replace(/[^\d]/g, '');
    if (raw === '') return;
    input.value = Number(raw).toLocaleString(); // 金額のみ
  }

  document.addEventListener('DOMContentLoaded', function () {
    const moneyInputs = document.querySelectorAll(
      'input[name^="rekinen_zoyo["], input[name^="rekinen_kojo["], ' +
      'input[name^="seisan_zoyo["], input[name^="seisan_kojo["]'
    );
    moneyInputs.forEach((input) => {
      input.addEventListener('blur', () => formatNumber(input));
    });
  });
</script>

<script>
  document.addEventListener('input', function (e) {
    if (!e.target.matches('input[name^="rekinen_year["], input[name^="seisan_year["], input[name="inherit_year"]')) return;
    // 全角→半角、数字以外除去
    e.target.value = e.target.value
      .replace(/[０-９]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0))
      .replace(/[^\d]/g, '');
  });

  // （任意）月・日も同様にガードしたい場合は下を有効化
  document.addEventListener('input', function (e) {
    if (!e.target.matches(
      'input[name^="rekinen_month["], input[name^="rekinen_day["], ' +
      'input[name^="seisan_month["], input[name^="seisan_day["], ' +
      'input[name="inherit_month"], input[name="inherit_day"]'
    )) return;
    e.target.value = e.target.value
      .replace(/[０-９]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0))
      .replace(/[^\d]/g, '');
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

  // ★ 関数名重複を解消（上書きバグ防止）
  function calculaterekinen_kojoTotal() {
  
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
      input.addEventListener('blur', calculaterekinen_kojoTotal);
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

  // ★ 関数名重複を解消（上書きバグ防止）
  function calculateseisan_kojoTotal() {  
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
      input.addEventListener('blur', calculateseisan_kojoTotal);
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
  function countHouteiByBunsi() {
    let count = 0;

    for (let i = 2; i <= 10; i++) {
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
  }

  // 各 bunsi[1〜10] に blur イベントを付与
  for (let i = 2; i <= 10; i++) {
    const input = document.querySelector(`input[name="bunsi[${i}]"]`);
    if (input) {
      input.addEventListener('blur', countHouteiByBunsi);
    }
  }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Enterキーで past_zouyo.blade 内の入力欄を次へ移動
  // ※ 初期表示で非表示タブにいても効くように、個別付与ではなく委譲で処理する
  const pastEnterSelector = [
    '#past-recipient-no',
    'input[name^="rekinen_year["]',
    'input[name^="rekinen_month["]',
    'input[name^="rekinen_day["]',
    'input[name^="rekinen_zoyo["]',
    'input[name^="rekinen_kojo["]',
    'input[name^="seisan_year["]',
    'input[name^="seisan_month["]',
    'input[name^="seisan_day["]',
    'input[name^="seisan_zoyo["]',
    'input[name^="seisan_kojo["]'
  ].join(', ');

  function isVisible(el) {
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function getPastFocusable() {
    return Array.from(document.querySelectorAll(pastEnterSelector)).filter((el) => {
      return !el.disabled
        && !el.readOnly
        && el.tabIndex !== -1
        && isVisible(el);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.isComposing) return; // IME変換中は無視
    if (!e.target.matches(pastEnterSelector)) return;
    if (e.target.disabled || e.target.readOnly || e.target.tabIndex === -1) return;

    // 既定の submit（＝「計算開始」）を止める
    e.preventDefault();
    e.stopPropagation();

    const focusable = getPastFocusable();
    const currentIndex = focusable.indexOf(e.target);
    if (currentIndex === -1) return;

    const next = focusable[currentIndex + 1];
    if (!next) return;

    next.focus();

    // 入力欄は値を選択して上書きしやすくする
    if (next.tagName === 'INPUT' && typeof next.select === 'function') {
      next.select();
    }
  }, true);
});
</script>



<script type="module">
  
  // 共通: data_id 解決（親フォーム/このセレクトの data-* / hidden の順）
  function resolveDataIdPast() {
    const sel  = document.getElementById('past-recipient-no');
    const form = document.getElementById('zouyo-input-form');
    return (
      sel?.dataset?.dataId ||
      form?.querySelector('input[name="data_id"]')?.value ||
      document.querySelector('input[name="data_id_display"]')?.value ||
      ''
    );
  }
  // 他の <script> からも使えるよう公開
  window.resolveDataIdPast = resolveDataIdPast;

  // 切替前受贈者を保存する関数（グローバル公開）
  async function autosaveCurrent(prevRecipientNo) {
    const form  = document.getElementById('zouyo-input-form');
    const token = document.querySelector('input[name="_token"]')?.value
               || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
               || '';
    if (!form) throw new Error('zouyo-input-form not found');

    const fd = new FormData();
    fd.set('_token', token);
    fd.set('autosave', '1');
    fd.set('data_id', (resolveDataIdPast() || '').toString());
    fd.set('recipient_no', (prevRecipientNo ?? '').toString());



    // --- ヘッダ（header_*）: 値があるものだけ送る ---
    ['header_year','header_month','header_day'].forEach(n => {
      const el = document.querySelector(`input[name="${n}"]`);
      if (el && el.value !== '') fd.set(n, el.value);
    });

    // --- 行データ（1..10） ---
    const pushRows = (prefixes, from, to) => {
      for (let i = from; i <= to; i++) {
        prefixes.forEach(p => {
          const el = document.querySelector(`input[name="${p}[${i}]"]`);
          if (el && el.name) fd.set(el.name, el.value ?? '');
        });
      }
    };
    // 暦年
    pushRows(['rekinen_year','rekinen_month','rekinen_day','rekinen_zoyo','rekinen_kojo'], 1, 10);
    // 精算
    pushRows(['seisan_year','seisan_month','seisan_day','seisan_zoyo','seisan_kojo'], 1, 10);
    // 合計（任意）
    ['rekinen_zoyo','rekinen_kojo','seisan_zoyo','seisan_kojo'].forEach(p => {
      const el = document.querySelector(`input[name="${p}[110]"]`);
      if (el && el.value !== '') fd.set(`${p}[110]`, el.value);
    });

    const response = await fetch(form.action, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
      body: fd,
    });
    if (!response.ok) throw new Error('保存に失敗しました。');

    const ct = response.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const raw = await response.text();
      return { ok: true, raw };
    }
    try { return await response.json(); } catch { return { ok: true }; }
  }
  window.autosaveCurrent = autosaveCurrent;

</script>



<script>
  // 年フィールド: 入力中も blur 後もカンマ禁止
  document.addEventListener('DOMContentLoaded', function () {
    const yearSelector =
      'input[name^="rekinen_year["], input[name^="seisan_year["], input[name="inherit_year"]';

    document.addEventListener('input', function (e) {
      if (!e.target.matches(yearSelector)) return;
      e.target.value = e.target.value
        .replace(/[０-９]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0)) // 全角→半角
        .replace(/[^\d]/g, ''); // 数字以外排除（カンマも除去）
    });

    // ★ blur時、他のフォーマッタが付けたカンマを最後に必ず剥がす
    document.addEventListener('blur', function (e) {
      if (!e.target.matches(yearSelector)) return;
      // 0ms遅延で他のblurハンドラが走った“後”に実行
      setTimeout(() => {
        e.target.value = e.target.value
          .replace(/[０-９]/g, ch => String.fromCharCode(ch.charCodeAt(0) - 0xFEE0))
          .replace(/[^\d]/g, '');
      }, 0);
    }, true); // ← captureでも拾っておく（確実性UP）
  });


</script>


<script type="module">
  document.addEventListener('DOMContentLoaded', function () {
    const recipientSelect = document.getElementById('past-recipient-no');
    if (!recipientSelect) return;

    // 選択中 option の表示名を取得
    const getSelectedRecipientName = () =>
      recipientSelect.options[recipientSelect.selectedIndex]?.text?.trim() || '';


    let prevRecipientNo = recipientSelect.value;  // ← 最初に保持　選択前


    // 受贈者の選択が変更されたときにデータ保存と取得を行う
    recipientSelect.addEventListener('change', async function () {
        
          const nextRecipientNo = recipientSelect.value; // ← 変更後の新しい番号          選択後
          
          if (!nextRecipientNo) return;
        
          // 1. UIだけでもすぐ変化させて体感向上
          const $name = document.getElementById('recipient-name');
          if ($name) $name.textContent = getSelectedRecipientName();
          const $amt = document.getElementById('recipient-amount');
          if ($amt) $amt.textContent = '読込中…';
        
          // 2. 保存（失敗してもスルー）
          try {
            await saveRecipientData(prevRecipientNo); //　選択前
          } catch (e) {
            console.warn('保存失敗', e);
          }
        
          // ✅ 3. ここで明示的にリセット
          clearRecipientInputFields();
        
          // 4. 新しい受贈者データを取得
          const fetched = await fetchRecipientData(nextRecipientNo);
        
          // 5. 表示（存在すれば）
          if (fetched) {
            displayRecipientData(fetched);
          } else {
            if ($amt) $amt.textContent = '-';
          }
          
         // 6. 「次回のため」に保持を更新
          prevRecipientNo = nextRecipientNo;          

    });

    // 初期表示：選択中の受贈者で即取得して表示
    if (recipientSelect.value) {
      fetchRecipientData(recipientSelect.value).then(data => {
        if (data) displayRecipientData(data);
        else {
          // 取得失敗時のフォールバック
          const $name = document.getElementById('recipient-name');
          if ($name) $name.textContent = getSelectedRecipientName();
        }
      });
    }
  });

  // 受贈者データを保存する関数
  async function saveRecipientData(recipientNo) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const formData = new FormData();
    formData.append('_token', token);
    formData.append('recipient_no', recipientNo);
    formData.append('data_id', window.resolveDataIdPast()); // ★ data_id を必ず送る


    // ★ 追加：提案書日付も保存対象にする（値があるものだけ送信）
    ['header_year','header_month','header_day'].forEach((name) => {
      const el = document.querySelector(`input[name="${name}"]`);
      if (el && (el.value ?? '').trim() !== '') {
        formData.append(name, el.value);
      }
    });
    
    // ★ ここから追加：入力フィールドを明示的にFormDataへ追加
    const inputPrefixes = [
      // 暦年
      'rekinen_year', 'rekinen_month', 'rekinen_day',
      'rekinen_zoyo', 'rekinen_kojo',
      // 精算
      'seisan_year', 'seisan_month', 'seisan_day',
      'seisan_zoyo', 'seisan_kojo',
    ];
    
    inputPrefixes.forEach(prefix => {
      for (let i = 1; i <= 10; i++) {
        const input = document.querySelector(`input[name="${prefix}[${i}]"]`);
        if (input) {
          formData.append(`${prefix}[${i}]`, input.value ?? '');
        }
      }
      // 合計欄 (110)
      const totalInput = document.querySelector(`input[name="${prefix}[110]"]`);
      if (totalInput) {
        formData.append(`${prefix}[110]`, totalInput.value ?? '');
      }
    });
    // ★ ここまで追加
    
    
    
  
    try {
      const res = await fetch("/zouyo/save/past", {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
      });
  
      const contentType = res.headers.get("content-type") || '';
      if (!res.ok) {
        let detail = '';
        try { detail = await res.text(); } catch {}
        console.error(`20251105_03 保存HTTP異常 ${res.status}:`, detail);
        return { success: false };
      }
      if (!contentType.includes("application/json")) {
        const text = await res.text();
        console.error('20251105_03 HTMLが返された:', text);
        return { success: false };
      }
  
      const data = await res.json();
      
      console.log('[保存成功] saveRecipientData:', data); // ←これ追加！
      
        // FormDataの中身確認（開発用ログ）
      for (let pair of formData.entries()) {
        console.log(`[FormData] ${pair[0]} = ${pair[1]}`);
      }
    
      // { success: true } 形式を期待。違っても success を推定。
      return typeof data?.success === 'boolean' ? data : { success: true, ...data };
      
      
      
  
    } catch (error) {
      console.error('20251105_02 受贈者データ保存に失敗しました', error);
      return { success: false };
    }
  }

  // 受贈者のデータを取得する関数
  async function fetchRecipientData(recipientNo) {
    try {
      const sel = document.getElementById('past-recipient-no');
      const baseUrl = sel?.dataset?.fetchUrl || '/zouyo/past/fetch';
      const qs = new URLSearchParams();
      const dataId = window.resolveDataIdPast();
      if (dataId) qs.set('data_id', String(dataId));
      if (recipientNo) qs.set('recipient_no', String(recipientNo));
      const url = `${baseUrl}?${qs.toString()}`;

      const response = await fetch(url, {
        method: 'GET',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'include',
      });


      if (!response.ok) {
        let detail = '';
        try { detail = JSON.stringify(await response.json()); } catch {}
        console.error(`20251105_06 取得HTTP異常 ${response.status}:`, detail);
        // サーバが未実装/HTML返却でも「何も起きない」を避けるためフォールバック
        return {
          status: 'ok',
          recipient_name: sel?.options[sel.selectedIndex]?.text?.trim() || '',
          amount: null,
        };
      }
 

      const data = await response.json();
      // { status: 'ok', ... } を想定。違っても寛容に扱う。
      if (data?.status && data.status !== 'ok') {
        console.error('20251105_05 受贈者データの取得に失敗しました', data);
        return null;
      }
      return data;
    } catch (error) {
      console.error('20251105_06 受贈者データの取得に失敗しました', error);
      // ネットワーク例外でもフォールバック
      const sel = document.getElementById('past-recipient-no');
      return {
        status: 'ok',
        recipient_name: sel?.options[sel.selectedIndex]?.text?.trim() || '',
        amount: null,
      };

    }
  }

  // 受贈者データを画面に表示する関数
  function displayRecipientData(data) {
    clearRecipientInputFields(); // ← ★ 最初に必ずリセット！

    document.getElementById('recipient-name').textContent =
      data.recipient_name || data.name || '名前不明';

    document.getElementById('recipient-amount').textContent =
      (data.amount_yen ?? data.amount ?? data.total_yen ?? '-')?.toLocaleString?.() ?? String(data.amount ?? '不明');

    // データが存在すれば入力欄にマッピング（暦年）
    if (data.rekinen) {
      for (let i = 1; i <= 10; i++) {
        const set = (field, value) => {
          const input = document.querySelector(`input[name="${field}[${i}]"]`);
          if (input) input.value = value ?? '';
        };
        set('rekinen_year',  data.rekinen.year?.[i]);
        set('rekinen_month', data.rekinen.month?.[i]);
        set('rekinen_day',   data.rekinen.day?.[i]);
        set('rekinen_zoyo',  formatAmount(data.rekinen.zoyo?.[i]));
        set('rekinen_kojo',  formatAmount(data.rekinen.kojo?.[i]));
      }
    }

    // データが存在すれば入力欄にマッピング（精算）
    if (data.seisan) {
      for (let i = 1; i <= 10; i++) {
        const set = (field, value) => {
          const input = document.querySelector(`input[name="${field}[${i}]"]`);
          if (input) input.value = value ?? '';
        };
        set('seisan_year',  data.seisan.year?.[i]);
        set('seisan_month', data.seisan.month?.[i]);
        set('seisan_day',   data.seisan.day?.[i]);
        set('seisan_zoyo',  formatAmount(data.seisan.zoyo?.[i]));
        set('seisan_kojo',  formatAmount(data.seisan.kojo?.[i]));
      }
    }

    // ★ 1〜10行の値から合計欄も再計算して反映する
    const sanitizeNumber = (str) =>
      parseInt(String(str).replace(/,/g, '').replace(/[^\d]/g, ''), 10) || 0;
    const formatWithComma = (num) => num.toLocaleString();

    const recalcTotal = (prefix) => {
      let total = 0;
      for (let i = 1; i <= 10; i++) {
        const input = document.querySelector(`input[name="${prefix}[${i}]"]`);
        if (input && input.value !== '') {
          total += sanitizeNumber(input.value);
        }
      }
      const totalInput = document.querySelector(`input[name="${prefix}[110]"]`);
      if (totalInput) {
        totalInput.value = total ? formatWithComma(total) : '';
      }
    };

    // 暦年贈与：贈与額・贈与税額
    recalcTotal('rekinen_zoyo');
    recalcTotal('rekinen_kojo');

    // 精算課税：贈与額・贈与税額
    recalcTotal('seisan_zoyo');
    recalcTotal('seisan_kojo');
  }
  
  
   // 千円のフォーマット関数（null・undefined対応）
   function formatAmount(val) {
     if (val == null || val === '') return '';
     return Number(val).toLocaleString();
   }
</script>

<script>
function clearRecipientInputFields() {
  const inputSelectors = [
    'rekinen_year', 'rekinen_month', 'rekinen_day',
    'rekinen_zoyo', 'rekinen_kojo',
    'seisan_year', 'seisan_month', 'seisan_day',
    'seisan_zoyo', 'seisan_kojo',
  ];

  inputSelectors.forEach(prefix => {
    for (let i = 1; i <= 10; i++) {
      const name = `${prefix}[${i}]`;
      const input = document.querySelector(`input[name="${name}"]`);
      if (input) {
        console.log(`✅ Clear: ${name}`);
        input.value = '';
      } else {
        console.warn(`⚠️ Not found: input[name="${name}"]`);
      }
    }

    // 合計欄 (110)
    const totalName = `${prefix}[110]`;
    const totalInput = document.querySelector(`input[name="${totalName}"]`);
    if (totalInput) {
      console.log(`✅ Clear total: ${totalName}`);
      totalInput.value = '';
    } else {
      console.warn(`⚠️ Not found total: input[name="${totalName}"]`);
    }
  });
}
</script>

