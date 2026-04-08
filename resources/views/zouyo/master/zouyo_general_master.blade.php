@extends('layouts.min')

@section('content')

<div class="container-grey mt-2" style="width: 450px;">
  <div class="card-header d-flex align-items-start">
    <img src="{{ asset('storage/images/kado_lefttop_m.jpg') }}" alt="…">
    <hb class="mb-0 mt-2">贈与税の速算表（一般税率）</hb>
  </div>
  <div class="card-body">
    <div class="wrapper">
      <table class="table-base table-bordered align-middle">
        <tbody>
          @php
            $fmtAmt = static fn($v)=>$v===null? '': number_format((int)$v);
            $fmtPct = static fn($v)=>$v===null? '': rtrim(rtrim(number_format((float)$v,3),'0'),'.').'%';
            $basicDeductionAmount = (int) data_get($rates, '0.basic_deduction_amount', 1100000);
          @endphp

          <tr>
            <td colspan="3" class="text-center">基礎控除後の課税価格(円)</td>
            <td class="text-center">税率</td>
            <td class="text-center">控除額(円)</td>
          </tr>

          @foreach ($rates as $rate)
            <tr>
              <td class="text-end b-r-no" style="width:80px;">{{ $fmtAmt($rate['lower'] ?? null) }}</td>
              <td class="text-center b-l-no b-r-no" style="width:20px;">～</td>
              <td class="text-end b-l-no" style="width:80px;">{{ $fmtAmt($rate['upper'] ?? null) }}</td>
              <td class="text-end" style="width:50px;">{{ $fmtPct($rate['rate'] ?? null) }}</td>
              <td class="text-end" style="width:80px;">{{ number_format((int)($rate['deduction_amount'] ?? 0)) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
      

      <div class="text-start ms-2 mt-2 mb-2">        
        贈与税の基礎控除額　年間{{ number_format($basicDeductionAmount) }}円
      </div>
      
      
      <hr>
      <div class="text-end me-2 mb-2">
        <a href="{{ route('zouyo.master', ['data_id' => $dataId]) }}" class="btn-base-blue">戻 る</a>
      </div>
    </div>
  </div>
</div>


@endsection
