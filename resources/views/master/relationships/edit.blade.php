@extends('layouts.min')

@section('content')

@php
  $resolvedDataId = old('data_id', $dataId ?? request('data_id'));
@endphp


<style>
  .relationship-master-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
  }

  .relationship-master-actions-top {
    margin-bottom: 8px;
  }

  .relationship-master-actions-bottom {
    margin-top: 8px;
  }

  .relationship-master-btn {
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



  /* title.blade の入力可能欄と同じ見た目 */
  .relationship-master-input {
    width: 100%;
    box-sizing: border-box;
    font-size: 12px;
    height: 20px;
    padding: 0 4px;
    line-height: 1.2;
  }

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


</style>


<div class="container" style="max-width: 200px;">
  <div class="d-flex justify-content-between align-items-center mb-3">

    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    
    <h5 class="mb-0">続柄マスター</h5>
  </div>

  @if (session('status'))
    <div class="alert alert-success py-2">
      {{ session('status') }}
    </div>
  @endif

  <div class="alert alert-secondary py-2">
    No0〜41は固定です。No42〜45は名称変更のみ可能です。No46〜50は空欄のまま保存すると未使用扱いとなり、プルダウンには表示されません。
  </div>

  <form id="relationship-master-form" method="post" action="{{ route('master.relationships.update') }}">
     @csrf
     
    <input type="hidden" name="data_id" value="{{ $resolvedDataId }}">

    <div class="relationship-master-actions relationship-master-actions-top">
      <a
        href="{{ route('zouyo.master', array_filter(['data_id' => $resolvedDataId])) }}"
        class="btn-base-blue relationship-master-btn"
      >戻る</a>

      <button type="submit" class="btn-base-blue relationship-master-btn">登録</button>
    </div>



    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th class="text-center" style="width: 60px;">No</th>
          <th class="text-center">続柄</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($rows as $row)
          @php
            $field = "relations.{$row->relation_no}.name";
          @endphp
          <tr>
            <td class="text-center">No{{ $row->relation_no }}</td>
            <td>
              @if ($row->is_editable)
                <input
                  type="text"
                  name="relations[{{ $row->relation_no }}][name]"
                  value="{{ old($field, $row->name ?? '') }}"
                  class="form-control relationship-master-input text-start title-field-input {{ $errors->has($field) ? 'is-invalid' : '' }}"
                  maxlength="50"
                >
                @if ($errors->has($field))
                  <div class="invalid-feedback d-block">
                    {{ $errors->first($field) }}
                  </div>
                @endif
              @else
                <input
                  type="text"
                  class="form-control bg-light text-start"
                  style="width: 100%;"                  
                  value="{{ $row->name }}"
                  readonly
                  disabled
                >
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="relationship-master-actions relationship-master-actions-bottom">
      <a
        href="{{ route('zouyo.master', array_filter(['data_id' => $resolvedDataId])) }}"
        class="btn-base-blue relationship-master-btn"
      >戻る</a>
 
      <button type="submit" class="btn-base-blue relationship-master-btn">登録</button>
 
     </div>
     
     


  </form>
</div>


{{-- Enterキーで移動　--}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('relationship-master-form');
  if (!form) return;

  function getFocusableFields() {
    return Array.from(form.querySelectorAll(
      'input:not([type="hidden"]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled])'
    )).filter((el) => {
      const type = String(el.type || '').toLowerCase();
      if (['button', 'submit', 'reset', 'checkbox', 'radio', 'file'].includes(type)) {
        return false;
      }
      return el.offsetParent !== null;
    });
  }

  form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.isComposing || e.keyCode === 229) return; // 日本語変換中は除外

    const target = e.target;
    if (!(target instanceof HTMLElement)) return;

    const fields = getFocusableFields();
    const index = fields.indexOf(target);
    if (index === -1) return;

    e.preventDefault();

    const next = fields[index + 1];
    if (!next) return;

    next.focus();
    if ((next.tagName === 'INPUT' || next.tagName === 'TEXTAREA') && typeof next.select === 'function') {
      next.select();
    }
  });
});
</script>




@endsection