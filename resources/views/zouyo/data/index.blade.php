@extends('layouts.min')

@section('content')
<style>

  :root {
    --zouyo-right-content-width: 548px;
    --zouyo-scrollbar-width: 16px;
  }


  .zouyo-master-wrap {
    max-width: 920px;
    width: 100%;
    margin: 0 auto;
  }

  .zouyo-master-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
  }

  .zouyo-master-title {
    display: flex;
    align-items: flex-start;
  }

  .zouyo-master-title h0 {
    margin-left: 12px;
    margin-top: 8px;
  }

  .zouyo-master-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-right: 20px;
    margin-top: 8px;
  }

  .zouyo-master-pane-wrap {
    display: flex;
    gap: 14px;
    flex-wrap: nowrap;
    justify-content: center;
    align-items: stretch;    
    overflow-x: hidden;
    min-height: 480px;
  }

  .zouyo-left-pane {
    width: 300px;
    flex: 0 0 300px;
    display: flex;
    flex-direction: column;    
  }

  .zouyo-right-pane {
    width: calc(var(--zouyo-right-content-width) + var(--zouyo-scrollbar-width));
    flex: 0 0 calc(var(--zouyo-right-content-width) + var(--zouyo-scrollbar-width));
    display: flex;
    flex-direction: column;    
  }

  .zouyo-right-head {
    display: flex;
    flex-wrap: nowrap;
    gap: 14px;
    width: var(--zouyo-right-content-width);
  }

  .zouyo-year-col {
    width: 78px;
    flex: 0 0 78px;
  }

  .zouyo-data-col {
    width: 456px;
    flex: 0 0 456px;  
  }

  .zouyo-right-body {
    display: flex;
    flex-wrap: nowrap;
    gap: 14px;
    width: calc(var(--zouyo-right-content-width) + var(--zouyo-scrollbar-width));
    height: 420px;
    overflow-y: auto;
    overflow-x: hidden;
    margin-top: 0px;
    padding-top: 0;
  }


  /* ▼ ヘッダ表と本体表の境界を詰める */
  .zouyo-left-body table,
  .zouyo-right-body table {
    margin-top: 0;
  }


  .zouyo-left-body {
    height: 420px;
    overflow-y: auto;
    overflow-x: hidden;    
    margin-top: -1px;    
  }


  .zouyo-guest-row.is-active td,
  .zouyo-data-row.is-active td {
    background: #cfe2ff !important;
  }

  .zouyo-data-name {
    display: inline-block;
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
  }


  .zouyo-date-cell {
    white-space: nowrap;
    font-size: 11px;
    padding-left: 4px !important;
    padding-right: 4px !important;
  }

  .zouyo-action-cell {
    padding-left: 2px !important;
    padding-right: 2px !important;
  }

  .zouyo-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;    
    gap: 12px;
    margin-top: 10px;
  }

  .zouyo-footer-left {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }


  .zouyo-right-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 0;    
  }



  /* ▼ 下部ボタン帯の高さを統一 */
  .zouyo-footer-btn {
    height: 32px;
    min-height: 32px;
    line-height: 1;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    box-sizing: border-box;
  }

  .btn-disabled-link {
    pointer-events: none;
    opacity: .55;
  }
  

  /* ▼ 左右ペインの各データ行の高さを統一 */
  .zouyo-row-height {
    height: 32px;
  }

  .zouyo-row-height > td {
    height: 32px !important;
    min-height: 32px;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    vertical-align: middle !important;
    line-height: 1.2;
  }

  /* ▼ 年度列・データ列のテーブル幅計算を安定化 */
  .zouyo-year-col table,
  .zouyo-data-col table {
    table-layout: fixed;
  }

  /* ▼ リンク自体も行高いっぱいに広げて高さブレを防ぐ */
  .zouyo-cell-link {
    display: flex;
    align-items: center;
    width: 100%;
    height: 32px;
    color: inherit;
    text-decoration: none;
  }

  .zouyo-cell-link.is-center {
    justify-content: center;
  }


  .zouyo-year-cell {
    padding-left: 2px !important;
    padding-right: 2px !important;
  }


  /* ▼ 開くボタンも高さを揃える */
  .zouyo-open-btn {
    height: 24px;
    line-height: 1;
    padding-top: 0;
    padding-bottom: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }  


  /* ▼ disabledボタンはグレー表示 */
  .zouyo-footer-btn:disabled {
    background: #9aa0a6 !important;
    border-color: #9aa0a6 !important;
    color: #ffffff !important;
    cursor: not-allowed;
    opacity: 1 !important;
  }

</style>




<div class="container-blue zouyo-master-wrap">
  
  @php
    $hasDatas = isset($datas) && $datas->count() > 0;
  @endphp


  <div class="card-header zouyo-master-head">
    <div class="zouyo-master-title">
      <img src="{{ asset('storage/images/kado_lefttop.jpg') }}" alt="…">
      <h0>最適贈与プランナー　お客様・データ名一覧</h0>
    </div>

    <div class="zouyo-master-toolbar">
      <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="btn btn-base-blue">ログアウト</button>
      </form>
    </div>
  </div>

  <div class="border-0 rounded p-3">
    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="zouyo-master-pane-wrap">
      <div class="zouyo-left-pane">
        <table class="table table-base mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-center" style="width:300px; height:25px;">お客様名</th>
            </tr>
          </thead>
        </table>

        <div class="mt-0 zouyo-left-body">            
          <table class="table table-compact-p mb-0 align-middle" style="width:300px;">            
            <tbody>
              @forelse ($guests as $guest)
                <tr class="zouyo-guest-row zouyo-row-height {{ (int)$selectedGuestId === (int)$guest->id ? 'is-active' : '' }}">
                  <td class="text-start py-1 px-2" style="width:300px;">
                    <a href="{{ route('zouyo.data.index', ['guest_id' => $guest->id]) }}"
                       class="zouyo-cell-link">
                      {{ $guest->name ?: '—' }}
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td class="text-muted py-2 px-2" style="width:300px;">（お客様がありません）</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="zouyo-right-pane">
        <div class="zouyo-right-head">
          <div class="zouyo-year-col">
            <table class="table table-base mb-0 align-middle" style="width:78px;">
              <thead class="table-light">
                <tr style="height:25px;">
                  <th class="text-center zouyo-year-cell" style="width:78px;">年度</th>
                </tr>
              </thead>
            </table>
          </div>

          <div class="zouyo-data-col">
            <table class="table table-base mb-0 align-middle" style="width:456px;">                
              <thead class="table-light">
                <tr style="height:25px;">
                  <th class="text-center" style="width:255px;">データ名</th>
                  <th class="text-center" style="width:84px;">更新日時</th>
                  <th class="text-center" style="width:116px;">操作</th>                  
                </tr>
              </thead>
            </table>
          </div>
        </div>

        <div class="zouyo-right-body">
          <div class="zouyo-year-col">
            <table class="table table-compact-p mb-0 align-middle" style="width:78px;">
              <tbody>
                @forelse ($datas as $data)
                  <tr class="zouyo-data-row zouyo-row-height {{ (int)$selectedDataId === (int)$data->id ? 'is-active' : '' }}">
                    <td class="text-center" style="width:78px; padding-left:4px; padding-right:4px;">
                      <a href="{{ route('zouyo.data.index', ['guest_id' => $selectedGuestId, 'data_id' => $data->id]) }}"
                         class="zouyo-cell-link is-center">
                         {{ $data->kihu_year ?? '—' }}
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td class="text-muted py-2 px-2 text-center">（データがありません）</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="zouyo-data-col">
            <table class="table table-compact-p mb-0 align-middle" style="width:456px;">                
              <tbody>
                @forelse ($datas as $data)
                  <tr class="zouyo-data-row zouyo-row-height {{ (int)$selectedDataId === (int)$data->id ? 'is-active' : '' }}">
                    <td class="text-start" style="width:255px;">
                      <a href="{{ route('zouyo.data.index', ['guest_id' => $selectedGuestId, 'data_id' => $data->id]) }}"
                         class="zouyo-cell-link">                          
                        <span class="zouyo-data-name">{{ $data->data_name ?? '—' }}</span>
                      </a>
                    </td>
                    <td class="text-center zouyo-date-cell" style="width:84px;">                        
                      {{ optional($data->updated_at)->format('Y-m-d') ?? '—' }}
                    </td>

                    <td class="text-center bg-cream zouyo-action-cell" style="width:116px;" nowrap="nowrap">                        

                      <div class="d-inline-flex align-items-center justify-content-center gap-1">

                        <button type="button"
                                class="btn-base-blue zouyo-open-btn"
                                onclick="window.location.href='{{ route('zouyo.input', ['data_id' => $data->id]) }}'">                          
                          選 択
                        </button>

                        <button type="button"
                                class="btn-base-blue zouyo-open-btn"
                                onclick="window.location.href='{{ route('zouyo.data.edit', ['data_id' => $data->id]) }}'">
                          編 集
                        </button>

                      </div>

                    </td>
                  </tr>
                @empty
                  <tr>
                    <td class="text-muted py-2 px-2" colspan="3">（データがありません）</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <hr class="mb-2">

    <div class="zouyo-footer">
      <div class="zouyo-footer-left">
        <a href="{{ route('zouyo.data.create', ['guest_id' => $selectedGuestId]) }}"
           class="btn-base-blue zouyo-footer-btn">
          新規データの作成
        </a>

        <a href="{{ ($hasDatas && $selectedDataId) ? route('zouyo.data.copy.form', ['selected_data_id' => $selectedDataId]) : '#' }}"
           class="btn-base-blue zouyo-footer-btn {{ ($hasDatas && $selectedDataId) ? '' : 'btn-disabled-link' }}"
           title="{{ ($hasDatas && $selectedDataId) ? '' : 'コピーするデータがありません。' }}">
          既存データのコピー
        </a>
      </div>



        <div class="zouyo-right-actions">

          <form method="POST"
                action="{{ route('zouyo.data.destroyGuest') }}"
                onsubmit="return confirm('選択中のお客様に紐づくデータをすべて削除します。削除後は復元できません。');"
                class="m-0">
            @csrf
            <input type="hidden" name="guest_id" value="{{ $selectedGuestId }}">
            <button type="submit"
                    class="btn-base-red zouyo-footer-btn"                    
                    {{ $selectedGuestId ? '' : 'disabled' }}
                    title="{{ $selectedGuestId ? '' : '削除するお客様を選択してください。' }}">
              お客様データの削除
            </button>
          </form>

          <form method="POST"
                action="{{ route('zouyo.data.destroy') }}"
                onsubmit="return confirm('選択中のデータ名を削除します。削除後は復元できません。');"
                class="m-0">
            @csrf
            <input type="hidden" name="data_id" value="{{ $selectedDataId }}">
            <button type="submit"
                    class="btn-base-red zouyo-footer-btn"                    
                    {{ ($hasDatas && $selectedDataId) ? '' : 'disabled' }}
                    title="{{ ($hasDatas && $selectedDataId) ? '' : '削除するデータがありません。' }}">
              データ名の削除
            </button>
          </form>

        </div>





      <div>
        <a href="/" class="btn-base-blue zouyo-footer-btn">戻 る</a>        
      </div>
    </div>
  </div>
</div>
@endsection

