@extends('layouts.min')

@section('content')

<div class="container-grey mt-2" style="width: 500px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">相続税の速算表</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      <table class="table-base table-bordered align-middle">
        <tbody>
          @php
            $fmtAmt = static fn($v)=>$v===null? '': number_format((int)$v);
            $fmtPct = static fn($v)=>$v===null? '': rtrim(rtrim(number_format((float)$v,3),'0'),'.').'%';
          @endphp

          <tr>
            <td colspan="3" class="text-center">法定相続分に応ずる取得金額(円)</td>
            <td class="text-center">税率</td>
            <td class="text-center">控除額(円)</td>
          </tr>

          @foreach ($rates as $rate)
            <tr>
              <td class="text-end b-r-no" style="width:100px;">{{ $fmtAmt($rate['lower'] ?? null) }}</td>
              <td class="text-center b-l-no b-r-no" style="width:20px;">～</td>
              <td class="text-end b-l-no" style="width:100px;">{{ $fmtAmt($rate['upper'] ?? null) }}</td>
              <td class="text-end" style="width:60px;">{{ $fmtPct($rate['rate'] ?? null) }}</td>
              <td class="text-end" style="width:100px;">{{ number_format((int)($rate['deduction_amount'] ?? 0)) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>


    @php
      $rateFirst = $rates instanceof \Illuminate\Support\Collection ? $rates->first() : ($rates[0] ?? null);
      $basicDeductionBaseYen = (int) data_get($rateFirst, 'basic_deduction_base_yen', 30000000);
      $basicDeductionPerHeirYen = (int) data_get($rateFirst, 'basic_deduction_per_heir_yen', 6000000);
    @endphp

    <div class="mt-3" style="font-size: 14px; line-height: 1.6;">
      <div>相続税の基礎控除額</div>
      <div>{{ number_format($basicDeductionBaseYen) }}円＋{{ number_format($basicDeductionPerHeirYen) }}円×法定相続人の人数</div>
    </div>
    
        


      <hr>
      <div class="text-end me-2 mb-2">
        <a href="{{ route('zouyo.master', ['data_id' => $dataId]) }}" class="btn-base-blue">戻 る</a>
      </div>
    </div>
  </div>
</div>

@endsection
