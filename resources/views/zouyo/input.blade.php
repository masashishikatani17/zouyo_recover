<!--   input.blade  -->


@extends('layouts.min')

@section('content')


<div class="container-grey" style="width: 1100px; max-width: 1500px;">
  

  <style>
    /* ▼ この画面では hidden 属性を唯一の表示制御にする */
    #zouyo-main-tab-content .tab-pane[hidden] {      
      display: none !important;
    }
    #zouyo-main-tab-content .tab-pane:not([hidden]) {
      display: block;
    }





    /* ▼ 計算中オーバーレイ */
    #zouyo-calculating-overlay[hidden] {
      display: none !important;
    }
    #zouyo-calculating-overlay {
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(0, 0, 0, 0.35);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    #zouyo-calculating-overlay .zouyo-calculating-box {
      min-width: 320px;
      max-width: 90vw;
      padding: 22px 28px;
      border-radius: 12px;
      background: #fff;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);

      display: flex;
      align-items: center;
      justify-content: center;
      gap: 14px;
      text-align: left;

    }

    #zouyo-calculating-overlay .zouyo-calculating-spinner {
      width: 28px;
      height: 28px;
      border: 3px solid #d9d9d9;
      border-top-color: #2563eb;
      border-radius: 50%;
      animation: zouyo-calculating-spin 0.8s linear infinite;
      flex: 0 0 auto;
    }
    #zouyo-calculating-overlay .zouyo-calculating-text {
      display: flex;
      flex-direction: column;
      justify-content: center;
    }





    #zouyo-calculating-overlay .zouyo-calculating-title {
      font-size: 20px;
      font-weight: 700;
      color: #333;
      margin-bottom: 0;
    }
    #zouyo-calculating-overlay .zouyo-calculating-sub {
      font-size: 14px;
      color: #666;
    }


    @keyframes zouyo-calculating-spin {
      from {
        transform: rotate(0deg);
      }
      to {
        transform: rotate(360deg);
      }
    }

  </style>


    @php
        $tabSaveRoute = match ($activeTab ?? '') {
            'input02' => 'zouyo.save.family',
            'input03' => 'zouyo.save.past',
            'input04' => 'zouyo.save.future',
            'input05' => 'zouyo.save.inheritance',
            default   => 'zouyo.save.family', // デフォルト
         };
    

        // ★ data_id をこのファイル内で一度だけ確定させて以後は必ずこれを参照する
        $resolvedDataId = $dataId
            ?? ($data->id ?? null)
            ?? request('data_id')
            ?? session('selected_data_id');


        $resolvedGuestId = $data->guest->id
            ?? $data->guest_id
            ?? request('guest_id');

        
    @endphp
  
  <form
    id="zouyo-input-form"
    method="POST"
    action="{{ route($tabSaveRoute) }}"
    data-data-id="{{ $resolvedDataId }}"
    data-save-inheritance-url="{{ route('zouyo.save.inheritance') }}"    
    >
    


    @csrf


    {{-- 画面上は編集不可にし、重複定義を避けるため hidden で1か所のみ --}}
    <input type="hidden" name="data_id" value="{{ old('data_id', $resolvedDataId) }}">
    <input type="hidden" name="redirect_to" value="input">
    <input type="hidden" name="show_result" value="1">

    @php
      use Illuminate\Support\Facades\Session;
      use Illuminate\Support\Facades\Cache;
      // 計算結果のフォールバックをセッションからも拾う
      $resultsData = $results ?? [];
      if (empty($resultsData)) {
          // まず従来のセッション直格納を確認（互換）
          $resultsData = Session::get('zouyo.results', []);
      }
      if (empty($resultsData)) {
          // 推奨：セッションに入っているキーで Cache から取得
          $key = Session::get('zouyo.results_key');
          if ($key) {
              $resultsData = Cache::get($key, []);
          }
      }

      // 通常の初回表示は常に「家族構成等(input02)」を既定にする。
      // ただし、計算開始後に戻ってきた場合のみ Controller から渡された
      // $activeTab='input07' を優先して PDF指定 を開く。
      $showResultFlag = ($showResult ?? null);
      if (is_null($showResultFlag)) {
          $showResultFlag = request()->boolean('show_result', false);
      }

      $activeTab = $activeTab ?? 'input02';


      function isActiveTab($tab, $activeTab) {
          return $tab === $activeTab ? 'active' : '';
      }
      function isShowActiveTab($tab, $activeTab) {
          return $tab === $activeTab ? 'show active' : 'fade';
      }
    @endphp
    
    

    <img src="{{ asset('storage/images/ribbon.jpg') }}" alt="…">
       <h13>最適贈与額計算システム"贈与名人"・インプット表</h13>
   <div class="wrapper"> 
      <div class="d-flex flex-wrap justify-content-end gap-2">


        <a id="zouyo-back-btn"
           href="{{ route('zouyo.data.index', array_filter([
              'guest_id' => $resolvedGuestId,
              'data_id'  => $resolvedDataId,
           ])) }}"        
           class="btn btn-base-blue">戻 る</a>


        <!--
        <button type="submit"
                class="btn btn-outline-secondary btn-sm"
                formnovalidate
                name="redirect_to"
                value="master">マスター</button>
        -->

        {{-- ▼ マスター一覧はPOSTせず遷移のみ（オートセーブに干渉しない） --}}
        <a id="zouyo-master-btn"
           href="{{ route('zouyo.master', ['data_id' => $resolvedDataId ?? '']) }}"
           class="btn btn-base-blue">マスター</a>

        
        <!--
        <button type="submit" class="btn btn-success btn-sm" formnovalidate>保存</button>
        -->

        {{-- ▼ ヘッダー右側に「計算開始」ボタンを配置（フォーム参照付き）
             ※ formaction に data_id をクエリで明示付与してバリデーションを確実に通す --}}
        <button type="submit"
                class="btn  btn-base-red"
                form="zouyo-input-form"  {{-- フォーム外に出す可能性も考慮して明示 --}}
                id="zouyo-calc-btn"


                {{-- ★ hiddenが空でも必ず ?data_id=... を送る --}}
                @php
                  $resolvedDataId = $resolvedDataId
                    ?? ($dataId ?? ($data->id ?? request('data_id')));
                @endphp

                formaction="{{ route('zouyo.calc', ['data_id' => $resolvedDataId]) }}"
                formmethod="POST"
                title="現在の入力内容で計算を実行します">計算開始</button>
      </div>
    

<br>


    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <ul class="nav nav-tabs" id="zouyo-main-tabs" role="tablist">
       
     <li class="nav-item" role="presentation">
        <button 
              class="nav-link {{ isActiveTab('input01', $activeTab) }}" 
              id="zouyo-tab-input01-nav" 
              data-bs-toggle="tab" 
              data-bs-target="#zouyo-tab-input01" 
              type="button" 
              role="tab" 
              aria-controls="zouyo-tab-input01" 
              aria-selected="{{ $activeTab === 'input01' ? 'true' : 'false' }}">
              はじめに
         </button>
     </li>
       
     <li class="nav-item" role="presentation">
        <button 
              class="nav-link {{ isActiveTab('input02', $activeTab) }}" 
              id="zouyo-tab-input02-nav" 
              data-bs-toggle="tab" 
              data-bs-target="#zouyo-tab-input02" 
              type="button" 
              role="tab" 
              aria-controls="zouyo-tab-input02" 
              aria-selected="{{ $activeTab === 'input02' ? 'true' : 'false' }}">
              家族構成等
         </button>
     </li>
       
     <li class="nav-item" role="presentation">
        <button 
              class="nav-link {{ isActiveTab('input03', $activeTab) }}" 
              id="zouyo-tab-input03-nav" 
              data-bs-toggle="tab" 
              data-bs-target="#zouyo-tab-input03" 
              type="button" 
              role="tab" 
              aria-controls="zouyo-tab-input03" 
              aria-selected="{{ $activeTab === 'input03' ? 'true' : 'false' }}">
              過年度の贈与
         </button>
     </li>
       
     <li class="nav-item" role="presentation">
        <button 
              class="nav-link {{ isActiveTab('input05', $activeTab) }}" 
              id="zouyo-tab-input05-nav"
              data-bs-toggle="tab" 
              data-bs-target="#zouyo-tab-input05"              
              type="button" role="tab" 
              aria-controls="zouyo-tab-input05" 
              aria-selected="{{ $activeTab === 'input05' ? 'true' : 'false' }}">
              遺産分割等
         </button>
     </li>
       
     <li class="nav-item" role="presentation">
        <button 
              class="nav-link {{ isActiveTab('input04', $activeTab) }}" 
              id="zouyo-tab-input04-nav" 
              data-bs-toggle="tab" 
              data-bs-target="#zouyo-tab-input04" 
              type="button" 
              role="tab" 
              aria-controls="zouyo-tab-input04" 
              aria-selected="{{ $activeTab === 'input04' ? 'true' : 'false' }}">
              これからの贈与
</button>
     </li>
       
     <li class="nav-item" role="presentation">
        <button 
              class="nav-link {{ isActiveTab('input06', $activeTab) }}" 
              id="zouyo-tab-input06-nav" 
              data-bs-toggle="tab" 
              data-bs-target="#zouyo-tab-input06" 
              type="button" 
              role="tab" 
              aria-controls="zouyo-tab-input06" 
              aria-selected="{{ $activeTab === 'input06' ? 'true' : 'false' }}">
              おわりに
         </button>
     </li>
       

     <li class="nav-item" role="presentation">
        <button
              class="nav-link {{ isActiveTab('input07', $activeTab) }}"
              id="zouyo-tab-input07-nav"
              data-bs-toggle="tab"
              data-bs-target="#zouyo-tab-input07"
              type="button"
              role="tab"
              aria-controls="zouyo-tab-input07"
              aria-selected="{{ $activeTab === 'input07' ? 'true' : 'false' }}">
              PDF指定
         </button>
     </li>

    </ul>


    <div class="tab-content" id="zouyo-main-tab-content">
            
            <div class="tab-pane {{ isShowActiveTab('input01', $activeTab) }}"
                 id="zouyo-tab-input01"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input01-nav"
                 @if($activeTab !== 'input01') hidden @endif>
              {{-- 結果詳細 --}}
              <br>
              <p>はじめに</p>
            </div>
             

            <div class="tab-pane {{ isShowActiveTab('input02', $activeTab) }}"
                 id="zouyo-tab-input02"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input02-nav"
                 @if($activeTab !== 'input02') hidden @endif>              
              @include('zouyo.tabs.title')
            </div>
            
            
            <div class="tab-pane {{ isShowActiveTab('input03', $activeTab) }}"
                 id="zouyo-tab-input03"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input03-nav"
                 data-data-id="{{ $resolvedDataId }}"
                 @if($activeTab !== 'input03') hidden @endif>
               @include('zouyo.tabs.past_zouyo')
            </div>

            <div class="tab-pane {{ isShowActiveTab('input04', $activeTab) }}"
                 id="zouyo-tab-input04"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input04-nav"
                 data-data-id="{{ $resolvedDataId }}"
                 @if($activeTab !== 'input04') hidden @endif>
               @include('zouyo.tabs.future_zouyo')
            </div>

            <div class="tab-pane {{ isShowActiveTab('input05', $activeTab) }}"
                 id="zouyo-tab-input05"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input05-nav"
                 @if($activeTab !== 'input05') hidden @endif>              
              @include('zouyo.tabs.isanbunkatu')
            </div>
            
            <div class="tab-pane {{ isShowActiveTab('input06', $activeTab) }}"
                 id="zouyo-tab-input06"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input06-nav"
                 @if($activeTab !== 'input06') hidden @endif>
              <br>
              <p>おわりに</p>


            </div>


            <div class="tab-pane {{ isShowActiveTab('input07', $activeTab) }}"
                 id="zouyo-tab-input07"
                 role="tabpanel"
                 aria-labelledby="zouyo-tab-input07-nav"
                 data-data-id="{{ $resolvedDataId }}"

                 @if($activeTab !== 'input07') hidden @endif>

                @include('zouyo.tabs.pdf_shitei', [
                   'dataId' => $resolvedDataId,
                ])
 
            </div>


       </div>  {{-- .tab-content --}}
    </div>    {{-- .wrapper --}}
  </form>
  

  {{-- ▼ PDF用の独立フォーム
       親フォーム（#zouyo-input-form）の外に置くことで form ネストを回避する --}}
  <form id="zouyo-pdf-form" action="{{ route('generate_pdf') }}" method="POST" target="_blank" style="display:none;">
    @csrf
    <input type="hidden" name="data_id" value="{{ $resolvedDataId }}">
  </form>  
  

</div>



{{-- ▼ 計算中オーバーレイ --}}
<div id="zouyo-calculating-overlay" hidden aria-live="polite" aria-busy="true">
  <div class="zouyo-calculating-box">

    <div class="zouyo-calculating-spinner" aria-hidden="true"></div>
    <div class="zouyo-calculating-text">
      <div class="zouyo-calculating-title">ただいま計算中です</div>
      {{--
      <div class="zouyo-calculating-sub">しばらくお待ちください。</div>
      --}}
    </div>

  </div>
</div>




{{-- ▼ グローバルに data_id を公開（タブ切替先の部分ビューからも取得できるように） --}}
<script>
  // サーバ値の堅牢なフォールバック（$data->id / request('data_id')）
  window.APP_DATA_ID = @json($resolvedDataId);
  
</script>



<script>
function z_showCalculatingOverlay() {
  const el = document.getElementById('zouyo-calculating-overlay');
  if (!el) return;
  el.hidden = false;
  el.style.display = 'flex';
}


function z_hideCalculatingOverlay() {
  const el = document.getElementById('zouyo-calculating-overlay');
  if (!el) return;
  el.hidden = true;
  el.style.display = 'none';
}


function z_markCalcInProgress() {
  try {
    sessionStorage.setItem('zouyo_calc_in_progress', '1');
  } catch (_) {}
}


function z_clearCalcInProgress() {
  try {
    sessionStorage.removeItem('zouyo_calc_in_progress');
} catch (_) {}
}


function z_isCalcInProgress() {
  try {
    return sessionStorage.getItem('zouyo_calc_in_progress') === '1';
  } catch (_) {
    return false;
  }
}

</script>










{{-- ▼ data_id を確実に埋めてから送信するためのフォールバック --}}
<script>

  // まずサーバが決めた resolved を最優先で使う
  window.Z_RESOLVED_DATA_ID = (function(){
    try { return String(@json($resolvedDataId) ?? '').trim(); } catch(_) { return ''; }
  })();


  // URLパラメータ取得
  function z_getQueryParam(name) {
    try {
      const u = new URL(window.location.href);
      return u.searchParams.get(name) || '';
    } catch (_) { return ''; }
  }
  // data_idの最終決定ロジック（空なら順にフォールバック）
  function z_resolveDataId() {
    try {
      const form = document.getElementById('zouyo-input-form');
      const hid  = form?.querySelector('input[name="data_id"]');
      const pane3 = document.querySelector('#zouyo-tab-input03');
      const pane4 = document.querySelector('#zouyo-tab-input04');
      return (
        (window.Z_RESOLVED_DATA_ID && String(window.Z_RESOLVED_DATA_ID).trim()) ||
        (hid?.value && hid.value.trim()) ||
        (typeof window.APP_DATA_ID !== 'undefined' && String(window.APP_DATA_ID || '').trim()) ||
        (form?.dataset?.dataId && form.dataset.dataId.trim()) ||
        (pane3?.dataset?.dataId && pane3.dataset.dataId.trim()) ||
        (pane4?.dataset?.dataId && pane4.dataset.dataId.trim()) ||
        (z_getQueryParam('data_id') && z_getQueryParam('data_id').trim()) ||
        ''
      );
    } catch (_) { return ''; }
  }
  // 初期ロード時と送信直前に反映
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zouyo-input-form');
    if (!form) return;
    const hid = form.querySelector('input[name="data_id"]');
    const did = z_resolveDataId();
    if (hid && (!hid.value || hid.value.trim() === '') && did) { hid.value = did; form.dataset.dataId = did; }
    form.addEventListener('submit', () => {
      const cur = hid?.value?.trim() || '';
      if (!cur) { const d = z_resolveDataId(); if (d) { hid.value = d; form.dataset.dataId = d; } }
    });
  });
</script>

{{-- ▼ 「家族構成等」タブで入力した氏名を
       「過年度の贈与」「これからの贈与」「遺産分割等」タブに反映するヘルパ --}}
<script>

// 「家族構成等」タブの氏名 → 「過年度の贈与」タブの表示に反映
function z_syncPastNamesFromTitle() {
  const titlePane = document.querySelector('#zouyo-tab-input02');
  const pastPane  = document.querySelector('#zouyo-tab-input03');
  if (!titlePane || !pastPane) return;

  // 贈与者：name[1] → customer_name（past_zouyo 側）
  const donorSrc = titlePane.querySelector('input[name="name[1]"]');
  const donorDst = pastPane.querySelector('input[name="customer_name"]');
  if (donorSrc && donorDst) {
    const v = (donorSrc.value ?? '').trim();
    if (v !== '') donorDst.value = v;
  }

  // 受贈者セレクト：name[2..10] → #past-recipient-no の option
  const sel = pastPane.querySelector('#past-recipient-no');
  if (!sel) return;

  const prevValue = sel.value; // もともとの選択を覚えておく
  sel.innerHTML = '';          // いったん全部クリア

  for (let i = 2; i <= 10; i++) {
    const src = titlePane.querySelector(`input[name="name[${i}]"]`);
    const label = (src?.value ?? '').trim();
    if (!label) continue; // 氏名が空なら option 生成しない

    const opt = document.createElement('option');
    opt.value = String(i);
    opt.textContent = label;
    if (prevValue && prevValue === String(i)) {
      opt.selected = true;
    }
    sel.appendChild(opt);
  }

  // 何も選ばれていなければ先頭を選択
  if (!sel.value && sel.options.length > 0) {
    sel.selectedIndex = 0;
  }
}



// 「家族構成等」タブの氏名 → 「これからの贈与」タブの表示に反映
function z_syncFutureNamesFromTitle() {
  const titlePane  = document.querySelector('#zouyo-tab-input02');
  const futurePane = document.querySelector('#zouyo-tab-input04');
  if (!titlePane || !futurePane) return;

  // 贈与者：name[1] → customer_name（future_zouyo 側）
  const donorSrc = titlePane.querySelector('input[name="name[1]"]');
  const donorDst = futurePane.querySelector('input[name="customer_name"]');
  if (donorSrc && donorDst) {
    const v = (donorSrc.value ?? '').trim();
    if (v !== '') donorDst.value = v;
  }

  // 受贈者セレクト：name[2..10] → #future-recipient-no の option ラベル
  const sel = futurePane.querySelector('#future-recipient-no');
  if (!sel) return;

  // 既存 option は data-* を保持したまま text だけ書き換える
  Array.from(sel.options).forEach((opt) => {
    const no = parseInt(opt.value, 10);
    if (!Number.isInteger(no) || no < 2 || no > 10) return;
    const src = titlePane.querySelector(`input[name="name[${no}]"]`);
    const label = (src?.value ?? '').trim();
    if (label !== '') {
      opt.textContent = label;
    }
  });

  // 選択値が空で、選択可能な option があるなら先頭を選択
  if (!sel.value && sel.options.length > 0) {
    sel.selectedIndex = 0;
  }
}


// 「家族構成等」タブの氏名・続柄 → 「遺産分割等」タブの表示に反映
function z_syncIsanNamesFromTitle() {
  const titlePane = document.querySelector('#zouyo-tab-input02');
  const isanPane  = document.querySelector('#zouyo-tab-input05');
  if (!titlePane || !isanPane) return;

  // 被相続人：name[1] → customer_name（isanbunkatu 側）
  const donorSrc = titlePane.querySelector('input[name="name[1]"]');
  const donorDst = isanPane.querySelector('input[name="customer_name"]');
  if (donorSrc && donorDst) {
    const v = (donorSrc.value ?? '').trim();
    if (v !== '') donorDst.value = v;
  }

  // 遺産分割表ヘッダ（相続人氏名・続柄）のテーブルを取得
  // このタブには table-auto が複数あるので、最後の border付きテーブルを採用
  const tables = isanPane.querySelectorAll('table.table-auto');
  let table = null;
  if (tables.length > 0) {
    // 最後のテーブル（大きい遺産分割表）を選ぶ
    table = tables[tables.length - 1];
  }
  if (!table) return;

  const headerRows = table.querySelectorAll('thead tr');
  if (headerRows.length < 3) return;

  // 2行目: 氏名ヘッダ, 3行目: 続柄ヘッダ（ともに相続人2〜10の9列）
  const nameCells = headerRows[1].querySelectorAll('th');
  const relCells  = headerRows[2].querySelectorAll('th');

  // config/relationships.php を Blade 経由でJSへ渡した共通マスタを使う
  const REL_LABELS = window.Z_RELATIONSHIP_LABELS || {};


  for (let idx = 0; idx < 9; idx++) {
    const no = 2 + idx;

    //
    // ① 氏名: title タブの name[no] をそのまま
    //
    const nameInput =
      titlePane.querySelector(`input[name="name[${no}]"]`) ||
      titlePane.querySelector(`input[name="name.${no}"]`);
    const rawName = (nameInput?.value ?? '').trim();
    if (nameCells[idx]) {
      nameCells[idx].textContent = rawName;
    }


    //
    // ② 続柄: 「現在画面に表示されている入力値」を最優先で採用する
    //    - relationship_code / zokugara 系の select
    //    - 上記が無ければ input をフォールバック
    //    - name 属性は
    //        relationship_code[2] / relationship_code.2 /
    //        family[2][relationship_code] / family.2.relationship_code /
    //        zokugara[2] / family[2][zokugara]
    //      など色々想定されるので「name*="relationship"」「name*="zokugara"」で広く拾う
    //

    // 最優先: select（プルダウン）の現在選択ラベルを使う
    const relSelect =
      titlePane.querySelector(`select[name*="[${no}]"][name*="relationship"]`) ||
      titlePane.querySelector(`select[name*=".${no}"][name*="relationship"]`)  ||
      titlePane.querySelector(`select[name*="[${no}]"][name*="zokugara"]`)     ||
      titlePane.querySelector(`select[name*=".${no}"][name*="zokugara"]`);

    // 次善: input 等（hidden / text）の value をコードとして解釈
    const relInput =
      titlePane.querySelector(`input[name*="[${no}]"][name*="relationship"]`) ||
      titlePane.querySelector(`input[name*=".${no}"][name*="relationship"]`)  ||
      titlePane.querySelector(`input[name*="[${no}]"][name*="zokugara"]`)     ||
      titlePane.querySelector(`input[name*=".${no}"][name*="zokugara"]`);

    const relEl = relSelect || relInput;



    // 対応する入力が無ければ既存表示を維持
    if (!relEl) {
      continue;
    }

    let relLabel = '';

    // ▼ <select> の場合は選択中 option のテキストをそのまま採用（「長男」「長女」など）
    if (relEl.tagName && relEl.tagName.toUpperCase() === 'SELECT') {
      const opt = relEl.options[relEl.selectedIndex] || null;
      relLabel = (opt && typeof opt.text === 'string') ? opt.text.trim() : '';
    } else {
      // input 等の場合は value からコードを解釈してラベルに変換
      const codeStr = (relEl.value ?? '').trim();
      if (codeStr !== '') {
        const num = parseInt(codeStr.replace(/[^\d\-]/g, ''), 10);
        if (!Number.isNaN(num) && Object.prototype.hasOwnProperty.call(REL_LABELS, num)) {
          relLabel = REL_LABELS[num];
        } else {
          // マスタにないコードなら、そのまま表示
          relLabel = codeStr;
        }
      }
    }

    // ラベルが取得できた場合のみ上書き（空なら既存表示を保持）
    if (relCells[idx] && relLabel !== '') {
      relCells[idx].textContent = relLabel;
    }



  }
}


</script>


<script>
document.addEventListener('shown.bs.tab', function (e) {
   const targetSelector = e.target?.getAttribute?.('data-bs-target') || '';
   const activeTabPane = document.querySelector(targetSelector);
   if (!activeTabPane) return;

  // 初回以外のタブ切替時にも data-id を pane に付与
  // ★ data_id をタブ配下へ伝搬（dataset に載せる／必要要素にも付与）
  try {
    const form = document.getElementById('zouyo-input-form');
    const dataId =
      (typeof window.APP_DATA_ID !== 'undefined' && window.APP_DATA_ID) ||
      form?.dataset?.dataId ||
      form?.querySelector('input[name="data_id"]')?.value ||
      '';
    if (dataId) {
      activeTabPane.dataset.dataId = dataId;
      // 代表要素があれば個別にも埋める
      const pastSel   = activeTabPane.querySelector('#past-recipient-no');
      const futureSel = activeTabPane.querySelector('#future-recipient-no');
      if (pastSel)   pastSel.dataset.dataId = dataId;
      if (futureSel) futureSel.dataset.dataId = dataId;
    }
  } catch (_) {}



  // (A) テーブルの再描画（forEachの中はこの処理のみに限定）
  const tables = activeTabPane.querySelectorAll('table');
  tables.forEach((table) => {
    const prevLayout = table.style.tableLayout;
    table.style.tableLayout = 'auto';
    void table.offsetWidth; // Reflow 強制
    table.style.tableLayout = prevLayout || 'fixed';
  });


  // (B-0) 「過年度の贈与」タブが開かれたら、titleタブの氏名から贈与者・受贈者を同期
  if (targetSelector === '#zouyo-tab-input03') {
    try {
      z_syncPastNamesFromTitle();
    } catch (err) {
      console.error('[z_syncPastNamesFromTitle] failed', err);
    }
  }


  // (B-0-2) 「遺産分割等」タブが開かれたら、titleタブの氏名・続柄からヘッダを同期し、その後に必ず再計算する
  if (targetSelector === '#zouyo-tab-input05') {
  
    try {
      z_syncIsanNamesFromTitle();
    } catch (err) {
      console.error('[z_syncIsanNamesFromTitle] failed', err);
    }
    
    try {

      // shown.bs.tab の取りこぼしや表示同期遅延を吸収してから再計算する
      z_queueIsanPreviewAfterTabOpen();

    } catch (err) {
      console.error('[z_requestIsanBeforePreview] failed', err);
    }      

  }


  // (B) 「これからの贈与」タブが開かれたら、選択中の受贈者データを取得→表示
  if (targetSelector === '#zouyo-tab-input04') {
  
    // まず氏名を title タブの入力で更新
    try {
      z_syncFutureNamesFromTitle();
    } catch (err) {
      console.error('[z_syncFutureNamesFromTitle] failed', err);
    }

    // 将来、future_zouyo 側で公開する高機能ハンドラがあればそちらを優先
    if (typeof window.z_onFutureTabShown === 'function') {
      try { window.z_onFutureTabShown(); } catch (err) { console.error(err); }
      return;
    }
    // フォールバック：現在のセレクトとAPIから直接取得→反映
    const sel = document.getElementById('future-recipient-no');
    
    
    
    if (!sel) return;
    const rn = sel.value || '';
    if (!rn) return;
    const fetchUrl = sel.dataset.fetchUrl;
    
    
    
    // ★ 念のためここでも data-id を付与
    if (!sel.dataset.dataId) {
      const did =
        (typeof window.APP_DATA_ID !== 'undefined' && window.APP_DATA_ID) ||
        document.querySelector('input[name="data_id"]')?.value || '';
      if (did) sel.dataset.dataId = did;
    }


    // future_zouyo.blade で公開しているユーティリティを使用（存在チェック付き）
    if (typeof fetchRecipientFuture === 'function' && typeof applyFuturePayload === 'function') {
      // 取得前に軽く描画をクリア（必要最低限：入力欄が残っても apply が上書きするため省略可）
      try {
        // クリア関数が公開されている場合のみ呼ぶ
        if (typeof window.clearFutureInputs === 'function') window.clearFutureInputs();
      } catch (_) {}

      fetchRecipientFuture(fetchUrl, rn)
        .then((payload) => {
          try {

            const v4 = payload?.plan?.cal_amount?.[4] ?? null;
            console.info('[future-tab] fetched cal_amount[4]=', v4, 'debug=', payload?.debug);
            // 画面右の保存ピルがあれば、受信値を一瞬表示（視覚確認）
            const pill = document.getElementById('future-save-status');
            if (pill && v4 !== null && v4 !== undefined) {
              pill.classList.remove('is-error','is-idle','is-saving');
              pill.classList.add('is-success');
              pill.textContent = `受信: cal_amount[4]=${v4.toLocaleString?.() ?? v4}`;
              setTimeout(()=>{ pill.classList.remove('is-success'); pill.classList.add('is-idle'); }, 1800);
            }
          } catch(_) {}

          if (payload) applyFuturePayload(payload);

        })

    }
    // 既存の要約更新があれば併用
    if (typeof window.z_refreshFutureSummary === 'function') {
      try { window.z_refreshFutureSummary(); } catch (err) { console.error(err); }
    }

  }  
});

</script>



<script>
// 初期ロード直後に、主要 pane/セレクトへも data-id を一括埋め込み
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('zouyo-input-form');
  const did =
    (typeof window.APP_DATA_ID !== 'undefined' && window.APP_DATA_ID) ||
    form?.dataset?.dataId ||
    form?.querySelector('input[name="data_id"]')?.value || '';
  if (!did) return;
  ['#zouyo-tab-input03', '#zouyo-tab-input04', '#zouyo-tab-input05', '#zouyo-tab-input07'].forEach(sel => {
    const pane = document.querySelector(sel);
    if (pane) pane.dataset.dataId = did;
  });
  const pastSel   = document.getElementById('past-recipient-no');
  const futureSel = document.getElementById('future-recipient-no');
  if (pastSel)   pastSel.dataset.dataId = did;
  if (futureSel) futureSel.dataset.dataId = did;
});
</script>

<script>
// 計算後は PDF指定 タブを自動で開く
document.addEventListener('DOMContentLoaded', function () {
  try {

    if (z_isCalcInProgress()) {
      z_showCalculatingOverlay();
    }




    const shouldOpenPdfTab = sessionStorage.getItem('zouyo_open_pdf_tab_after_calc') === '1';

    if (!shouldOpenPdfTab) {
      // ▼ 既に計算中フラグだけ残っていて、しかもPDF指定タブが初期表示で開いている場合はここで閉じる
      const alreadyActivePdfBtn = document.querySelector('#zouyo-main-tabs .nav-link.active[data-bs-target="#zouyo-tab-input07"]');
      const alreadyActivePdfPane = document.querySelector('#zouyo-tab-input07');
      const isPdfVisible =
        !!alreadyActivePdfBtn ||
        (!!alreadyActivePdfPane && !alreadyActivePdfPane.hidden && alreadyActivePdfPane.style.display !== 'none');

      if (isPdfVisible && z_isCalcInProgress()) {
        z_hideCalculatingOverlay();
        z_clearCalcInProgress();
      }
      return;
    }


    const pdfTabBtn = document.getElementById('zouyo-tab-input07-nav');

    if (!pdfTabBtn || typeof bootstrap === 'undefined' || !bootstrap.Tab) {
      // ▼ BootstrapのTab制御が使えない場合でも、初期表示でPDF指定タブが見えていれば閉じる
      const pdfPane = document.getElementById('zouyo-tab-input07');
      const isPdfVisible = !!pdfPane && !pdfPane.hidden && pdfPane.style.display !== 'none';
      if (isPdfVisible && z_isCalcInProgress()) {
        z_hideCalculatingOverlay();
        z_clearCalcInProgress();
      }
      return;
    }

    // ▼ すでに PDF指定タブが active の場合は shown.bs.tab が発火しないため、ここで閉じる
    const isAlreadyActive =
      pdfTabBtn.classList.contains('active') ||
      pdfTabBtn.getAttribute('aria-selected') === 'true';
    if (isAlreadyActive) {
      z_hideCalculatingOverlay();
      z_clearCalcInProgress();
      return;
    }


    

    bootstrap.Tab.getOrCreateInstance(pdfTabBtn).show();
  } catch (e) {
    console.error('[open-pdf-tab-after-calc] failed:', e);
  } finally {
    try { sessionStorage.removeItem('zouyo_open_pdf_tab_after_calc'); } catch (_) {}
  }
});
</script>



<script>
// ▼ 計算後に PDF指定タブが実際に表示されたタイミングでオーバーレイを閉じる（案B）
document.addEventListener('shown.bs.tab', function (e) {
  try {
    const targetSelector = e.target?.getAttribute?.('data-bs-target') || '';
    if (targetSelector !== '#zouyo-tab-input07') return;
    if (!z_isCalcInProgress()) return;

    z_hideCalculatingOverlay();
    z_clearCalcInProgress();
  } catch (err) {
    console.error('[calc-overlay-close] failed:', err);
  }
});
</script>




<script>
// ▼ tab-pane の表示制御は hidden 属性だけに統一する
function z_syncMainTabVisibility(activeSelector) {

  const tabContent = document.getElementById('zouyo-main-tab-content');

  if (tabContent) {

    tabContent.style.display = 'block';

  }

  // ▼ PDF指定タブは ID で必ず明示制御する
  const pdfPane = document.getElementById('zouyo-tab-input07');
  if (pdfPane) {
    const isPdfActive = activeSelector === '#zouyo-tab-input07';
    pdfPane.hidden = !isPdfActive;
    pdfPane.style.display = isPdfActive ? 'block' : 'none';
  }

  // ▼ 他タブも descendant selector で拾って制御する
  document.querySelectorAll('#zouyo-main-tab-content .tab-pane').forEach(function (pane) {

    const isActive = activeSelector && ('#' + pane.id) === activeSelector;
    
    pane.hidden = !isActive;
    pane.style.display = isActive ? 'block' : 'none';
  });  
  
}

function z_getActiveMainTabSelector() {
  const activeBtn = document.querySelector('#zouyo-main-tabs .nav-link.active[data-bs-target]');
  return activeBtn ? activeBtn.getAttribute('data-bs-target') : '';
}


document.addEventListener('DOMContentLoaded', function () {

  const initialSelector = z_getActiveMainTabSelector() || '#zouyo-tab-{{ $activeTab }}';
   z_syncMainTabVisibility(initialSelector);

  // ▼ Bootstrap の shown.bs.tab が取りこぼされても、
  //    nav-link の active 変化を監視して必ず pane 表示を同期する
  const navButtons = document.querySelectorAll('#zouyo-main-tabs .nav-link[data-bs-target]');
  if (navButtons.length > 0 && typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(function () {
      const currentSelector = z_getActiveMainTabSelector();
      if (currentSelector) {
        z_syncMainTabVisibility(currentSelector);
      }
    });

    navButtons.forEach(function (btn) {
      observer.observe(btn, {
        attributes: true,
        attributeFilter: ['class', 'aria-selected']
      });

      // クリック直後にも一度同期
      btn.addEventListener('click', function () {
        const sel = btn.getAttribute('data-bs-target') || '';
        if (!sel) return;
        window.setTimeout(function () {
          const currentSelector = z_getActiveMainTabSelector() || sel;
          z_syncMainTabVisibility(currentSelector);
        }, 0);
      });
    });
  }  
  
});

document.addEventListener('shown.bs.tab', function (e) {
  const targetSelector = e.target?.getAttribute?.('data-bs-target') || '';
  z_syncMainTabVisibility(targetSelector);
});
</script>


<script>
// ▼ 変更点の主役：タブクリック時に「離脱元タブの入力」を保存 → 成功したら切替
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('zouyo-input-form');
  const nav = document.getElementById('zouyo-main-tabs');
  if (!form || !nav) return;

  // ▼ PHP側の match($activeTab) と同義の保存先URLテーブルをJSにも用意
  //   - 「今開いているタブ」＝離脱元タブのキーで保存先を切り替える
  const SAVE_URLS = {
    input02: "{{ route('zouyo.save.family') }}",
    input03: "{{ route('zouyo.save.past') }}",
    input04: "{{ route('zouyo.save.future') }}",
    input05: "{{ route('zouyo.save.inheritance') }}",
  };
  

  const isAutosaveTargetTab = (tabKey) => {
    return Object.prototype.hasOwnProperty.call(SAVE_URLS, tabKey);
  };  
  

  const getNextTabKey = (targetSelector) => {
    /* "#zouyo-tab-input03" → "input03" */
    const m = (targetSelector || '').match(new RegExp('#zouyo-tab-(.+)$'));
    return m ? m[1] : '';
  };

  const getCurrentTabKey = () => {

    // ① nav-link.active から取得（通常ケース）
    const activeBtn = nav.querySelector('.nav-link.active[id^="zouyo-tab-"][data-bs-toggle="tab"]');
    if (activeBtn) {
      const sel = activeBtn.getAttribute('data-bs-target') || '';
      const m = sel.match(new RegExp('#zouyo-tab-(.+)$'));
      if (m) return m[1];
    }
    // ② フォールバック：pane 側から推定
    const activePane = document.querySelector('.tab-pane.show.active[id^="zouyo-tab-"]');
    if (activePane) {
      const id = activePane.id || '';               // 例: "zouyo-tab-input04"
      const m2 = id.match(new RegExp('^zouyo-tab-(.+)$'));
      if (m2) return m2[1];
    }
    return '';

  };

  // fromTabKey: 現在開いている（これから離脱する）タブ
  // nextTabKey: 次に表示するタブ（ログ用途・サーバ側で必要なら送る）
  const autosave = async (fromTabKey, nextTabKey) => {
    const token = form.querySelector('input[name="_token"]')?.value || '';
    // ★修正: フォーム丸ごとではなく空の FormData に必要項目だけ積む
    const fd = new FormData();
    fd.set('_token', token);

    // --- data_id を確実に付与 ---
    let hidDataId = form.querySelector('input[name="data_id"]')?.value?.trim();
    if (!hidDataId || hidDataId === '') {
        hidDataId = '{{ (isset($dataId) && $dataId > 0) ? $dataId : 1 }}';
    }
    fd.set('data_id', hidDataId);

    // サーバに「今回保存対象のタブ（離脱元）」と「遷移先」を両方伝える
    fd.set('active_tab', fromTabKey || '');
    fd.set('next_tab', nextTabKey || '');
    fd.set('autosave', '1');          // サーバ側でAJAX保存と判定するフラグ
    fd.set('redirect_to', 'input');   // 継続入力用

    // ---- （input03用）過年度の贈与を FormData に明示的に詰める（1..10行）----
    // ★ 重要：
    //   storePastGifts() は recipient_no が無いと受贈者単位の保存ができない。
    //   そのため、input03 からの autosave では recipient_no を必ず送る。
    const setPastScalarField = (name) => {
      const selector = [
        `#zouyo-tab-input03 input[name="${name}"]`,
        `#zouyo-tab-input03 select[name="${name}"]`,
        `#zouyo-tab-input03 textarea[name="${name}"]`,
      ].join(', ');
      const el = form.querySelector(selector);
      if (!el) return;
      if (el.disabled) return;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      const v = (el.value ?? '');
      if (v !== '') fd.set(name, v);
    };

    const setPastArrayField = (base, i) => {
      const selector = [
        `#zouyo-tab-input03 input[name="${base}[${i}]"]`,
        `#zouyo-tab-input03 select[name="${base}[${i}]"]`,
        `#zouyo-tab-input03 textarea[name="${base}[${i}]"]`,
      ].join(', ');
      const el = form.querySelector(selector);
      if (!el) return;
      if (el.disabled) return;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      const v = (el.value ?? '');
      if (v !== '') fd.set(`${base}[${i}]`, v);
    };

    const fillPastRange = (bases, from, to) => {
      bases.forEach(base => {
        for (let i = from; i <= to; i++) setPastArrayField(base, i);
      });
    };

    if (fromTabKey === 'input03') {
      // 受贈者番号は最優先で明示送信
      const pastRecipientEl = form.querySelector(
        '#zouyo-tab-input03 #past-recipient-no, ' +
        '#zouyo-tab-input03 select[name="recipient_no"], ' +
        '#zouyo-tab-input03 input[name="recipient_no"]'
      );
      const pastRecipientNo = (pastRecipientEl?.value ?? '').trim();
      if (pastRecipientNo !== '') {
        fd.set('recipient_no', pastRecipientNo);
      }

      // 相続開始日がある実装なら一緒に送る
      setPastScalarField('inherit_year');
      setPastScalarField('inherit_month');
      setPastScalarField('inherit_day');

      fillPastRange([
        'rekinen_year','rekinen_month','rekinen_day',
        'rekinen_zoyo','rekinen_kojo',
        'seisan_year','seisan_month','seisan_day',
        'seisan_zoyo','seisan_kojo'
      ], 1, 10);
    }



    // ▼ 「家族構成等（input02）」タブ：ヘッダ＋家族構成テーブルをまとめて送信
    if (fromTabKey === 'input02') {
      const pane = document.querySelector('#zouyo-tab-input02');
      if (pane) {
        // タブ内の input / select / textarea を一括走査
        const nodes = pane.querySelectorAll('input, select, textarea');
        nodes.forEach((el) => {
          const name = el.getAttribute('name');
          if (!name) return;
          // disabled は送らない
          if (el.disabled) return;


          // checkbox / radio は hidden の 0 を checkbox の 1 で上書きできるよう特別扱い
          if (el.type === 'checkbox' || el.type === 'radio') {
            if (!el.checked) return;
            fd.set(name, el.value ?? '1');
            return;
          }

          const val = (el.value ?? '');

          // hidden の checkbox 補助値（0）は先に積んでよいが、
          // 後続の checked checkbox が上書きできるようにする
          if (el.type === 'hidden') {
            if (val === '') return;
            if (!fd.has(name)) {
              fd.set(name, val);
            }
            return;
          }

          // 通常の input/select/textarea
          // 空文字は送らない（既存値の消し込み防止）
          if (val === '') return;

          // 既に同名キーが FormData にあり、かつ非空なら上書きしない
          const existed = fd.getAll(name);
          if (existed.length > 0 && (existed[0] ?? '') !== '') return;

          fd.set(name, val);



        });
      }
    }


    // ▼ 「これからの贈与（input04）」は pane 内から “非空のみ” を追加収集
    if (fromTabKey === 'input04') {
      const pane = document.querySelector('#zouyo-tab-input04');
      if (pane) {

        const nodes = pane.querySelectorAll('input, select, textarea');
        nodes.forEach((el) => {
          const name = el.getAttribute('name');
          if (!name) return;
          // disabled は送らない／checkbox・radio の未チェックは送らない
          if (el.disabled) return;
          if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
          const val = (el.value ?? '');
          // ★ 空は送らない（既存値の消し込み防止）
          if (val === '') return;
          // ★ 既に同名キーが非空で積まれていれば維持（空での上書き防止）
          const existed = fd.getAll(name);
          if (existed.length > 0 && (existed[0] ?? '') !== '') return;
          fd.set(name, val);
        });


        // ---- ① 受贈者番号を必ず future_recipient_no で送る ----
        const rnSel = pane.querySelector('#future-recipient-no');
        const rnVal = rnSel?.value ?? '';
        if (rnVal !== '') fd.set('future_recipient_no', rnVal);

        // ---- ② base日付を future_base_* として保証送信（存在するものだけ）----
        const valOr = (sel) => {
          const el = pane.querySelector(sel);
          return el ? (el.value ?? '') : '';
        };
        const fbY = valOr('[name="future_base_year"], [name="header_year"], #future-base-year');
        const fbM = valOr('[name="future_base_month"], [name="header_month"], #future-base-month');
        const fbD = valOr('[name="future_base_day"], [name="header_day"], #future-base-day');
        if (fbY !== '' && !fd.has('future_base_year'))  fd.set('future_base_year',  fbY);
        if (fbM !== '' && !fd.has('future_base_month')) fd.set('future_base_month', fbM);
        if (fbD !== '' && !fd.has('future_base_day'))   fd.set('future_base_day',   fbD);

        // ---- ③ plan[...] 形式 → トップレベルへフラット化して別名送信（非空のみ）----
        // コントローラが読むキー群
        const KEYS = [
          'gift_year','age',
          'cal_amount','cal_basic','cal_after_basic','cal_tax','cal_cum',
          'set_amount','set_basic110','set_after_basic','set_after_25m','set_tax20','set_cum',
          'gift_month','gift_day',
        ];
        // plan[key][i] または key[i] を探して、最終的に key[i] を必ず積む
        for (let i = 1; i <= 20; i++) {
          for (const k of KEYS) {
            // 20行対象だが、過去10行系の key は見つからなければ無視でOK
            const qPlan = pane.querySelector(`[name="plan[${k}][${i}]"]`);
            const qFlat = pane.querySelector(`[name="${k}[${i}]"]`);
            
            const v = (qFlat?.value ?? qPlan?.value ?? '');
            if (v !== '') {
              // 既に同名が FormData に在っても、空か未定義なら上書き
              const formKey = `${k}[${i}]`;
              const has = fd.getAll(formKey).length > 0;
              if (!has || (has && (fd.get(formKey) ?? '') === '')) {
                fd.set(formKey, v);
              }
            }
          }
        }
        // 20行より短いキー（過去10行）についても安全側で 1..10 を再走査
        for (let i = 1; i <= 10; i++) {
          for (const k of ['gift_month','gift_day']) {
            const qPlan = pane.querySelector(`[name="plan[${k}][${i}]"]`);
            const qFlat = pane.querySelector(`[name="${k}[${i}]"]`);
            const v = (qFlat?.value ?? qPlan?.value ?? '');
            if (v !== '') {
              const formKey = `${k}[${i}]`;
              const has = fd.getAll(formKey).length > 0;
              if (!has || (has && (fd.get(formKey) ?? '') === '')) {
                fd.set(formKey, v);
              }
            }
          }
        }
      
        
      }
    }



    // ▼ 「遺産分割等（input05）」タブの入力も “非空のみ” を収集して保存
    if (fromTabKey === 'input05') {
      const pane = document.querySelector('#zouyo-tab-input05');
      if (pane) {
        // 1) pane 内の input/select/textarea を走査（空・未チェック・disabled は送らない）
        const nodes = pane.querySelectorAll('input, select, textarea');
        nodes.forEach((el) => {
          const name = el.getAttribute('name');
          if (!name) return;
          if (el.disabled) return;
          if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
          const val = (el.value ?? '');
          if (val === '') return;              // 空値は送らない（既存値の消し込み防止）
          const existed = fd.getAll(name);
          if (existed.length > 0 && (existed[0] ?? '') !== '') return; // 既に非空を積んでいれば保持
          fd.set(name, val);
        });

        // 2) “法定/手入力” の入力モード（name="input_mode"）は上記で入るが、念のため強制保証
        const modeChecked = pane.querySelector('input[name="input_mode"]:checked');
        if (modeChecked && !fd.has('input_mode')) {
          fd.set('input_mode', modeChecked.value);
        }

        // 3) よく使う配列系（存在するものだけ）を安全に積む
        //    - 課税価格（手入力）：id_taxable_manu[2..10]
        //    - その他の税額控除：  id_other_credit[2..10]
        //    - 法定相続分の分数：  bunsi[2..10], bunbo[2..10]
        //    - 人数：               houtei_ninzu
        const setIfPresent = (key, idx) => {
          const el = pane.querySelector(`[name="${key}[${idx}]"]`);
          if (!el) return;
          const v = (el.value ?? '');
          if (v === '') return;
          const formKey = `${key}[${idx}]`;
          const has = fd.getAll(formKey).length > 0;
          if (!has || (has && (fd.get(formKey) ?? '') === '')) fd.set(formKey, v);
        };
        for (let i = 2; i <= 10; i++) {
          ['id_taxable_manu','id_other_credit','bunsi','bunbo'].forEach(k => setIfPresent(k, i));
        }
        const ninzuEl = pane.querySelector('[name="houtei_ninzu"]');
        if (ninzuEl && ninzuEl.value !== '' && !fd.has('houtei_ninzu')) {
          fd.set('houtei_ninzu', ninzuEl.value);
        }

        // 4) （任意）年齢自動計算関連や明細合計がある環境でも、存在すれば非空のみ積む
        //    例：rekinen_zoyo[1..10], rekinen_kojo[1..10], seisan_zoyo[1..10], seisan_kojo[1..10]
        const OPTIONAL_KEYS = ['rekinen_zoyo','rekinen_kojo','seisan_zoyo','seisan_kojo'];
        for (const k of OPTIONAL_KEYS) {
          for (let i = 1; i <= 10; i++) setIfPresent(k, i);
          // 合計行(110) があれば送る（存在する場合のみ）
        setIfPresent(k, 110);
        }
        // 誕生日と年齢（存在時のみ・非空のみ）
        for (let i = 1; i <= 10; i++) {
          ['birth_year','birth_month','birth_day','age'].forEach(k => setIfPresent(k, i));
        }
      }
    }



    // ▼ 拡張ポイント：future_zouyo 等で動的生成した要素を追加収集したい場合に利用
    //    例) window.z_beforeAutosave = (fromTabKey, fd) => { fd.set('foo','bar'); ... }
    try { if (typeof window.z_beforeAutosave === 'function') window.z_beforeAutosave(fromTabKey, fd); } catch (_) {}



    try {
      // 現在タブに対応する保存URLへPOST（未定義時はフォームの既定action）
      const postUrl = SAVE_URLS[fromTabKey] || form.action;

      // デバッグ容易化のため action も同期（任意）
      try { form.action = postUrl; } catch (_) {}
      // 送信先とタブ情報を簡易ログ（必要に応じて削除可）
      console.debug('[autosave] from:', fromTabKey, 'next:', nextTabKey, 'POST:', postUrl);
      const res = await fetch(postUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
          // Cookie が未設定の環境での split 例外を回避
          'X-XSRF-TOKEN': (() => {
            try {
              const raw = (document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN=')) || '').split('=')[1] || '';
              return decodeURIComponent(raw);
            } catch { return ''; }
          })(),
        },
        body: fd,
        credentials: 'include',  // ← これ必須
      });

      if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(`autosave failed: ${res.status} ${res.statusText}\n${text.slice(0,200)}`);
      }

      const json = await res.json().catch(() => ({}));

      // ▼ 表示：future タブ保存なら #future-save-status を更新（色=既存の .save-pill クラス利用）
      try {
        if (fromTabKey === 'input04') {
          const pill = document.getElementById('future-save-status');
          if (pill) {
            pill.classList.remove('is-error','is-idle','is-saving');
            pill.classList.add('is-success');
            const rows = json?.saved?.plan_rows ?? null;
            const header = json?.saved?.header ? ' / header' : '';
            const rcpt   = json?.saved?.recipient ? ' / recipient' : '';
            pill.textContent = rows !== null
              ? `保存OK (${rows}行${header}${rcpt})`
              : '保存OK';
            // 数秒後に淡色へ戻すなら以下
            setTimeout(() => {
              pill.classList.remove('is-success');
              pill.classList.add('is-idle');
            }, 2200);
          }
        }
      } catch (_) {}

      return json;
      
      
      
    } catch (e) {
      console.error(e);
      alert('自動保存に失敗しました。\n' + e);

      // ▼ 表示：失敗時
      try {
        const pill = document.getElementById('future-save-status');
        if (pill) {
          pill.classList.remove('is-success','is-idle','is-saving');
          pill.classList.add('is-error');
          pill.textContent = '保存失敗';
        }
      } catch(_) {}

      // ★重要: 呼び出し元で遷移/送信を止められるよう必ず投げ直す
      throw e;
    }      

  };

  // ★ 修正：クリックではなく「離脱するタブ」側のイベントで保存する
  // Bootstrap 5 の 'hide.bs.tab' は「現在のタブボタン」に発火し、relatedTarget に遷移先が入る
  nav.addEventListener('hide.bs.tab', async (ev) => {
    const fromBtn = ev.target;               // 今閉じようとしているタブのボタン
    const toBtn   = ev.relatedTarget;        // 次に表示するタブのボタン
    const fromSel = fromBtn?.getAttribute('data-bs-target') || '';
    const toSel   = toBtn?.getAttribute('data-bs-target')   || '';
    const fromKey = getNextTabKey(fromSel);
    const nextKey = getNextTabKey(toSel);




    // ▼ autosave 対象外タブは、そのまま通常切替させる
    if (!isAutosaveTargetTab(fromKey)) {
      return;
    }




    // 連打/多重防止（from側のボタンにフラグ）
    if (fromBtn && fromBtn.dataset.saving === '1') return;
    if (fromBtn) fromBtn.dataset.saving = '1';

    // ここで一旦切替を止めて保存→成功後に show()
    ev.preventDefault();
    try {
      await autosave(fromKey, nextKey);
      if (toBtn) {
        const tab = bootstrap.Tab.getOrCreateInstance(toBtn);
        tab.show();
        
        // autosave 経由で input05 を開く場合は、shown.bs.tab だけに依存せず明示的に再計算を予約する
        if (nextKey === 'input05') {
          z_queueIsanPreviewAfterTabOpen();
        }        
        
      }
    } catch (e) {
      console.error('[autosave on hide.bs.tab] failed:', e);
      alert('自動保存に失敗しました。ネットワークをご確認の上、再度お試しください。' + e);
    } finally {
      if (fromBtn) fromBtn.dataset.saving = '0';
    }
  });


  // ▼ 追加：「計算開始」クリック時に“現在開いているタブの内容を保存”→成功後にcalc実行
  const calcBtn = document.getElementById('zouyo-calc-btn');
  if (calcBtn) {
    calcBtn.addEventListener('click', async (ev) => {
      ev.preventDefault();
      try {


        z_showCalculatingOverlay();


        const fromKey = getCurrentTabKey();   // 例: input02 / input03 / input04 / input05

        // ▼ 入力保存対象タブにいる場合のみ autosave
        if (isAutosaveTargetTab(fromKey)) {
          // 次タブは計算フローなのでログ目的のダミー名 'calc' を渡す
          await autosave(fromKey, 'calc');
        }

      } catch (e) {
        console.error('[calc-before-save] autosave failed:', e);

        z_hideCalculatingOverlay();
        z_clearCalcInProgress();


        alert('自動保存に失敗しました。ネットワーク等をご確認ください。\n' + e);
        return; // 保存失敗時はcalcを実行しない
      }
      // 保存成功 → calc へ POST 提出
      const form = document.getElementById('zouyo-input-form');
      if (!form) return;
      // data_id を最終確認（空なら補完）
      const hid = form.querySelector('input[name="data_id"]');
      if (hid && (!hid.value || hid.value.trim() === '')) {
        const d = (typeof z_resolveDataId === 'function') ? z_resolveDataId() : '';
        if (d) { hid.value = d; form.dataset.dataId = d; }
      }
      // ボタンの formaction/formmethod を尊重して送信
      const targetAction  = calcBtn.getAttribute('formaction')  || form.action;
      const targetMethod  = calcBtn.getAttribute('formmethod')  || form.getAttribute('method') || 'POST';
      try { form.action = targetAction; } catch (_) {}
      try { form.method = targetMethod; } catch (_) {}
      

      // 計算後の戻り画面では PDF指定 タブを自動で開く
      try { sessionStorage.setItem('zouyo_open_pdf_tab_after_calc', '1'); } catch (_) {}


      z_markCalcInProgress();
      
      form.submit();
    });
  }



  // ▼ 追加：「戻る」クリック時に“現在開いているタブの内容を保存”→成功後にだけ戻る
  const backBtn = document.getElementById('zouyo-back-btn');
  if (backBtn) {
    backBtn.addEventListener('click', async (ev) => {
      ev.preventDefault();

      // 連打防止
      if (backBtn.dataset.saving === '1') return;
      backBtn.dataset.saving = '1';

      try {
        const fromKey = getCurrentTabKey();   // 例: input02 / input03 / input04 / input05

        // ▼ 入力保存対象タブにいる場合のみ autosave
        if (isAutosaveTargetTab(fromKey)) {
          // 遷移先は一覧へ戻るのでログ目的で 'back' を渡す
          await autosave(fromKey, 'back');
        }

        // 保存成功後にのみ遷移
        window.location.href = backBtn.getAttribute('href');
      } catch (e) {
        console.error('[back-before-save] autosave failed:', e);
        alert('保存に失敗したため、戻る処理を中止しました。\n' + e);
      } finally {
        backBtn.dataset.saving = '0';
      }
    });
  }


  // ▼ 追加：「マスター」クリック時に“現在開いているタブの内容を保存”→成功後にだけ遷移
  const masterBtn = document.getElementById('zouyo-master-btn');
  if (masterBtn) {
    masterBtn.addEventListener('click', async (ev) => {
      ev.preventDefault();

      // 連打防止
      if (masterBtn.dataset.saving === '1') return;
      masterBtn.dataset.saving = '1';

      try {
        const fromKey = getCurrentTabKey();   // 例: input02 / input03 / input04 / input05

        // ▼ 入力保存対象タブにいる場合のみ autosave
        if (isAutosaveTargetTab(fromKey)) {
          // 遷移先はマスター画面なのでログ目的で 'master' を渡す
          await autosave(fromKey, 'master');
        }

        // 保存成功後にのみ遷移
        window.location.href = masterBtn.getAttribute('href');
      } catch (e) {
        console.error('[master-before-save] autosave failed:', e);
        alert('保存に失敗したため、マスター画面への遷移を中止しました。\n' + e);
      } finally {
        masterBtn.dataset.saving = '0';
      }
    });
  }



});  // ← ここで閉じる















</script>

<script>
  /**
   * input04 のフォーム要素から 1..20 行分を FormData に詰める
   * Controller 側の storeFutureGifts は name="xxx[i]" 形式を受けるため、
   * フロントでも同じキー名で fd.set() する。
   */
  function z_collectFutureToFormData(fd) {
    const pane = document.querySelector('#zouyo-tab-input04');
    if (!pane) return;


    // ▼ デバッグトグル（必要時のみ true に）
    window.Z_FUTURE_DEBUG = window.Z_FUTURE_DEBUG ?? false;


    // 受贈者番号と基準日
    const rn = pane.querySelector('#future-recipient-no')?.value ?? '';
    if (rn !== '') fd.set('future_recipient_no', rn);
    const y = pane.querySelector('input[name="future_base_year"]')?.value ?? '';
    const m = pane.querySelector('input[name="future_base_month"]')?.value ?? '';
    const d = pane.querySelector('input[name="future_base_day"]')?.value ?? '';
    if (y !== '') fd.set('future_base_year', y);
    if (m !== '') fd.set('future_base_month', m);
    if (d !== '') fd.set('future_base_day', d);

    // 行キー一覧（暦年/精算/贈与日）
    const KEYS = [
      'cal_amount','cal_basic','cal_after_basic','cal_tax','cal_cum',
      'set_amount','set_basic110','set_after_basic','set_after_25m','set_tax20','set_cum',
      'gift_month','gift_day','gift_year'
    ];


    // ▼ デバッグ用：収集前に 1..20 をスキャンして見える化する
    const debugPreview = {};
    if (window.Z_FUTURE_DEBUG) {
      for (const k of KEYS) {
        const row = {};
        for (let i = 1; i <= 20; i++) {
          const el = pane.querySelector(`input[name="${k}[${i}]"]`);
          const v  = el?.value ?? '';
          if (v !== '') row[i] = v; // 入力がある行だけ載せる
        }
        debugPreview[k] = row;
      }
      // 受贈者・基準日も併せて表示
      console.groupCollapsed('%c[input04] 送信前プレビュー','color:#2f54eb');
      console.log('recipient_no:', rn, 'base:', {year:y, month:m, day:d});
      // KEYSごとに table 表示（行⇔値）
      for (const k of KEYS) {
        const rows = debugPreview[k];
        const table = [];
        for (let i=1;i<=20;i++){
          table.push({row:i, value: rows[i] ?? ''});
        }
        console.log(`%c${k}`,'color:#52c41a');
        console.table(table);
      }
      console.groupEnd();
    }



    // 安全に値を取得し、存在する時のみ set
    const setIfPresent = (name, idx) => {
      const sel = pane.querySelector(`input[name="${name}[${idx}]"]`);
      if (!sel) return;
      const v = sel.value ?? '';
      if (v !== '') fd.set(`${name}[${idx}]`, v);
    };

    // 1..20 行分
    for (let i = 1; i <= 20; i++) {
      for (const k of KEYS) {
        setIfPresent(k, i);
      }
    }

    // 必要なら「過年度合計の0行」「合計行(110)」も収集
    // （存在しない環境でも問題ないようにガード付）
    const optionalRows = [0, 110];
    for (const idx of optionalRows) {
      for (const k of ['cal_amount','cal_after_basic','cal_tax','cal_cum','set_amount','set_tax20']) {
        setIfPresent(k, idx);
      }
    }
  }

</script>


 <script>
let zIsanPreviewRequestSeq = 0;

function z_findIsanPane() {
  return document.getElementById('zouyo-tab-input05');
}

function z_setIsanInputValue(pane, selector, value) {
  const el = pane.querySelector(selector);
  if (!el) return;
  el.value = value ?? '';
}

function z_setIsanTextValue(pane, selector, value) {
  const el = pane.querySelector(selector);
  if (!el) return;
  el.textContent = value ?? '';
}

function z_applyIsanPreview(preview) {
  const pane = z_findIsanPane();
  if (!pane || !preview) return;

  const left = preview.left || {};
  const members = preview.members || {};
  const previewMode = String(preview.mode || '').toLowerCase();  

  z_setIsanInputValue(pane, '#isan-customer-name', left.customer_name ?? '');
  z_setIsanInputValue(pane, '#id_cash_total', left.cash_total ?? '');
  z_setIsanInputValue(pane, '#id_other_total', left.other_total ?? '');
  z_setIsanInputValue(pane, '#id_taxable_manu_total', left.property_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-lifetime-gift', left.lifetime_gift_total ?? '');
  z_setIsanInputValue(pane, 'input[data-role="taxable_total_overall"]', left.taxable_total_overall ?? '');
  z_setIsanTextValue(pane, '#basic-deduction-label', left.basic_deduction_label ?? '');
  z_setIsanInputValue(pane, '#basic_deduction_amount', left.basic_deduction_amount ?? '');
  z_setIsanInputValue(pane, '#isan-total-taxable-estate', left.taxable_estate ?? '');
  z_setIsanInputValue(pane, '#isan-total-anbun-ratio', left.anbun_ratio_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-sozoku-tax', left.sozoku_tax_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-sanzutsu', left.sanzutsu_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-two-tenths', left.two_tenths_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-calendar-credit', left.gift_tax_credit_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-spouse-relief', left.spouse_relief_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-other-credit', left.other_credit_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-credit', left.credit_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-sashihiki', left.sashihiki_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-settlement-credit', left.settlement_credit_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-subtotal', left.subtotal_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-payable', left.payable_total ?? '');
  z_setIsanInputValue(pane, '#isan-total-refund', left.refund_total ?? '');

  Object.entries(members).forEach(([no, row]) => {
    z_setIsanInputValue(pane, `input[name="id_cash_share[${no}]"]`, row.cash_share ?? '');
    z_setIsanInputValue(pane, `input[name="id_other_share[${no}]"]`, row.other_share ?? '');
    z_setIsanInputValue(pane, `input[name="id_taxable_manu[${no}]"]`, row.taxable_manu ?? '');
    z_setIsanInputValue(pane, `input[name="id_other_credit[${no}]"]`, row.other_credit ?? '');
    z_setIsanInputValue(pane, `input[name="id_lifetime_gift_addition[${no}]"]`, row.lifetime_gift_addition ?? '');
    z_setIsanInputValue(pane, `input[data-role="taxable_total_heir"][data-heir-no="${no}"]`, row.taxable_total ?? '');
    z_setIsanInputValue(pane, `input[data-role="taxable_estate_share"][data-heir-no="${no}"]`, row.taxable_estate_share ?? '');
    z_setIsanInputValue(pane, `input[data-role="legal_share_text"][data-heir-no="${no}"]`, row.legal_share_text ?? '');
    z_setIsanInputValue(pane, `input[data-role="legal_tax"][data-heir-no="${no}"]`, row.legal_tax ?? '');
    z_setIsanInputValue(pane, `input[data-role="anbun_ratio"][data-heir-no="${no}"]`, row.anbun_ratio ?? '');
    z_setIsanInputValue(pane, `input[data-role="sanzutsu_tax"][data-heir-no="${no}"]`, row.sanzutsu_tax ?? '');
    z_setIsanInputValue(pane, `input[data-role="two_tenths_amount"][data-heir-no="${no}"]`, row.two_tenths_amount ?? '');
    z_setIsanInputValue(pane, `input[data-role="gift_tax_credit_calendar"][data-heir-no="${no}"]`, row.gift_tax_credit_calendar ?? '');
    z_setIsanInputValue(pane, `input[data-role="spouse_relief"][data-heir-no="${no}"]`, row.spouse_relief ?? '');
    z_setIsanInputValue(pane, `input[data-role="credit_total"][data-heir-no="${no}"]`, row.credit_total ?? '');
    z_setIsanInputValue(pane, `input[data-role="sashihiki_tax"][data-heir-no="${no}"]`, row.sashihiki_tax ?? '');
    z_setIsanInputValue(pane, `input[data-role="settlement_gift_tax"][data-heir-no="${no}"]`, row.settlement_gift_tax ?? '');
    z_setIsanInputValue(pane, `input[data-role="raw_subtotal"][data-heir-no="${no}"]`, row.raw_subtotal ?? '');
    z_setIsanInputValue(pane, `input[data-role="payable_tax"][data-heir-no="${no}"]`, row.payable_tax ?? '');
    z_setIsanInputValue(pane, `input[data-role="refund_tax"][data-heir-no="${no}"]`, row.refund_tax ?? '');
  });

  try {
    // ★ auto preview で手入力ストアを上書きしない
    if (previewMode === 'manual' && typeof window.z_syncIsanManualStores === 'function') {
      window.z_syncIsanManualStores();
    }
  } catch (_) {}


  try {
    if (typeof window.syncIsanFamilyHeaders === 'function') {
      window.syncIsanFamilyHeaders();
    }
  } catch (_) {}

  try {
    if (typeof updateLifetimeGiftAddition === 'function') {
      updateLifetimeGiftAddition();
    }
  } catch (_) {}

  try {
    if (typeof updateTaxablePriceTotal === 'function') {
      updateTaxablePriceTotal();
    }
  } catch (_) {}

  try {
    if (typeof syncIsanTableHeights === 'function') {
      requestAnimationFrame(() => syncIsanTableHeights());
    }
  } catch (_) {}
}

function z_queueIsanPreviewAfterTabOpen() {
  const pane = z_findIsanPane();
  if (!pane) return;

  const run = (retry = 0) => {
    const activeBtn = document.querySelector('#zouyo-main-tabs .nav-link.active[data-bs-target="#zouyo-tab-input05"]');
    const isVisible =
      !!activeBtn &&
      !pane.hidden &&
      pane.style.display !== 'none';

    if (!isVisible && retry < 10) {
      window.setTimeout(() => run(retry + 1), 30);
      return;
    }

    z_requestIsanBeforePreview();
  };

  window.requestAnimationFrame(() => run(0));
}


async function z_requestIsanBeforePreview() {

  const pane = z_findIsanPane();
  const form = document.getElementById('zouyo-input-form');
  if (!pane || !form) return;

  const token =
    form.querySelector('input[name="_token"]')?.value ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
    '';

  const fd =
    typeof window.buildIsanPreviewFormDataFromCurrentForm === 'function'
      ? window.buildIsanPreviewFormDataFromCurrentForm()
      : new FormData(form);

  const dataId =
    fd.get('data_id') ||
    form.querySelector('input[name="data_id"]')?.value ||
    (typeof z_resolveDataId === 'function' ? z_resolveDataId() : '') ||
    (typeof window.APP_DATA_ID !== 'undefined' ? window.APP_DATA_ID : '') ||
    '';

  if (!dataId) return;

  fd.set('data_id', String(dataId));
  fd.set('active_tab', 'input05');

  const seq = ++zIsanPreviewRequestSeq;
  pane.dataset.previewLoading = '1';

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
    if (seq !== zIsanPreviewRequestSeq) return;

    if ((json?.status || '') !== 'ok') {
      throw new Error(json?.message || 'inheritance preview failed');
    }

    if (typeof z_applyIsanPreview === 'function') {
      z_applyIsanPreview(json.preview || {});
    }
  } catch (err) {
    console.error('[isan-preview] failed', err);
  } finally {
    if (seq === zIsanPreviewRequestSeq) {
      delete pane.dataset.previewLoading;
    }
  }

}


document.addEventListener('DOMContentLoaded', function () {
  const activeBtn = document.querySelector('#zouyo-main-tabs .nav-link.active[data-bs-target="#zouyo-tab-input05"]');
  if (!activeBtn) return;

  try {

    z_requestIsanBeforePreview();

  } catch (err) {
    console.error('[initial-input05-preview] failed', err);
  }

  
  
  
});



</script>



@endsection
