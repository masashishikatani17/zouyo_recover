<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PastGiftCalendarEntry;

use Illuminate\Support\Facades\Log;

class PastGiftController extends Controller
{
    /**
     * 過年度贈与データを取得して返す
     */
    public function fetchPastGiftData(Request $request)
    {
        $recipientNo = $request->input('recipient_no');
        $dataId = $request->input('data_id');
    
        // 過年度贈与データを取得
        $pastGifts = PastGiftCalendarEntry::where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->get();
    
        // ここで過年度贈与額と加算累計額を計算
        $giftAmount = $pastGifts->sum('amount_thousand'); // 贈与額合計
        $accumulatedAmount = $pastGifts->sum('tax_thousand'); // 贈与税額合計
    
        // デバッグ用のログ
        Log::debug('Past Gifts Data:', [
            'giftAmount' => $giftAmount,
            'accumulatedAmount' => $accumulatedAmount,
        ]);
    
        // JSONで返却
        return response()->json([
            'giftAmount' => $giftAmount,
            'accumulatedAmount' => $accumulatedAmount,
        ]);
    }

}


    