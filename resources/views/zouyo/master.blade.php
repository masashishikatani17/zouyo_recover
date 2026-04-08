@extends('layouts.min')

@section('content')
<div class="container-grey mt-2" style="width: 500px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">マスター一覧</hb>
  </div>
  <div class="card-body mt-3">
    <div class="wrapper">
      <table width="440" align="center">


                  {{-- ▼ 追加：贈与税（一般／特例）・相続税 速算表 --}}
                  <tr>
                      <td>
                          <div>
                              <a href="{{ route('zouyo.master.zouyo_general', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">贈与税 速算表（一般税率）</a>
                          </div>
                      </td>
                      <td></td>
                  </tr>

                  <tr>
                      <td height="8" colspan="3" style="font-size: 2px;">&nbsp;</td>
                  </tr>

                  <tr>
                      <td>
                          <div>
                              <a href="{{ route('zouyo.master.zouyo_tokurei', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">贈与税 速算表（特例税率）</a>
                          </div>
                      </td>
                  </tr>

                  <tr>
                      <td height="8" colspan="3" style="font-size: 2px;">&nbsp;</td>
                  </tr>

                  <tr>
                      <td>
                          <div>
                              <a href="{{ route('zouyo.master.sozoku', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">相続税 速算表</a>
                          </div>
                      </td>
                      <td></td>
                      <td></td>
                  </tr>



                  <tr>
                      <td height="8" colspan="3" style="font-size: 2px;">&nbsp;</td>
                  </tr>

                  <tr>
                      <td>
                          <div>
                              <a href="{{ route('master.relationships.edit', ['data_id' => $dataId]) }}" class="btn btn-outline-secondary btn-sm">続柄マスター</a>
                          </div>
                      </td>
                      <td></td>
                      <td></td>
                  </tr>


      </table>
        <hr>
        <div class="text-end me-2 mb-2">
          <a href="{{ route('zouyo.input', ['data_id' => $dataId], false) }}" class="btn-base-blue">戻 る</a>
        </div>
      </div> 
  </div>
</div>
@endsection