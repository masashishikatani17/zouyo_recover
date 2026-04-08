@extends('layouts.min')

@section('title', 'データ編集')

@section('content')
@php
  $today = now()->format('Y-m-d');
  $updatedOn = optional($data->updated_at)->format('Y-m-d H:i:s') ?: $today;
@endphp

<div class="container" style="max-width:650px;">
  <div class="d-flex justify-content-between align-items-center m-3">
    <hb>▶ データ編集</hb>
  </div>

  <div class="align-middle mb-3 p-3 border rounded bg-pale" style="width:570px;">
    <div><strong>お客様名：</strong>{{ $guest?->name }}</div>    
    <div><strong>現在の年度：</strong>{{ (int)($data->kihu_year ?? 0) }}年</div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <form method="POST" action="{{ route('zouyo.data.update') }}" id="zouyo-data-edit-form">
    @csrf
    <input type="hidden" name="data_id" value="{{ $data->id }}">

    <div class="m-3">
      <table class="table-input align-middle" style="width:570px; table-layout: fixed;">
        <tbody>

          <tr>
            <th class="text-start ps-2" style="height:33px; width:150px;">お客様名</th>
            <td class="text-start">
              <input type="text"
                     name="guest_name"
                     class="form-control kana20"
                     style="height:32px; width:260px;"
                     maxlength="50"
                     value="{{ old('guest_name', (string)($guest?->name ?? '')) }}"
                     required>
            </td>
          </tr>

          <tr>
            <th class="text-start ps-2" style="height:33px; width:150px;">更新日時</th>
            <td class="text-start ps-2" style="width:420px;">
              <input type="text"
                     class="form-control"
                     style="height:32px; width:220px;"
                     value="{{ $updatedOn }}"
                     readonly>
              <div class="text-muted ms-1 mt-1 mb-1" style="font-size:12px;">
                ※保存時に自動更新されます。
              </div>
            </td>
          </tr>

          <tr>
            <th class="text-start ps-2" style="height:33px;">年度</th>
            <td class="text-start">
              @php
                $yy = (int) old('kihu_year', (int)($data->kihu_year ?? 0));
              @endphp
              <select class="form-select text-start" style="height:32px; width:150px; font-size:12px;" name="kihu_year" required>
                @foreach($years as $y)
                  <option value="{{ $y }}" @selected((int)$yy === (int)$y)>{{ $y }}</option>
                @endforeach
              </select>
            </td>
          </tr>

          <tr>
            <th class="text-start ps-2" style="height:33px;">データ名</th>
            <td class="text-start">
              <input type="text"
                     name="data_name"
                     class="form-control kana20"
                     style="height:32px; width:260px;"
                     maxlength="25"
                     value="{{ old('data_name', (string)($data->data_name ?? '')) }}"
                     required>
              <div class="text-muted ms-1 mt-1 mb-1" style="font-size:12px;">
                ※改行・タブ・制御文字・\ / : * ? " &lt; &gt; | は使用できません。
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <hr class="mb-2">

      <div class="d-flex justify-content-between mx-2 mb-2">
        <div>
          <button type="submit" class="btn btn-base-green">保 存</button>
          @if($canDelete)
            <button type="button" class="btn-base-red" data-bs-toggle="modal" data-bs-target="#deleteModal">
              このデータを削除
            </button>
          @endif
        </div>

        <div class="d-flex">
          <a href="{{ route('zouyo.data.index', ['guest_id' => $guest?->id, 'data_id' => $data->id]) }}"
             class="btn btn-base-blue">キャンセル</a>
        </div>
      </div>
    </div>
  </form>

  @if($canDelete)
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h15 class="modal-title">削除確認</h15>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">
            {{ (int)($data->kihu_year ?? 0) }}年 / {{ (string)($data->data_name ?? '') }} を削除します。よろしいですか？
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-base-blue" data-bs-dismiss="modal">キャンセル</button>
          <form method="POST" action="{{ route('zouyo.data.destroy') }}">
            @csrf
            <input type="hidden" name="data_id" value="{{ $data->id }}">
            <button type="submit" class="btn btn-base-red">削除する</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  @endif
</div>
@endsection
