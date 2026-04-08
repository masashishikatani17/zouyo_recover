@extends('layouts.app')

@section('content')
<div class="container">
    <h2>贈与プランナー フォーム</h2>

  <!-- Bootstrap Nav Tabs -->
  <ul class="nav nav-tabs" id="sheetTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="sheet013-tab" data-bs-toggle="tab" data-bs-target="#sheet013" type="button" role="tab">表題（sheet013）</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="sheet014-tab" data-bs-toggle="tab" data-bs-target="#sheet014" type="button" role="tab">過年度贈与（sheet014）</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="sheet015-tab" data-bs-toggle="tab" data-bs-target="#sheet015" type="button" role="tab">贈与計画（sheet015）</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="sheet016-tab" data-bs-toggle="tab" data-bs-target="#sheet016" type="button" role="tab">遺産分割（sheet016）</button>
    </li>
  </ul>

  <!-- Tab Contents -->
  <div class="tab-content mt-3" id="sheetTabsContent">

    {{-- sheet013 --}}
    <div class="tab-pane fade show active" id="sheet013" role="tabpanel">
      @include('sheets.sheet013')
    </div>

    {{-- sheet014 --}}
    <div class="tab-pane fade" id="sheet014" role="tabpanel">
      @include('sheets.sheet014')
    </div>

    {{-- sheet015 --}}
    <div class="tab-pane fade" id="sheet015" role="tabpanel">
      @include('sheets.sheet015')
    </div>

    {{-- sheet016 --}}
    <div class="tab-pane fade" id="sheet016" role="tabpanel">
      @include('sheets.sheet016')
    </div>

  </div>
</div>
</div>
@endsection

