@extends('layouts.min')

@section('content')
<div class="container" style="width: 580px;">
  <div class="d-flex justify-content-between align-items-center p-3">
    <hb class="ms-2">▶ 新規データ作成</hb>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('zouyo.data.store') }}" method="POST" id="zouyo-data-create-form" class="mt-3">
    @csrf

    <table class="table-input align-middle">
      <tbody>
        <tr>
          <th class="text-start ps-2" style="height:33px; width:140px;">お客様の指定</th>
          <td class="th-cream text-start ps-2 py-0 align-middle">
            <div class="d-flex align-items-center" style="height:33px; gap:14px;">
              <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="guest_mode" id="guest_mode_existing" value="existing"
                       {{ old('guest_mode', $selectedGuest ? 'existing' : 'new') === 'existing' ? 'checked' : '' }}>
                <label class="form-check-label" for="guest_mode_existing">登録済から選択</label>
              </div>
              <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="radio" name="guest_mode" id="guest_mode_new" value="new"
                       {{ old('guest_mode', $selectedGuest ? 'existing' : 'new') === 'new' ? 'checked' : '' }}>
                <label class="form-check-label" for="guest_mode_new">新規で登録</label>
              </div>
            </div>
          </td>
        </tr>

        <tr id="guest_existing_row">
          <th class="text-start ps-2" style="height:33px;">登録済お客様</th>
          <td class="text-start">
              <select name="guest_id" id="guest_id" class="form-select" style="height:32px; max-width:260px; font-size:12px;">                  
              <option value="">選択して下さい</option>
              @foreach ($guests as $guest)
                <option value="{{ $guest->id }}"
                    @selected((string) old('guest_id', $selectedGuest?->id) === (string) $guest->id)>
                  {{ $guest->name }}
                </option>
              @endforeach
            </select>
          </td>
        </tr>

        <tr id="guest_name_row">
          <th class="text-start ps-2" style="height:33px;">お客様名</th>
          <td class="text-start">
            <input type="text"
                   name="guest_name"
                   id="guest_name"
                   class="form-control kana20"
                   style="height:32px;"
                   maxlength="25"
                   value="{{ old('guest_name') }}"
                   placeholder="新規登録時は入力して下さい">
          </td>
        </tr>

        <tr>
          <th class="text-start ps-2" style="height:33px;">年度</th>
          <td class="text-start">
            @php
              $minY = 2025; $maxY = 2035;
              $now = (int)date('Y');
              $default = min(max($now, $minY), $maxY);
              $oldYear = (int) old('kihu_year', $default);
              if ($oldYear < $minY) $oldYear = $minY;
              if ($oldYear > $maxY) $oldYear = $maxY;
            @endphp
              <select name="kihu_year" class="form-select" style="height:32px; max-width:120px; font-size:12px;">
              @for ($y = $maxY; $y >= $minY; $y--)
                <option value="{{ $y }}" @selected((int)$oldYear === (int)$y)>{{ $y }}</option>
              @endfor
            </select>
          </td>
        </tr>

        <tr>
          <th class="text-start ps-2" style="height:33px;">データ名</th>
          <td class="text-start">
            <input type="text"
                   name="data_name"
                   id="data_name"
                   class="form-control kana20"
                   style="height:32px; max-width:260px;"
                   maxlength="25"
                   value="{{ old('data_name', '') }}"
                   required>
          </td>
        </tr>
      </tbody>
    </table>

    <hr class="mb-2 mx-3">
    <div class="d-flex justify-content-end gap-2 me-3 mb-3">
      <button type="submit" class="btn-base-blue">作 成</button>
      <a href="{{ route('zouyo.data.index', ['guest_id' => $selectedGuest?->id]) }}" class="btn-base-blue">キャンセル</a>
    </div>
  </form>
</div>
@endsection












@push('scripts')
<script>
(function () {
  const existingRadio = document.getElementById('guest_mode_existing');
  const newRadio = document.getElementById('guest_mode_new');
  const existingRow = document.getElementById('guest_existing_row');
  const guestNameRow = document.getElementById('guest_name_row');
  const guestId = document.getElementById('guest_id');
  const guestName = document.getElementById('guest_name');

  function syncGuestMode() {
    const isExisting = !!(existingRadio && existingRadio.checked);

    if (existingRow) existingRow.style.display = isExisting ? '' : 'none';
    if (guestNameRow) guestNameRow.style.display = isExisting ? 'none' : '';

    if (guestId) guestId.disabled = !isExisting;
    if (guestName) guestName.disabled = isExisting;
  }

  existingRadio?.addEventListener('change', syncGuestMode);
  newRadio?.addEventListener('change', syncGuestMode);
  syncGuestMode();
})();
</script>
@endpush


