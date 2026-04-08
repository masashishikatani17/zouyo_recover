<?php
//ZouyoFutureController
namespace App\Http\Controllers\Tax;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Data;

class ZouyoFutureController extends Controller
{
    public function fetch(Request $req)
    {
        // フォーム送信(FormData)／JSONどちらでも拾えるようにする

        $dataId = (int) (
            $req->input('data_id')
            ?? $req->query('data_id')
            ?? session('selected_data_id')
            ?? 0
        );        

        $rn     = $req->input('future_recipient_no');


        $req->merge(['data_id' => $dataId]);

        $req->validate([
            'data_id'             => ['required','integer','min:1'],
            'future_recipient_no' => ['required','integer'],
        ]);

        session(['selected_data_id' => $dataId]);

        $companyId = auth()->user()?->company_id;
        abort_unless($companyId, 403, '会社情報を取得できません。');

        $data = Data::where('id', $dataId)
            ->where('company_id', $companyId)
            ->firstOrFail();


        // まずは疎通優先：最低限の空ペイロード（実装に合わせて埋めてください）
        $payload = [
            'recipient_no' => (int)$rn,
            'header' => [
                'year'  => now()->year, // 必要に応じて置換
                'month' => 5,
                'day'   => 6,
            ],
            'plan' => [
                'cal_amount'      => [],
                'cal_basic'       => [],
                'cal_after_basic' => [],
                'cal_tax'         => [],
                'cal_cum'         => [],
                'set_amount'      => [],
                'set_basic110'    => [],
                'set_after_basic' => [],
                'set_after_25m'   => [],
                'set_tax20'       => [],
                'set_cum'         => [],
                'gift_month'      => [],
                'gift_day'        => [],
            ],
            'birth' => null,
        ];

        return response()->json($payload);
    }
}
