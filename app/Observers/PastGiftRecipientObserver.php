<?php
 
namespace App\Observers;
 
use App\Models\PastGiftRecipient;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Request;
 
 class PastGiftRecipientObserver
 {

     public function creating(PastGiftRecipient $model): void
     {
        // どこから create() されたか診断ログ（APP_DEBUG 時のみ詳細）
        $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20))
            ->map(fn($t,$i)=>sprintf('#%d %s:%s', $i, $t['file']??'?', $t['line']??'?'))
            ->implode("\n");
 
         // 親ファースト必須
         if (empty($model->data_id) || empty($model->recipient_no)) {
            Log::warning('[PGR Observer] creating WITHOUT required keys', [
                'attrs'       => $model->getAttributes(),
                'request_uri' => Request::fullUrl() ?? null,
             'trace'       => $trace,
            ]);
             throw ValidationException::withMessages([
                 'past_gift_recipients' => ['受取人の保存に必要なキー（data_id, recipient_no）が不足しています。controller 側を updateOrCreate に統一してください。'],
             ]);
         }
        // キーが揃っている場合は冪等確認用に INFO に落とす（うるさい場合はコメントアウト可）
        if (config('app.debug')) {
            Log::info('[PGR Observer] creating with keys OK', [
                'attrs'       => $model->getAttributes(),
                'request_uri' => Request::fullUrl() ?? null,
                'trace'       => $trace,
            ]);
        }
 
         // 同一キーが既に存在するなら create を禁止（UPSERT を使うべき）
         $exists = PastGiftRecipient::where([
             'data_id'      => $model->data_id,
             'recipient_no' => $model->recipient_no,
         ])->exists();
         if ($exists) {
             throw ValidationException::withMessages([
                 'past_gift_recipients' => ['同一キー（data_id, recipient_no）の行が存在します。create ではなく updateOrCreate / upsert を使用してください。'],
             ]);
         }
     }
 }
