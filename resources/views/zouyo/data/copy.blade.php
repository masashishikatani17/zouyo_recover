@extends('layouts.min')

@section('content')
<div class="container" style="width: 660px;">
  <div class="d-flex justify-content-between align-items-center m-3">
    <hb>▶ 既存データのコピー</hb>
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

  <div class="m-3">
    <h15 class="ms-2 mt-1 mb-3">○コピー元</h15>
    <table class="table-base table-bordered align-middle w-auto mx-auto">
      <tbody>
        <tr>
          <th class="text-center" style="width:100px;">お客様名</th>
          <td class="px-2 text-start ps-1" style="min-width:300px;">{{ $source->guest?->name ?? '—' }}</td>
        </tr>
        <tr>
          <th class="text-center">元の年度</th>
          <td class="px-2 text-start ps-1">{{ $source->kihu_year ? $source->kihu_year.'年' : '—' }}</td>
        </tr>
        <tr>
          <th class="text-center">元データ名</th>
          <td class="px-2 text-start ps-1">{{ $source->data_name ?? '—' }}</td>
        </tr>
      </tbody>
    </table>

    <hr>

    <form action="{{ route('zouyo.data.copy') }}" method="POST" id="zouyo-data-copy-form">
      @csrf
      <input type="hidden" name="selected_data_id" value="{{ $source->id }}">

      <h15 class="ms-2 mb-3">○コピー先の指定</h15>

      <table class="table-input align-middle mx-auto" style="max-width:570px;">
        <tbody>
          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">コピー先お客様</th>
            <td class="text-start bg-cream">
              <div class="form-check form-check-inline ms-2 mt-2 mb-0">
                <input class="form-check-input" type="radio" name="copy_mode" id="copy_mode_same" value="same"
                       {{ old('copy_mode', 'same') === 'same' ? 'checked' : '' }}>
                <label class="form-check-label" for="copy_mode_same">同じお客様</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="copy_mode" id="copy_mode_existing" value="existing"
                       {{ old('copy_mode') === 'existing' ? 'checked' : '' }}>
                <label class="form-check-label" for="copy_mode_existing">登録済から選択</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="copy_mode" id="copy_mode_new" value="new"
                       {{ old('copy_mode') === 'new' ? 'checked' : '' }}>
                <label class="form-check-label" for="copy_mode_new">新規のお客様</label>
              </div>
            </td>
          </tr>

          <tr id="target_guest_existing_row">
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">登録済お客様</th>
            <td class="text-start">
              <select name="target_guest_id" id="target_guest_id" class="form-select" style="height:32px; max-width:260px; font-size:13px;">
                <option value="">選択して下さい</option>
                @foreach ($guests as $guest)
                  <option value="{{ $guest->id }}"
                    @selected((string) old('target_guest_id') === (string) $guest->id)>
                    {{ $guest->name }}
                  </option>
                @endforeach
              </select>
            </td>
          </tr>

          <tr id="target_guest_name_row">
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">新規お客様名</th>
            <td class="text-start">
              <input type="text"
                     name="target_guest_name"
                     id="target_guest_name"
                     class="form-control kana20"
                     style="height:32px; max-width:320px;"
                     maxlength="25"
                     value="{{ old('target_guest_name') }}"
                     placeholder="新規のお客様名を入力して下さい">
            </td>
          </tr>

          <tr>
            <th class="text-start ps-1" style="min-width:120px; white-space:nowrap;">年度</th>
            <td class="text-start">
              @php
                $minY = 2025; $maxY = 2035;
                $defaultY = (int)($source->kihu_year ?: 2025);
                if ($defaultY < $minY) $defaultY = $minY;
                if ($defaultY > $maxY) $defaultY = $maxY;
                $oldYear = (int) old('kihu_year', $defaultY);
              @endphp
              <select name="kihu_year" class="form-select" style="height:32px; max-width:120px; font-size:13px;">
                @for ($y = $maxY; $y >= $minY; $y--)
                  <option value="{{ $y }}" @selected((int)$oldYear === (int)$y)>{{ $y }}</option>
                @endfor
              </select>
            </td>
          </tr>

          <tr>
            <th class="text-start ps-1" style="height:33px; white-space:nowrap;">データ名</th>
            <td class="text-start">
              <input type="text"
                     name="data_name"
                     class="form-control kana20"
                     style="height:32px; max-width: 320px;"
                     maxlength="25"
                     value="{{ old('data_name', $suggestedCopyName) }}"
                     required>
            </td>
      </tr>
        </tbody>
  </table>

      <hr class="mb-2">
      <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-base-blue">コピー</button>
        <a href="{{ route('zouyo.data.index', ['guest_id' => $source->guest?->id, 'data_id' => $source->id]) }}"
           class="btn btn-base-blue">
          キャンセル
        </a>
      </div>
    </form>
  </div>
</div>
@endsection





@push('scripts')
<script>
(function () {
  const sameRadio = document.getElementById('copy_mode_same');
  const existingRadio = document.getElementById('copy_mode_existing');
  const newRadio = document.getElementById('copy_mode_new');

  const existingRow = document.getElementById('target_guest_existing_row');
  const newRow = document.getElementById('target_guest_name_row');
  const targetGuestId = document.getElementById('target_guest_id');
  const targetGuestName = document.getElementById('target_guest_name');

  function syncCopyMode() {
    const isSame = !!(sameRadio && sameRadio.checked);
    const isExisting = !!(existingRadio && existingRadio.checked);
    const isNew = !!(newRadio && newRadio.checked);

    if (existingRow) existingRow.style.display = isExisting ? '' : 'none';
    if (newRow) newRow.style.display = isNew ? '' : 'none';

    if (targetGuestId) targetGuestId.disabled = !isExisting;
    if (targetGuestName) targetGuestName.disabled = !isNew;

    if (isSame) {
      if (targetGuestId) targetGuestId.value = '';
      if (targetGuestName) targetGuestName.value = '';
    }
  }

  sameRadio?.addEventListener('change', syncCopyMode);
  existingRadio?.addEventListener('change', syncCopyMode);
  newRadio?.addEventListener('change', syncCopyMode);
  syncCopyMode();
})();
</script>
@endpush
