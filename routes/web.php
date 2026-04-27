<?php
//web.php

// web.php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Master\RelationshipMasterController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request; // ★ 追加：/zouyo/master のクロージャで型ヒント使用
 

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tax\ZouyoController;
use App\Http\Controllers\Tax\ZouyotaxController;
use App\Http\Controllers\Tax\ZouyoDataController;
use App\Http\Controllers\PdfSampleController;
use App\Http\Controllers\PastGiftController;

use App\Http\Controllers\ZouyoPdfController;


Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    return redirect()->route('zouyo.data.index');
});



// Dashboard Route (keep if needed)
Route::get('/dashboard', function () { return view('dashboard'); })
    ->middleware(['auth', 'verified'])->name('dashboard');

// Profile Routes (Standard)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 最適贈与専用：データ一覧画面
    Route::get('/zouyo/data', [ZouyoDataController::class, 'index'])->name('zouyo.data.index');
    Route::get('/zouyo/data/create', [ZouyoDataController::class, 'create'])->name('zouyo.data.create');
    Route::post('/zouyo/data', [ZouyoDataController::class, 'store'])->name('zouyo.data.store');
    Route::get('/zouyo/data/edit', [ZouyoDataController::class, 'edit'])->name('zouyo.data.edit');
    Route::post('/zouyo/data/update', [ZouyoDataController::class, 'update'])->name('zouyo.data.update');
    Route::get('/zouyo/data/copy', [ZouyoDataController::class, 'copyForm'])->name('zouyo.data.copy.form');
    Route::post('/zouyo/data/copy', [ZouyoDataController::class, 'copy'])->name('zouyo.data.copy');
    Route::post('/zouyo/data/delete', [ZouyoDataController::class, 'destroyData'])->name('zouyo.data.destroy');
    Route::post('/zouyo/data/delete-guest', [ZouyoDataController::class, 'destroyGuest'])->name('zouyo.data.destroyGuest');

});

require __DIR__.'/auth.php';

// Sample PDF Route
Route::get('/pdf/sample', [PdfSampleController::class, 'show'])->name('pdf.sample');

// Zouyo Routes (Tax Gift Controller)
Route::middleware('auth')->prefix('zouyo')->group(function () {    


    Route::get('/', [ZouyoController::class, 'index'])->name('zouyo.index');

    Route::get('/input', [ZouyoController::class, 'index'])->name('zouyo.input');

    // ▼ 過年度（暦年/精算）の明細＋合計を返す：フロントの route('zouyo.past.fetch') は常にこれを指す
    Route::get('/past/fetch', [ZouyoController::class, 'pastFetch'])->name('zouyo.past.fetch');

    // 将来贈与の取得API（正しいハンドラ名に）
    Route::get('/future/fetch', [ZouyoController::class, 'fetchFutureGifts'])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->middleware([])
        ->name('zouyo.future.fetch');


    Route::post('/save', [ZouyoController::class, 'save'])->name('zouyo.save');
    


    Route::post('/preview/inheritance-before', [ZouyoController::class, 'previewInheritanceBefore'])
        ->name('zouyo.preview.inheritance_before');

    // ★ 計算専用エンドポイント（/zouyo/calc で統一）
    Route::post('/calc', [ZouyoController::class, 'calc'])->name('zouyo.calc');
    
});

// Zouyo Master Routes (Tax Calculation Master)
Route::middleware('auth')->prefix('zouyo/master')->group(function () {    

    Route::get('/', function (Request $request) {
        $dataId = $request->query('data_id');
        return view('zouyo.master', compact('dataId'));
    })->name('zouyo.master');

    Route::get('/zouyo_general', [ZouyotaxController::class, 'zouyoGeneralMaster'])->name('zouyo.master.zouyo_general');
    Route::get('/zouyo_tokurei', [ZouyotaxController::class, 'zouyoTokureiMaster'])->name('zouyo.master.zouyo_tokurei');
    Route::get('/sozoku', [ZouyotaxController::class, 'sozokuMaster'])->name('zouyo.master.sozoku');
});

// Debug Route
Route::get('/_debug/ping', function (\Illuminate\Http\Request $r) {
    \Log::info('[PING]', ['ip' => $r->ip(), 'ua' => $r->userAgent()]);
    return response()->json([
        'ok' => true,
        'ts' => now()->toDateTimeString(),
        'url' => url()->current(),
    ]);
});

// （任意）合計だけ返すデバッグ用エンドポイント。ルート名を重複させない
Route::get('/past-gift-data', [\App\Http\Controllers\PastGiftController::class, 'fetchPastGiftData'])
     ->name('zouyo.past.totals'); // ← デバッグ用は別名に維持



// 2026.04.27 追加
// Gift History Management Routes
// 贈与履歴管理システム。既存贈与名人DBは読み取り専用で参照し、
// 保存先は gift_history 接続の専用DBに分離する。
Route::middleware('auth')
    ->prefix('gift-history')
    ->name('gift-history.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\GiftHistory\GiftHistoryCaseController::class, 'index'])
            ->name('index');

        Route::post('/start', [\App\Http\Controllers\GiftHistory\GiftHistoryCaseController::class, 'start'])
            ->name('start');
            
            
        Route::get('/{case}/family', [\App\Http\Controllers\GiftHistory\GiftHistoryFamilyController::class, 'edit'])
            ->whereNumber('case')
            ->name('family.edit');

        Route::post('/{case}/family/import', [\App\Http\Controllers\GiftHistory\GiftHistoryFamilyController::class, 'importFromZouyo'])
            ->whereNumber('case')
            ->name('family.import');

        Route::post('/{case}/family', [\App\Http\Controllers\GiftHistory\GiftHistoryFamilyController::class, 'update'])
            ->whereNumber('case')
            ->name('family.update');


        Route::get('/{case}', [\App\Http\Controllers\GiftHistory\GiftHistoryCaseController::class, 'show'])
            ->whereNumber('case')
            ->name('show');
    });
        
        
        // 2026.04.27 追加
        Route::get('/{case}/family', [\App\Http\Controllers\GiftHistory\GiftHistoryFamilyController::class, 'edit'])
            ->whereNumber('case')
            ->name('family.edit');

        Route::post('/{case}/family/import', [\App\Http\Controllers\GiftHistory\GiftHistoryFamilyController::class, 'importFromZouyo'])
            ->whereNumber('case')
            ->name('family.import');

        Route::post('/{case}/family', [\App\Http\Controllers\GiftHistory\GiftHistoryFamilyController::class, 'update'])
            ->whereNumber('case')
            ->name('family.update');

 
Route::post('/zouyo/save/family', [ZouyoController::class, 'saveFamily'])->name('zouyo.save.family');
Route::post('/zouyo/save/past', [ZouyoController::class, 'savePast'])->name('zouyo.save.past');
Route::post('/zouyo/save/future', [ZouyoController::class, 'saveFuture'])->name('zouyo.save.future');
Route::post('/zouyo/save/inheritance', [ZouyoController::class, 'saveInheritance'])->name('zouyo.save.inheritance');




// （任意）単独表示用のPDFプレビューが不要なら /zouyo/pdf GET は削除してOK
Route::get('/zouyo/pdf', [ZouyoPdfController::class, 'show'])->name('zouyo.pdf');

// ▼ 結果タブの「PDF作成」ボタン用：チェックされたページをまとめてPDF化
Route::post('/zouyo/pdf/generate', [ZouyoPdfController::class, 'generateBySelection'])
    ->name('generate_pdf');


Route::post('/save_selected_pages', function () {
    $dataId = request('data_id');
    $pages = request('pages');

    // セッションにページ情報を保存
    session()->put("zouyo.pdf_pages.{$dataId}", $pages);

    return response()->json(['status' => 'success']);
})->name('save_selected_pages');


Route::middleware(['auth'])->group(function () {
    Route::get('/master/relationships', [RelationshipMasterController::class, 'edit'])
        ->name('master.relationships.edit');
    Route::post('/master/relationships', [RelationshipMasterController::class, 'update'])
        ->name('master.relationships.update');
});