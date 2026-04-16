<?php
//App\Http\Controllers\\Tax\ZouyoController.php

namespace App\Http\Controllers\Tax;

use App\Http\Controllers\Controller;

// ★ 追加：コントローラ内で参照しているモデル/型を明示インポート
use App\Models\ZouyoSyoriSetting;
use App\Models\FurusatoInput;
use App\Models\FurusatoResult;
use App\Models\PastGiftRecipient;
use App\Models\PastGiftInput;
use App\Models\PastGiftCalendarEntry;
use App\Models\PastGiftSettlementEntry;

// ★ Eloquent の Data モデルを明示インポート
use App\Models\Data;
use App\Models\Guest;

// ★ 追加：$stored を取得するため
use App\Models\ZouyoInput;          


use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth; // ← ファイル冒頭に追記
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Services\ZouyotaxCalc;

use Illuminate\Http\JsonResponse;

use App\Http\Requests\ZouyoSyoriRequest;


use App\Models\ProposalFamilyMember;


use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

use App\Services\Zouyo\ZouyoGeneralRateResolver;


final class ZouyoController extends Controller
{
    private const MASTER_KIHU_YEAR = 2025;


    public function index(Request $req)
    {

        $dataId = (int) ($req->input('data_id') ?? $req->query('data_id') ?? session('selected_data_id') ?? 0);

        if ($dataId) {
            session(['selected_data_id' => $dataId]);
        }

        $context = $this->makeInputContext($req, $dataId);
        $context['out'] = ['inputs' => $context['savedInputs']];

        $session = session();
        // ★ 新仕様：calc() で Cache に保存した結果があれば最優先で使う
        if ($session->has('zouyo.results_key')) {
            $cacheKey = (string) $session->get('zouyo.results_key');
            $cached   = \Cache::get($cacheKey, []);
            $context['results'] = is_array($cached) ? $cached : [];
        } elseif ($session->has('zouyo_results')) {
            // ★ 旧仕様：セッションに直接格納していた場合
            $context['results'] = (array) $session->get('zouyo_results');
        } elseif ($dataId) {
            // ★ さらに旧仕様：DB に保存している場合
            $context['results'] = $this->getStoredZouyoResults($dataId);
        } else {
            $context['results'] = [];
        }

        if ($session->has('show_zouyo_result')) {
            $context['showResult'] = (bool) $session->get('show_zouyo_result');
        } else {
            $context['showResult'] = !empty($context['results']);
        }
        unset($context['savedInputs']);

        return view('zouyo.input', $context);
    }


    private function resolveBunriFlag(?int $dataId): int
    {

        if (! $dataId) {
            return 0;
        }

        $payload = ZouyoSyoriSetting::query()
            ->where('data_id', $dataId)
            ->value('payload');

        if (! is_array($payload)) {
            return 0;
        }

        $flag = $payload['bunri_flag'] ?? 0;

        return (int) ($flag ? 1 : 0);
    }
    
    
    

    private function makeInputContext(Request $request, ?int $dataId): array
    {

        $dataId = (int) (
            $dataId
            ?? $request->input('data_id')
            ?? $request->query('data_id')
            ?? session('selected_data_id')
            ?? 0
        );

         $bunriFlag = 0;
         $kihuYear = null;
         $warekiPrev = null;
         $warekiCurr = null;
         $savedInputs = [];
         $header = [];              // ★ 追加：未定義回避
         $family = [];              // ★ 追加：未定義回避


         $prefillPast = [          // 過年度の贈与プレフィル
             'inherit' => ['year'=>null,'month'=>null,'day'=>null],
             'recipient_no' => null,

             'rekinen' => [
                 'year'  => array_fill(1, 10, null),
                 'month' => array_fill(1, 10, null),
                 'day'   => array_fill(1, 10, null),
                 'zoyo'  => array_fill(1, 10, null),                 
                 'zouyo' => array_fill(1, 10, null),
                 'kojo'  => array_fill(1, 10, null),
                 'total' => ['zoyo'=>null, 'zouyo'=>null, 'kojo'=>null],
             ],
             'seisan' => [
                 'year'  => array_fill(1, 10, null),
                 'month' => array_fill(1, 10, null),
                 'day'   => array_fill(1, 10, null),
                 'zoyo'  => array_fill(1, 10, null),                 
                 'zouyo' => array_fill(1, 10, null),
                 'kojo'  => array_fill(1, 10, null),
                 'total' => ['zoyo'=>null, 'zouyo'=>null, 'kojo'=>null],
             ],
         ];




         // これからの贈与プレフィル
         $prefillFuture = [
             'header' => ['year'=>null,'month'=>null,'day'=>null],
             'recipient_no' => null,
             'plan' => [
                 'gift_year'  => array_fill(1, 20, null),
                 'age'        => array_fill(1, 20, null),
                 // 暦年
                 'cal_amount'      => array_fill(1, 20, null),
                 'cal_basic'       => array_fill(1, 20, null),
                 'cal_after_basic' => array_fill(1, 20, null),
                 'cal_tax'         => array_fill(1, 20, null),
                 'cal_cum'         => array_fill(1, 20, null),
                 // 精算
                 'set_amount'      => array_fill(1, 20, null),
                 'set_basic110'    => array_fill(1, 20, null),
                 'set_after_basic' => array_fill(1, 20, null),
                 'set_after_25m'   => array_fill(1, 20, null),
                 'set_tax20'       => array_fill(1, 20, null),
                 'set_cum'         => array_fill(1, 20, null),
             ],
         ];




         if ($dataId) {
            
             $data = $this->findDataForInput($request, $dataId);
             
             $companyId = (int) ($data->company_id ?? (Auth::user()?->company_id ?? 0));             

             $selectedCustomerName = $this->resolveSelectedGuestNameForHeader($data);             



             // ★ 追加：$stored を実際に取得
             $stored = ZouyoInput::query()
                 ->where('data_id', $dataId)
                 ->value('payload');


             $bunriFlag = $this->resolveBunriFlag($dataId);
             if (is_array($stored)) {
                 $savedInputs = $stored;
             }


            // ▼ 相続開始日は header に統一（title.blade の日付を使用）
            if ($ph = \App\Models\ProposalHeader::query()->where('data_id', $dataId)->first()) {
                $prefillPast['inherit']['year']  = $ph->doc_year;
                $prefillPast['inherit']['month'] = $ph->doc_month;
                $prefillPast['inherit']['day']   = $ph->doc_day;
            }

            // ▼ 受贈者の初期選択
            // 1) 直近更新の暦年 or 精算エントリがある recipient_no を優先
            $latestCal = PastGiftCalendarEntry::query()
                ->where('data_id', $dataId)
                ->select('recipient_no')
                ->orderByDesc('updated_at')
                ->first();
            $latestSet = PastGiftSettlementEntry::query()
                ->where('data_id', $dataId)
                ->select('recipient_no')
                ->orderByDesc('updated_at')
                ->first();
            $prefillPast['recipient_no'] = $latestCal?->recipient_no ?? $latestSet?->recipient_no;
            // 2) それも無ければ最小の recipient_no を選択
            if (!$prefillPast['recipient_no']) {
                if ($rcpt = PastGiftRecipient::query()->where('data_id', $dataId)->orderBy('recipient_no')->first()) {
                    $prefillPast['recipient_no'] = (int)$rcpt->recipient_no;
                }
            }




            // ▼ 暦年（row_no 1..10）… 受贈者に紐づく
            if ($prefillPast['recipient_no']) {
                $cal = PastGiftCalendarEntry::query()
                    ->where('data_id', $dataId)
                    ->where('recipient_no', $prefillPast['recipient_no'])
                    ->orderBy('row_no')
                    ->get();
                foreach ($cal as $r) {
                    $i = (int)$r->row_no;
                    if ($i >=1 && $i <= 10) {
                        $prefillPast['rekinen']['year'][$i]  = $r->gift_year;
                        $prefillPast['rekinen']['month'][$i] = $r->gift_month;
                        $prefillPast['rekinen']['day'][$i]   = $r->gift_day;
                        // 千円：'zoyo' と 'zouyo' を同値で保持
                        $prefillPast['rekinen']['zoyo'][$i]  = $r->amount_thousand;
                        $prefillPast['rekinen']['zouyo'][$i] = $r->amount_thousand;
                        $prefillPast['rekinen']['kojo'][$i]  = $r->tax_thousand;
                    }
                }
                
                if (!isset($prefillPast['rekinen']['zoyo'])) {
                    $prefillPast['rekinen']['zoyo'] = $prefillPast['rekinen']['zouyo'] ?? array_fill(1, 10, null);
                }
                if (!isset($prefillPast['rekinen']['zouyo'])) {
                    $prefillPast['rekinen']['zouyo'] = $prefillPast['rekinen']['zoyo'] ?? array_fill(1, 10, null);
                }

                $rekinenZoyo = $prefillPast['rekinen']['zoyo'] ?? [];
                $rekinenKojo = $prefillPast['rekinen']['kojo'] ?? [];
                

                // 合計（null 安全）— 'zoyo' と 'zouyo' を同値で用意
                $rekTotalZ = array_sum(array_map(fn($v)=> (int)($v??0), $rekinenZoyo));                
                $prefillPast['rekinen']['total']['zoyo']  = $rekTotalZ;
                $prefillPast['rekinen']['total']['zouyo'] = $rekTotalZ;

                $prefillPast['rekinen']['total']['kojo'] = array_sum(array_map(fn($v)=> (int)($v??0), $rekinenKojo));

                // ▼ 精算（row_no 1..10）
                $set = PastGiftSettlementEntry::query()
                    ->where('data_id', $dataId)
                    ->where('recipient_no', $prefillPast['recipient_no'])
                    ->orderBy('row_no')
                    ->get();
                foreach ($set as $r) {
                    $i = (int)$r->row_no;
                    if ($i >=1 && $i <= 10) {
                        $prefillPast['seisan']['year'][$i]  = $r->gift_year;
                        $prefillPast['seisan']['month'][$i] = $r->gift_month;
                        $prefillPast['seisan']['day'][$i]   = $r->gift_day;
                        $prefillPast['seisan']['zoyo'][$i]  = $r->amount_thousand;
                        $prefillPast['seisan']['zouyo'][$i] = $r->amount_thousand;
                        $prefillPast['seisan']['kojo'][$i]  = $r->tax_thousand;
                    }
                }

                if (!isset($prefillPast['seisan']['zoyo'])) {
                    $prefillPast['seisan']['zoyo'] = $prefillPast['seisan']['zouyo'] ?? array_fill(1, 10, null);
                }
                if (!isset($prefillPast['seisan']['zouyo'])) {
                    $prefillPast['seisan']['zouyo'] = $prefillPast['seisan']['zoyo'] ?? array_fill(1, 10, null);
                }

                $seisanZoyo = $prefillPast['seisan']['zoyo'] ?? [];
                $seisanKojo = $prefillPast['seisan']['kojo'] ?? [];

                $seiTotalZ = array_sum(array_map(fn($v)=> (int)($v??0), $seisanZoyo));

                $prefillPast['seisan']['total']['zoyo']  = $seiTotalZ;
                $prefillPast['seisan']['total']['zouyo'] = $seiTotalZ;

                $prefillPast['seisan']['total']['kojo'] = array_sum(array_map(fn($v)=> (int)($v??0), $seisanKojo));

            }



            // ▼ 提案書ヘッダ（1:1）
            $ph = \App\Models\ProposalHeader::query()->where('data_id', $dataId)->first();
            

            //お客様名の初期値用のデータを取得
            $currentCustomerName = trim((string) ($ph->customer_name ?? ''));

            //提案者名の初期値用のデータを取得
            $latestProposerName = $this->resolveLatestProposalProposerName($companyId);            
            
            if ($ph) {
                
                $currentProposerName = trim((string) ($ph->proposer_name ?? ''));                

                $header = [
                    'customer_name' => $currentCustomerName !== '' ? $currentCustomerName : $selectedCustomerName,
                    'title'         => $ph->title,
                    'year'          => $ph->doc_year,
                    'month'         => $ph->doc_month,
                    'day'           => $ph->doc_day,
                    'proposer_name' => $currentProposerName !== '' ? $currentProposerName : $latestProposerName,
                    'per'           => $ph->after_tax_yield_percent,
                    'property_110'  => $ph->property_total_thousand,
                    'cash_110'      => $ph->cash_total_thousand,
                ];


            } else {

                if ($selectedCustomerName !== null) {
                    $header['customer_name'] = $selectedCustomerName;
                }
                if ($latestProposerName !== null) {
                    $header['proposer_name'] = $latestProposerName;
                }

            }



            // ▼ 家族構成（1..10）
            $rows = \App\Models\ProposalFamilyMember::query()
                ->where('data_id', $dataId)
                ->orderBy('row_no')->get();

            foreach ($rows as $r) {
            
                $i = (int)$r->row_no;
            
                if ($i < 1 || $i > 10) continue;
            
                $family[$i] = [
                    'name'              => $r->name,
                    'gender'            => $r->gender,
                    'relationship_code' => $r->relationship_code,
                    'yousi'             => $r->adoption_note,
                    'souzokunin'        => match($r->heir_category){
                        0 => '被相続人', 1 => '法定相続人', 2 => '法定相続人以外', default => null
                    },
                    'civil_share_bunsi' => $r->civil_share_bunsi,  // 民法上の法定相続割合
                    'civil_share_bunbo' => $r->civil_share_bunbo,  // 民法上の法定相続割合
                    'bunsi'             => $r->share_numerator,
                    'bunbo'             => $r->share_denominator,
                    'twenty_percent_add'=> (int)$r->surcharge_twenty_percent,
                    'tokurei_zouyo'     => (int)$r->tokurei_zouyo,
                    'birth_year'        => $r->birth_year,
                    'birth_month'       => $r->birth_month,
                    'birth_day'         => $r->birth_day,
                    'age'               => $r->age,
                    'property'          => $r->property_thousand,
                    'cash'              => $r->cash_thousand,
                ];
            }
            


            // ▼ 家族番号1の氏名初期値
            //    - 既存保存値（proposal_family_members.row_no=1）があればそれを優先
            //    - 無ければ、お客様名を初期値として補完
            //    - 入力欄自体は Blade 側の通常 input のままなので、画面上で修正可能
            $defaultFamilyName1 = trim((string) ($header['customer_name'] ?? $selectedCustomerName ?? ''));
            if ($defaultFamilyName1 !== '') {
                if (!isset($family[1]) || !is_array($family[1])) {
                    $family[1] = [];
                }

                $currentFamilyName1 = trim((string) ($family[1]['name'] ?? ''));
                if ($currentFamilyName1 === '') {
                    $family[1]['name'] = $defaultFamilyName1;
                }
            }



            
         }
         

        // ▼ これからの贈与：ヘッダ（1:1）
        if ($fh = \App\Models\FutureGiftHeader::query()->where('data_id', $dataId)->first()) {
            $prefillFuture['header']['year']  = $fh->base_year;
            $prefillFuture['header']['month'] = $fh->base_month;
            $prefillFuture['header']['day']   = $fh->base_day;
        }
        // 直近更新の受贈者（将来プラン）を自動選択
        $latestPlan = \App\Models\FutureGiftPlanEntry::query()
            ->where('data_id', $dataId)->select('recipient_no')->orderByDesc('updated_at')->first();
        $prefillFuture['recipient_no'] = $latestPlan?->recipient_no;
        if (!$prefillFuture['recipient_no']) {
            $fr = \App\Models\FutureGiftRecipient::query()
                ->where('data_id', $dataId)->orderBy('recipient_no')->first();
            $prefillFuture['recipient_no'] = $fr?->recipient_no;
        }
        // プラン明細（row_no 1..20）
        if ($prefillFuture['recipient_no']) {
            $plans = \App\Models\FutureGiftPlanEntry::query()
                ->where('data_id', $dataId)
                ->where('recipient_no', $prefillFuture['recipient_no'])
                ->orderBy('row_no')->get();
            foreach ($plans as $r) {
                $i = (int)$r->row_no; if ($i < 1 || $i > 20) continue;
                $prefillFuture['plan']['gift_year'][$i]  = $r->gift_year;
                $prefillFuture['plan']['age'][$i]        = $r->age;
                $prefillFuture['plan']['cal_amount'][$i]      = $r->calendar_amount_thousand;
                $prefillFuture['plan']['cal_basic'][$i]       = $r->calendar_basic_deduction_thousand;
                $prefillFuture['plan']['cal_after_basic'][$i] = $r->calendar_after_basic_thousand;
                $prefillFuture['plan']['cal_tax'][$i]         = $r->calendar_special_tax_thousand;
                $prefillFuture['plan']['cal_cum'][$i]         = $r->calendar_add_cum_thousand;
                $prefillFuture['plan']['set_amount'][$i]      = $r->settlement_amount_thousand;
                $prefillFuture['plan']['set_basic110'][$i]    = $r->settlement_110k_basic_thousand;
                $prefillFuture['plan']['set_after_basic'][$i] = $r->settlement_after_basic_thousand;
                $prefillFuture['plan']['set_after_25m'][$i]   = $r->settlement_after_25m_thousand;
                $prefillFuture['plan']['set_tax20'][$i]       = $r->settlement_tax20_thousand;
                $prefillFuture['plan']['set_cum'][$i]         = $r->settlement_add_cum_thousand;
            }
        }
         

         // ★ 遺産分割(現時点)プレフィル
         $prefillInheritance = [
             'method_code' => null, // 0=法定, 9=手入力 など
             'members' => [         // recipient_no => 値（千円）
                 // 2..10 を使う
             ],
             'other_credit' => [    // recipient_no => その他税額控除（千円）
             ],
         ];


            // ▼ 遺産分割(現時点)：ヘッダ（1:1）
            if (class_exists(\App\Models\InheritanceDistributionHeader::class)) {
                if ($ih = \App\Models\InheritanceDistributionHeader::where('data_id', $dataId)->first()) {
                    $prefillInheritance['method_code'] = $ih->method_code;
                }
            }
            // ▼ 遺産分割(現時点)：明細（recipient_no=2..10）
            if (class_exists(\App\Models\InheritanceDistributionMember::class)) {
                $rows = \App\Models\InheritanceDistributionMember::where('data_id', $dataId)->get();
                foreach ($rows as $r) {
                    $no = (int)$r->recipient_no;
                    if ($no < 2 || $no > 10) continue;
                    $prefillInheritance['members'][$no] = [
                        'taxable_auto' => $r->taxable_auto_value_thousand,
                        'taxable_manu' => $r->taxable_manu_value_thousand,
                        // ★追加：金融資産/その他資産（テーブルにカラムが無くても null でOK）
                        'cash_share'   => $r->cash_share_value_thousand ?? null,
                        'other_share'  => $r->other_asset_share_value_thousand ?? null,
                        
                    ];
                    $prefillInheritance['other_credit'][$no] = $r->other_tax_credit_thousand;
                }
            }


        // ▼ PDF 作成ページの選択状態（セッションから復元：data_id 単位）
        $pdfSelectedPages = [];
        if ($dataId) {
            $pdfSelectedPages = (array) session()->get("zouyo.pdf_pages.{$dataId}", []);
            // 念のため整数化＆0〜10の範囲に正規化
            $pdfSelectedPages = array_values(array_unique(
                array_filter(
                    array_map('intval', $pdfSelectedPages),
                    fn (int $v) => $v >= 0 && $v <= 10
                )
            ));
        }

/*
Log::debug('PDF selected pages in makeInputContext', [
    'dataId' => $dataId,
    'pdfSelectedPages' => $pdfSelectedPages,
]);
*/


        return [
            'dataId' => $dataId,
            'bunriFlag' => $bunriFlag,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'savedInputs' => $savedInputs,
            'results' => [],
            'showResult' => false,
            // ▼ 画面プレフィル用
            'prefillHeader' => $header,
            'prefillFamily' => $family,
            // ★ kekka_kazeikakaku.blade でそのまま $family を参照できるように追加
            'family'        => $family,
            'prefillPast'   => $prefillPast, // ★ 追加
            'prefillFuture' => $prefillFuture, // ★ 追加
            'prefillInheritance' => $prefillInheritance, // ★ 追加：遺産分割
            // ★ PDF 作成タブの選択状態
            'pdfSelectedPages' => $pdfSelectedPages,
            

        ];
    }


    

    private function findDataForInput(Request $request, int $dataId): ?Data
    {
        // ★ ローカル/DEBUG は会社照合を緩和して安全に取得
        if (app()->isLocal() || config('app.debug')) {

            $id = $dataId ?: (int) (session('selected_data_id') ?? 0);
            if ($id <= 0) {
                return null;
            }
             
            return Data::with('guest')->find($id);
        }
        // 本番は通常のスコープ認可へ
        if ($request->user()) {
            return $this->resolveCompanyScopedDataOrFail($request);
        }
        return Data::find($dataId);
    }



    private function resolveLatestProposalProposerName(int $companyId): ?string
    {
        //提案者名の初期値用のデータを取得
        
        if ($companyId <= 0) {
            return null;
        }

        $name = DB::table('proposal_headers as ph')
            ->join('datas as d', 'd.id', '=', 'ph.data_id')
            ->where('d.company_id', $companyId)
            ->whereNotNull('ph.proposer_name')
            ->where('ph.proposer_name', '<>', '')
            ->orderByDesc('ph.updated_at')
            ->orderByDesc('ph.id')
            ->value('ph.proposer_name');

        $name = trim((string) ($name ?? ''));

        return $name === '' ? null : $name;
    }



    private function resolveSelectedGuestNameForHeader(?Data $data): ?string
    {
        //お客様名の初期値用のデータを取得

        if (!$data) {
            return null;
        }

        $name = trim((string) ($data->guest->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) Guest::query()
            ->where('company_id', (int) ($data->company_id ?? 0))
            ->where('group_id', (int) ($data->group_id ?? 0))
            ->orderBy('id')
            ->value('name'));

        return $name === '' ? null : $name;
    }

    // 既存：引数名が $req などになっているケースを $request に統一
    // 引数名を $request に統一
    public function save(Request $request)
     {


        /*
        \Log::info('[Zouyo][save] called', [
            'keys'    => array_keys($request->all()),
            'data_id' => $request->input('data_id'),
            'autosave'=> $request->boolean('autosave'),
            'active_tab' => $request->input('active_tab'),
        ]);
        */


        // ------------------------------------------------------
        // 親ファースト：必ず親 Data を認可付きで確定
        //$data = $this->resolveAuthorizedDataOrFail($request, 'update');



        // プロジェクトに AuthorizesData トレイトがある場合は resolveAuthorizedDataOrFail を使う
        // 親データの特定（親ファースト／data_id強制）
        if (method_exists($this, 'resolveAuthorizedDataOrFail')) {
            $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        } else {
            // フォールバック：hiddenの data_id から親を取得（未導入環境向け）
            $dataId = (int) ($request->input('data_id') ?? 0);
            if ($dataId <= 0) {
                // data_id が無い場合は明示的に例外にして 500 の原因を可視化
                abort(422, 'data_id is required.');
            }
            $data = \App\Models\Data::findOrFail($dataId);
        }


        /*
        \Log::info('[Zouyo][save] resolved data', [
            'data_id' => $data->id,
        ]);
        */



        $isAutosave = (bool)$request->boolean('autosave', false);
        $activeTab  = $request->input('active_tab'); // UI 復元用

        /**
         * 事前バリデーション（autosave 時の 422 原因を可視化）
         * - rows は配列
         * - recipient_no は **任意**（未指定なら後段で 1 始まりの連番を補完）
         * - 不要フィールドは許容しつつ、最低限の型だけ検査
         */

        $rows = $request->input('past_gift_recipients', []);


        // recipient_no は未指定可（未指定なら連番で補完する方針に変更）
        $validator = Validator::make(
            ['past_gift_recipients' => $rows],
            [
                'past_gift_recipients'               => 'array',
                'past_gift_recipients.*.recipient_no'=> 'nullable|integer|min:1',
            ],
            [
                'past_gift_recipients.*.recipient_no.integer'  => 'recipient_no は整数で指定してください。',
                'past_gift_recipients.*.recipient_no.min'      => 'recipient_no は 1 以上で指定してください。',
            ]
        );



        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            if ($isAutosave) {
                return response()->json([
                    'status'     => 'error',
                    'message'    => '入力値エラー（過年度の贈与受取人）。',
                    'errors'     => $errors,
                    'active_tab' => $activeTab,
                ], 422);
            }
            return back()->withErrors($errors)->withInput();
        }



        try {

            $rowErrors   = [];   // 行単位（past_gift_recipients）の失敗

            $blockErrors = [];   // どの保存ブロックで例外が出たか

            DB::transaction(function () use ($request, $data, $rows, &$rowErrors, &$blockErrors) {
                 

                
                // =============================
                // 既存の各保存処理（中略）
                // =============================

                    // ▼ 提案書ヘッダ＋家族構成の保存（ここでは data を再取得しない）

                    try {
                        $this->storeProposalHeaderAndFamily($data->id, $request);
                    } catch (\Throwable $ex) {
                        $blockErrors[] = [
                            'block'     => 'storeProposalHeaderAndFamily',
                            'exception' => get_class($ex),
                            'message'   => $ex->getMessage(),
                        ];
                        throw $ex; // 以降も失敗が連鎖するためロールバック
                    }
             
            
                    // ▼ 過年度の贈与（タブ input03 由来）も保存
                    //    ★ 未来タブの autosave でも、過去タブ系の入力が1つでもあれば保存を実行する
                    try {
                        $isFutureOnlyAutosave = $request->boolean('autosave')
                            && $request->has('future_recipient_no')
                            && !$this->hasAnyPastInputs($request);
                        if (!$isFutureOnlyAutosave) {
                            $this->storePastGifts($data->id, $request);
                        }
                    } catch (\Throwable $ex) {
                        $blockErrors[] = [
                            'block'     => 'storePastGifts',
                            'exception' => get_class($ex),
                            'message'   => $ex->getMessage(),
                        ];
                        throw $ex;
                    }




                    // ▼ これからの贈与（タブ input04）
                    try {
                        // 受贈者変更直後のスナップショット保存(行データなし)なら、行保存はスキップ
                        $autosaveOnlyRecipient = $request->boolean('autosave')
                            && $request->has('future_recipient_no')
                            && !$this->hasAnyPastInputs($request)
                            && !$this->requestHasAnyFutureRows($request);

                        // autosave のうち「受贈者切替直後のスナップショット保存（行データなし）」は行保存をスキップ
                        if (!$autosaveOnlyRecipient) {
                            $this->storeFutureGifts($data->id, $request);
                        }
                    } catch (\Throwable $ex) {
                        $blockErrors[] = [
                            'block'     => 'storeFutureGifts',
                            'exception' => get_class($ex),
                            'message'   => $ex->getMessage(),
                        ];
                        throw $ex;
                    }
            
                    // ▼ 遺産分割（タブ input05 由来）も保存（入力があれば）
                    try {

                        // ★ 修正：
                        //   - 「手入力」モードのときだけ、遺産分割(現時点)の課税価格を DB に保存する。
                        //   - 「法定相続割合」モードのときは、DB に保存済みの手入力値を壊さないため
                        //     storeInheritanceDistribution を呼ばない。
                        //
                        //   これにより、
                        //   1) 手入力モードで「保存」または「計算開始」したときにだけ手入力値が保存される
                        //   2) その後「法定相続割合」で「保存」や「計算開始」をしても、
                        //      手入力時の値は上書きされず残る
                        if ((string)$request->input('input_mode') === 'manual') {
                            $this->storeInheritanceDistribution($data->id, $request);
                        }



                    } catch (\Throwable $ex) {
                        $blockErrors[] = [
                            'block'     => 'storeInheritanceDistribution',
                            'exception' => get_class($ex),
                            'message'   => $ex->getMessage(),
                        ];
                        throw $ex;
                    }

                // =============================
                // 過年度の贈与受取人: past_gift_recipients
                // UNIQUE(data_id, recipient_no) 準拠の UPSERT（updateOrCreate）
                // =============================
                if (is_array($rows)) {


                    // 実在カラムのみを許可（Unknown column対策）
                    $columns = Schema::getColumnListing('past_gift_recipients');
                    $writable = array_flip(array_diff($columns, ['id','data_id','recipient_no','created_at','updated_at']));


                    foreach ($rows as $i => $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        // 未指定なら 1 始まりの連番で補完
                        $recipientNo = (int)($row['recipient_no'] ?? 0);
                        if ($recipientNo <= 0) {
                            $recipientNo = $i + 1;
                        }
 
                        $key = [
                            'data_id'      => $data->id,      // 親ファーストで強制付与
                            'recipient_no' => $recipientNo,   // UNIQUE キー
                        ];

                        // 値の整形：id/data_id/recipient_no/_delete 除去 + 実在カラムのみに制限
                        $values = Arr::except($row, ['id', 'data_id', 'recipient_no', '_delete']);
                        $values = array_intersect_key($values, $writable);
 

                        // _delete 指定時は削除（UI 仕様に応じて不要ならこの分岐を外してください）
                        if (!empty($row['_delete'])) {
                            PastGiftRecipient::where($key)->delete();
                             continue;
                        }


 
                        try {
                            /*
                            // デバッグログ（必要なら残す/無効化可）
                            \Log::info('UPSERT past_gift_recipient', [
                                'key'    => $key,
                                'values' => $values,
                            ]);
                            */
                            // data_id を $guarded にしているため、create 分岐では unguarded で実行する
                            PastGiftRecipient::unguarded(function () use ($key, $values) {
                                PastGiftRecipient::updateOrCreate($key, $values);
                            });

                        } catch (\Throwable $ex) {
                            $rowErrors[] = [
                                'row_index'     => $i,
                                'recipient_no'  => $recipientNo,
                                'exception'     => get_class($ex),
                                'message'       => $ex->getMessage(),
                                'errorInfo'     => ($ex instanceof QueryException) ? ($ex->errorInfo ?? null) : null,
                                // 参考用に送信行のうち実際に書き込もうとしたカラムだけ返す
                                'attempt_values'=> $values,
                            ];
                            
                            throw $ex; // 1行でも失敗したらロールバックし原因を返す

                        }


                     }
                 }
             });
             
            $this->touchDataUpdatedAt((int) $data->id);

              


            if ($isAutosave) {
                // autosave 用：200 で軽量レスポンス
                return response()->json([
                    'status'     => 'ok',
                    'active_tab' => $activeTab,
                ]);
            }

            // 通常保存：画面復帰（アクティブタブ維持）
            /*
            return back()
                ->with('success', '保存しました')
                ->with('active_tab', $activeTab);
            */
            return back()
                ->with('active_tab', $activeTab);
                
                
        } catch (\Throwable $e) {
            /*
            \Log::error('ZouyoController@save failed: '.$e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            */

            if ($isAutosave) {
                // autosave では 500 を避けて 422 で軽量エラー
                $payload = [
                    'status'     => 'error',
                    'message'    => '保存時にエラーが発生しました（受取人）。',
                    'active_tab' => $activeTab,
                ];
                // 開発環境 or APP_DEBUG=true のときは詳細も返す
                if (config('app.debug')) {
                    $payload['debug'] = [
                        'exception' => get_class($e),
                        'message'   => $e->getMessage(),
                        'file'      => $e->getFile(),
                        'line'      => $e->getLine(),
                    ];
                    // クエリ例外なら SQLSTATE/エラーコードも付与
                    if ($e instanceof QueryException) {
                        $payload['debug']['sqlstate'] = $e->getCode();
                        $payload['debug']['errorInfo']= $e->errorInfo ?? null;
                    }
                }
                    
                // ブロック単位／行単位の情報は常に返す（APP_DEBUG 無関係）
                if (!empty($blockErrors)) {
                    $payload['block_errors'] = $blockErrors;
                }
                if (!empty($rowErrors)) {
                    $payload['row_errors'] = $rowErrors;
                }
 
                // ← ここで JSON を返す（ブレース崩れ修正）
                return response()->json($payload, 422);
            }
                    

            return back()
                ->withErrors('保存に失敗しました。しばらくしてから再度お試しください。')
                ->withInput();
        }
        
        
    }
    

    /**
     * 提案書ヘッダ＋家族構成保存
     * $dataId が未指定/0/負値のときは 1 を既定値として使用
     */
    private function storeProposalHeaderAndFamily(?int $dataId, Request $req): void
     {

        // --------------------------------------------------------
        // data_id の保証処理        
        // --------------------------------------------------------
         if (empty($dataId) || $dataId <= 0) {
            $dataId = (int) ($req->input('data_id') ?? $req->query('data_id') ?? session('selected_data_id') ?? 0);
         }
        if ($dataId > 0) {
            session(['selected_data_id' => $dataId]);
        }



        /*
        // 念のため整数化してログ出力
        $dataId = (int) $dataId;
        \Log::info('[Zouyo][storeFamily] START', [
            'dataId' => $dataId,
            'keys'   => array_keys($req->all()),
        ]);
        */

        // ここまでで data_id が必ず存在



        // 1) ヘッダ（1:1）
        //    ★修正ポイント：
        //      - header_* 系の入力が 1つも送られてこないリクエストでは
        //        既存の ProposalHeader を上書きしない（null クリア防止）
        $hasHeaderInputs = $req->hasAny([
            'header_customer_name',
            'header_title',
            'header_year',
            'header_month',
            'header_day',
            'header_proposer_name',
            'per',
            'property.110',
            'cash.110',
        ]);

        if ($hasHeaderInputs) {
            
            /*
            \Log::info('[Zouyo][storeFamily] update header', [
                'dataId' => $dataId,
                'header_customer_name' => $req->input('header_customer_name'),
                'header_title'         => $req->input('header_title'),
                'header_year'          => $req->input('header_year'),
                'header_month'         => $req->input('header_month'),
                'header_day'           => $req->input('header_day'),
                'per'                  => $req->input('per'),
            ]);
            */

            \App\Models\ProposalHeader::updateOrCreate(
                ['data_id' => $dataId],
                [
                    'customer_name' => $this->strOrNull($req->input('header_customer_name')),
                    'title'         => $this->strOrNull($req->input('header_title')),
                    'doc_year'      => $this->intOrNull($req->input('header_year')),
                    'doc_month'     => $this->intOrNull($req->input('header_month')),
                    'doc_day'       => $this->intOrNull($req->input('header_day')),
                    'proposer_name' => $this->strOrNull($req->input('header_proposer_name')),
                    // 参考：利回り/合計欄があれば保存（存在しないならnull）
                    'after_tax_yield_percent' => $this->percentOrNull($req->input('per')),
                    'property_total_thousand'  => $this->signedThousandOrNull($req->input('property.110')),
                    'cash_total_thousand'      => $this->toThousand($req->input('cash.110')),
                ]
            );
        } else {
            
            /*
            \Log::info('[Zouyo][storeFamily] skip header update (no header_* inputs)', [
                'dataId' => $dataId,
            ]);
            
            */
        }

        // 2) 家族構成（1..10行）
        // --------------------------------------------------------
        // 家族構成の name 系入力が今回の request に含まれているか
        // - past/future タブ保存時は name.* が送られないことがある
        // - その場合に purge を実行すると、全 recipient が消えてしまう
        // --------------------------------------------------------
        $familyRowsWereSubmitted = false;
        for ($n = 1; $n <= 10; $n++) {
            if ($req->exists("name.$n")) {
                $familyRowsWereSubmitted = true;
                break;
            }
        }
        $activeRecipientNos = [];

        for ($i = 1; $i <= 10; $i++) {

            // --------------------------------------------------------
            // 行が「今回のリクエストで送信対象だったか」を判定
            // - filled() では空欄送信を拾えないため exists() を使う
            // - Blade 側で氏名空欄時も submit 前に disabled を解除して空欄送信する前提
            // --------------------------------------------------------
            $rowKeys = [
                'name',
                'gender',
                'relationship',
                'yousi',
                'souzokunin',
                'civil_share_bunsi',
                'civil_share_bunbo',
                'bunsi',
                'bunbo',
                'twenty_percent_add',
                'tokurei_zouyo',
                'birth_year',
                'birth_month',
                'birth_day',
                'age',
                'cash',
                'other_asset',
                'property',
            ];

            $rowWasSubmitted = false;
            foreach ($rowKeys as $key) {
                if ($req->exists("{$key}.{$i}")) {
                    $rowWasSubmitted = true;
                    break;
                }
            }

            // そもそも今回の保存対象に入っていない行は触らない
            if (! $rowWasSubmitted) {
                continue;
            }


            // --------------------------------------------------------
            // ★ 金融資産(cash) + その他資産(other_asset) から合計(property)をサーバ側でも補完
            //   - JS未発火で property が空でも確実に保存されるようにする
            // --------------------------------------------------------
            $cashK     = $this->toThousand($req->input("cash.$i"));                // 千円
            $otherK    = $this->signedThousandOrNull($req->input("other_asset.$i"));// 千円（負数許可）
            $propertyK = $this->signedThousandOrNull($req->input("property.$i"));   // 千円（負数許可）

            if ($propertyK === null && ($cashK !== null || $otherK !== null)) {
                $propertyK = (int)($cashK ?? 0) + (int)($otherK ?? 0);
            }

            

            // --------------------------------------------------------
            // 氏名空欄＋他項目も実質空なら、既存行を削除して「空欄状態」を保存する
            // - これが無いと、前回保存済みの値がそのまま残って復元される
            // --------------------------------------------------------
            $nameVal          = $this->strOrNull($req->input("name.$i"));
            $genderVal        = $this->strOrNull($req->input("gender.$i"));
            $relationshipVal  = $this->intOrNull($req->input("relationship.$i"));
            $yousiVal         = $this->strOrNull($req->input("yousi.$i"));
            $souzokuninVal    = $this->strOrNull($req->input("souzokunin.$i"));
            $civilBunsiVal    = $this->strOrNull($req->input("civil_share_bunsi.$i"));
            $civilBunboVal    = $this->strOrNull($req->input("civil_share_bunbo.$i"));
            $bunsiVal         = $this->intOrNull($req->input("bunsi.$i"));
            $bunboVal         = $this->intOrNull($req->input("bunbo.$i"));
            $birthYearVal     = $this->intOrNull($req->input("birth_year.$i"));
            $birthMonthVal    = $this->intOrNull($req->input("birth_month.$i"));
            $birthDayVal      = $this->intOrNull($req->input("birth_day.$i"));
            $ageVal           = $this->intOrNull($req->input("age.$i"));
            $twentyVal        = (bool) $req->boolean("twenty_percent_add.$i");
            $tokureiVal       = (bool) $req->boolean("tokurei_zouyo.$i");

            $isCompletelyBlank =
                $nameVal === null &&
                $genderVal === null &&
                $relationshipVal === null &&
                $yousiVal === null &&
                $souzokuninVal === null &&
                $civilBunsiVal === null &&
                $civilBunboVal === null &&
                $bunsiVal === null &&
                $bunboVal === null &&
                $birthYearVal === null &&
                $birthMonthVal === null &&
                $birthDayVal === null &&
                $ageVal === null &&
                $cashK === null &&
                $otherK === null &&
                $propertyK === null &&
                $twentyVal === false &&
                $tokureiVal === false;

            if ($isCompletelyBlank) {
                \App\Models\ProposalFamilyMember::where('data_id', (int) $dataId)
                    ->where('row_no', (int) $i)
                    ->delete();
                    
                continue;
            }

            

            // property が未送信/空のときは cash+other で補完（どちらか入力がある場合）
            if ($propertyK === null && ($cashK !== null || $otherK !== null)) {
                $propertyK = (int)($cashK ?? 0) + (int)($otherK ?? 0);
            }

            // ★ cash が空欄("") の場合でも other_asset/property が入っていれば cash=0 として保存する
            //   （Blade 側で other_asset を property-cash で復元するため）
            if ($cashK === null && ($otherK !== null || $propertyK !== null)) {
                $cashK = 0;
            }
            
            // ★ 2割加算チェックを Blade と同じロジックで判定する
            //    - form送信時の値: "1"/"on"/"true"/"yes" を true とみなす
            $twRaw = $req->input("twenty_percent_add.$i");
            $surchargeTwenty = in_array((string)$twRaw, ['1', 'on', 'true', 'yes'], true);




            // ★ data_id が guarded な環境でも確実に作成/更新できるよう firstOrNew で保存
            \App\Models\ProposalFamilyMember::unguarded(function () use ($dataId, $i, $req, $surchargeTwenty, $cashK, $propertyK) {
                $row = \App\Models\ProposalFamilyMember::firstOrNew([
                    'data_id' => (int)$dataId,
                    'row_no'  => (int)$i,
                ]);
                if (!$row->exists) {
                    $row->data_id = (int)$dataId;
                    $row->row_no  = (int)$i;
                }

                $row->name              = $this->strOrNull($req->input("name.$i"));
                $row->gender            = $this->strOrNull($req->input("gender.$i"));
                $row->relationship_code = $this->intOrNull($req->input("relationship.$i"));
                $row->adoption_note     = $this->strOrNull($req->input("yousi.$i"));
                $row->heir_category     = $this->mapHeirCategory($this->strOrNull($req->input("souzokunin.$i")));

                $row->civil_share_bunsi = $this->strOrNull($req->input("civil_share_bunsi.$i"));
                $row->civil_share_bunbo = $this->strOrNull($req->input("civil_share_bunbo.$i"));
                $row->share_numerator   = $this->intOrNull($req->input("bunsi.$i"));
                $row->share_denominator = $this->intOrNull($req->input("bunbo.$i"));

                $row->surcharge_twenty_percent = $surchargeTwenty ? 1 : 0;
                $row->tokurei_zouyo            = (bool)$req->boolean("tokurei_zouyo.$i");

                $row->birth_year  = $this->intOrNull($req->input("birth_year.$i"));
                $row->birth_month = $this->intOrNull($req->input("birth_month.$i"));
                $row->birth_day   = $this->intOrNull($req->input("birth_day.$i"));
                $row->age         = $this->intOrNull($req->input("age.$i"));

                // ★ ここが本題：other_asset 由来も含めた合計を確実に保存
                $row->cash_thousand     = $cashK;
                $row->property_thousand = $propertyK;

                $row->save();
            });
            

            // row_no 2..10 で、氏名が残っている行は「有効な recipient_no」とみなす
            if ($i >= 2 && $i <= 10 && $nameVal !== null) {
                $activeRecipientNos[] = (int) $i;
            }            
            
        }
        

        // --------------------------------------------------------
        // 家族構成が実際に送信されたときだけ purge を実行する
        // - past/future 保存で name.* が来ていない request では実行しない
        // - これにより「氏名があるのに全員分が削除される」事故を防ぐ
        // --------------------------------------------------------
        if ($familyRowsWereSubmitted) {
            $this->purgeGiftDataForMissingRecipients((int) $dataId, $activeRecipientNos);
        }


    }

    private function intOrNull($v): ?int
    {
        if ($v === null) return null;
        // 全角→半角、カンマ/空白/記号除去（年・月・日など整数想定フィールド）
        $s = preg_replace('/[^\d\-]/u', '', mb_convert_kana((string)$v, 'n', 'UTF-8'));
        if ($s === '' || $s === '-' || $s === '+') {
            return null;
        }
        return (int)$s;
    }

    private function strOrNull($v): ?string
    {
        if ($v === null) return null;
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
    private function toThousand($v): ?int
    {
        if ($v === null || $v === '') return null;
        return (int)preg_replace('/[^\d]/', '', (string)$v);
    }
    

    private function signedThousandOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;

        $s = preg_replace('/[^\d\-]/u', '', mb_convert_kana((string)$v, 'n', 'UTF-8'));
        if ($s === '' || $s === '-' || $s === '+') {
            return null;
        }

        $negative = str_starts_with($s, '-');
        $digits   = preg_replace('/[^\d]/', '', $s);
        if ($digits === '') return null;

        return $negative ? -((int)$digits) : (int)$digits;
    }

    private function percentOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        $num = (float)preg_replace('/[^\d\.]/', '', (string)$v);
        return round($num, 1);
    }
    private function mapHeirCategory(?string $label): ?int
    {
        if ($label === null) return null;
        // 0=被相続人, 1=法定相続人, 2=法定相続人以外
        return match($label){
            '被相続人'       => 0,
            '法定相続人'     => 1,
            '法定相続人以外' => 2,
            default          => null,
        };
    }



    /**
     * 家族構成の row_no と対応する受贈者データを削除する
     * 対象:
     * - 過年度の贈与受取人
     * - 過年度の暦年贈与
     * - 過年度の精算課税贈与
     * - これからの贈与受贈者
     * - これからの贈与プラン明細
     *
     * 前提:
     * - ProposalFamilyMember.row_no (2..10)
     * - Past/Future の recipient_no (2..10)
     *   が同じ番号対応になっている
     */
    private function deleteGiftDataByRecipientNo(int $dataId, int $recipientNo): void
    {
        if ($dataId <= 0 || $recipientNo < 2 || $recipientNo > 10) {
            return;
        }

        // 過年度
        PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->delete();

        PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->delete();

        PastGiftRecipient::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->delete();

        // これからの贈与
        if (class_exists(\App\Models\FutureGiftPlanEntry::class)) {
            \App\Models\FutureGiftPlanEntry::query()
                ->where('data_id', $dataId)
                ->where('recipient_no', $recipientNo)
                ->delete();
        }

        if (class_exists(\App\Models\FutureGiftRecipient::class)) {
            \App\Models\FutureGiftRecipient::query()
                ->where('data_id', $dataId)
                ->where('recipient_no', $recipientNo)
                ->delete();
        }
    }        

    /**
     * 現在の家族構成に存在しない recipient_no の贈与データを一括削除する
     *
     * @param int   $dataId
     * @param array $activeRecipientNos 家族構成で氏名が残っている No 一覧（2..10）
     */
    private function purgeGiftDataForMissingRecipients(int $dataId, array $activeRecipientNos): void
    {
        if ($dataId <= 0) {
            return;
        }

        $activeRecipientNos = array_values(array_unique(array_map('intval', $activeRecipientNos)));
        $allRecipientNos = range(2, 10);
        $deleteRecipientNos = array_values(array_diff($allRecipientNos, $activeRecipientNos));

        if (empty($deleteRecipientNos)) {
            return;
        }

        PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $deleteRecipientNos)
            ->delete();

        PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $deleteRecipientNos)
            ->delete();

        PastGiftRecipient::query()
            ->where('data_id', $dataId)
            ->whereIn('recipient_no', $deleteRecipientNos)
            ->delete();

        if (class_exists(\App\Models\FutureGiftPlanEntry::class)) {
            \App\Models\FutureGiftPlanEntry::query()
                ->where('data_id', $dataId)
                ->whereIn('recipient_no', $deleteRecipientNos)
                ->delete();
        }

        if (class_exists(\App\Models\FutureGiftRecipient::class)) {
            \App\Models\FutureGiftRecipient::query()
                ->where('data_id', $dataId)
                ->whereIn('recipient_no', $deleteRecipientNos)
                ->delete();
        }

    }





    /**
     * 過年度の贈与（ヘッダ/受贈者/暦年/精算）を保存
     */
    private function storePastGifts(int $dataId, Request $req): void
    {
        // ★ 既定値 1 に正規化
        $dataId = ($dataId !== null && $dataId > 0) ? $dataId : 1;

        // ★ ガード：未来タブ“専用”の autosave（future_recipient_no あり かつ 過去タブ入力なし）のときのみスキップ
        if ($req->boolean('autosave') && $req->has('future_recipient_no') && !$this->hasAnyPastInputs($req)) {
            return;
        }

        // 1) PastGiftInput を保持するが、inherit_* の更新は不要になったため削除済み

        // 2) 受贈者（No 2..9）：単一選択をスナップショット保存
        $recipientNo = $this->intOrNull($req->input('recipient_no'));
        if ($recipientNo !== null) {
            $recipientNo = (int) $recipientNo;

            PastGiftRecipient::unguarded(function () use ($dataId, $recipientNo) {
                PastGiftRecipient::updateOrCreate(
                    ['data_id' => (int) $dataId, 'recipient_no' => $recipientNo],
                    [
                        'recipient_name' => null,
                    ]
                );
            });
        }

        if ($recipientNo === null) {
            return;
        }

        // 3) 暦年（row_no = 1..10）
        for ($i = 1; $i <= 10; $i++) {
            $existing = \App\Models\PastGiftCalendarEntry::where([
                'data_id' => (int) $dataId,
                'recipient_no' => (int) $recipientNo,
                'row_no' => (int) $i,
            ])->first();

            $submittedYear  = $req->exists("rekinen_year.$i");
            $submittedMonth = $req->exists("rekinen_month.$i");
            $submittedDay   = $req->exists("rekinen_day.$i");
            $submittedZoyo  = $req->exists("rekinen_zoyo.$i");
            $submittedKojo  = $req->exists("rekinen_kojo.$i");

            $hasAnySubmitted =
                $submittedYear ||
                $submittedMonth ||
                $submittedDay ||
                $submittedZoyo ||
                $submittedKojo;

            if (!$hasAnySubmitted && !$existing) {
                continue;
            }

            $next = [
                'gift_year'       => $submittedYear
                    ? $this->intOrNull($req->input("rekinen_year.$i"))
                    : ($existing->gift_year ?? null),
                'gift_month'      => $submittedMonth
                    ? $this->intOrNull($req->input("rekinen_month.$i"))
                    : ($existing->gift_month ?? null),
                'gift_day'        => $submittedDay
                    ? $this->intOrNull($req->input("rekinen_day.$i"))
                    : ($existing->gift_day ?? null),
                'amount_thousand' => $submittedZoyo
                    ? $this->toThousand($req->input("rekinen_zoyo.$i"))
                    : ($existing->amount_thousand ?? null),
                'tax_thousand'    => $submittedKojo
                    ? $this->toThousand($req->input("rekinen_kojo.$i"))
                    : ($existing->tax_thousand ?? null),
            ];

            $rowIsBlank =
                $next['gift_year'] === null &&
                $next['gift_month'] === null &&
                $next['gift_day'] === null &&
                $next['amount_thousand'] === null &&
                $next['tax_thousand'] === null;

            if ($rowIsBlank) {
                if ($existing) {
                    $existing->delete();
                }
                continue;
            }

            \App\Models\PastGiftCalendarEntry::unguarded(function () use ($dataId, $recipientNo, $i, $next) {
                \App\Models\PastGiftCalendarEntry::updateOrCreate(
                    ['data_id' => (int) $dataId, 'recipient_no' => (int) $recipientNo, 'row_no' => (int) $i],
                    $next
                );
            });
        }

        // 4) 精算（row_no = 1..10）
        for ($i = 1; $i <= 10; $i++) {
            $existing = \App\Models\PastGiftSettlementEntry::where([
                'data_id' => (int) $dataId,
                'recipient_no' => (int) $recipientNo,
                'row_no' => (int) $i,
            ])->first();

            $submittedYear  = $req->exists("seisan_year.$i");
            $submittedMonth = $req->exists("seisan_month.$i");
            $submittedDay   = $req->exists("seisan_day.$i");
            $submittedZoyo  = $req->exists("seisan_zoyo.$i");
            $submittedKojo  = $req->exists("seisan_kojo.$i");

            $hasAnySubmitted =
                $submittedYear ||
                $submittedMonth ||
                $submittedDay ||
                $submittedZoyo ||
                $submittedKojo;

            if (!$hasAnySubmitted && !$existing) {
                continue;
            }

            $next = [
                'gift_year'       => $submittedYear
                    ? $this->intOrNull($req->input("seisan_year.$i"))
                    : ($existing->gift_year ?? null),
                'gift_month'      => $submittedMonth
                    ? $this->intOrNull($req->input("seisan_month.$i"))
                    : ($existing->gift_month ?? null),
                'gift_day'        => $submittedDay
                    ? $this->intOrNull($req->input("seisan_day.$i"))
                    : ($existing->gift_day ?? null),
                'amount_thousand' => $submittedZoyo
                    ? $this->toThousand($req->input("seisan_zoyo.$i"))
                    : ($existing->amount_thousand ?? null),
                'tax_thousand'    => $submittedKojo
                    ? $this->toThousand($req->input("seisan_kojo.$i"))
                    : ($existing->tax_thousand ?? null),
            ];

            $rowIsBlank =
                $next['gift_year'] === null &&
                $next['gift_month'] === null &&
                $next['gift_day'] === null &&
                $next['amount_thousand'] === null &&
                $next['tax_thousand'] === null;

            if ($rowIsBlank) {
                if ($existing) {
                    $existing->delete();
                }
                continue;
            }

            \App\Models\PastGiftSettlementEntry::unguarded(function () use ($dataId, $recipientNo, $i, $next) {
                \App\Models\PastGiftSettlementEntry::updateOrCreate(
                    ['data_id' => (int) $dataId, 'recipient_no' => (int) $recipientNo, 'row_no' => (int) $i],
                    $next
                );
            });
        }
    }



    /**
     * 受贈者選択時に「過年度の贈与（暦年/精算）」を返すAJAX
     * GET /zouyo/past/fetch?data_id=1&recipient_no=2
     * レスポンス: { inherit:{year,month,day}, rekinen:{year[],month[],day[],zoyo[],kojo[]}, seisan:{...} }
     */
    public function fetchPastGifts(Request $request)
    {



        // Extract the parameters (you might get them from $request or define them directly)
        $dataId = $request->input('data_id');
        $recipientNo = $request->input('future_recipient_no');
        
        // Construct the fetch URL and any parameters needed for the request
        $fetchUrl = route('zouyo.future.fetch', ['data_id' => $dataId, 'future_recipient_no' => $recipientNo]);
        $parameters = [
            'data_id' => $dataId,
            'future_recipient_no' => $recipientNo
        ];

        /*
        // Log the fetch URL and parameters
        Log::info('FETCH-FUTURE URL', ['url' => $fetchUrl, 'parameters' => $parameters]);
        */



        // 認可  親ファースト（view権限）
        $data = $this->resolveAuthorizedDataOrFail($request, 'view');
        // future_recipient_no / recipient_no の両方を受理（future 側からの流用にも対応）
        $recipientNo = (int) ($request->query('recipient_no', $request->query('future_recipient_no', 0)));
        abort_if($recipientNo <= 0, 422, 'recipient_no is required');



        // 1) 相続開始（ヘッダ）
        $inherit = ['year' => null, 'month' => null, 'day' => null];
        if ($pg = \App\Models\PastGiftInput::query()->where('data_id', $data->id)->first()) {
            $inherit['year']  = $pg->inherit_year;
            $inherit['month'] = $pg->inherit_month;
            $inherit['day']   = $pg->inherit_day;
        }

        // 2) 暦年（row_no 1..10）
        $rekYear  = $rekMonth = $rekDay = $rekZoyo = $rekKojo = array_fill(1, 10, null);
        $cal = \App\Models\PastGiftCalendarEntry::query()
            ->where('data_id', $data->id)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->get();
        foreach ($cal as $r) {
            $i = (int) $r->row_no;
            if ($i < 1 || $i > 10) continue;
            $rekYear[$i]  = $r->gift_year;
            $rekMonth[$i] = $r->gift_month;
            $rekDay[$i]   = $r->gift_day;
            $rekZoyo[$i]  = $r->amount_thousand;
            $rekKojo[$i]  = $r->tax_thousand;
        }

        // 3) 精算（row_no 1..10）
        $seiYear  = $seiMonth = $seiDay = $seiZoyo = $seiKojo = array_fill(1, 10, null);
        $set = \App\Models\PastGiftSettlementEntry::query()
            ->where('data_id', $data->id)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->get();
        foreach ($set as $r) {
            $i = (int) $r->row_no;
            if ($i < 1 || $i > 10) continue;
            $seiYear[$i]  = $r->gift_year;
            $seiMonth[$i] = $r->gift_month;
            $seiDay[$i]   = $r->gift_day;
            $seiZoyo[$i]  = $r->amount_thousand;
            $seiKojo[$i]  = $r->tax_thousand;
        }

        // ★ 'zoyo' と 'zouyo' を同値で返す（前方互換）
        return response()->json([
            'status'   => 'ok',
            'data_id'  => $data->id,
            'recipient_no' => $recipientNo,
            'inherit'  => $inherit,
            'rekinen'  => [
                'year'  => $rekYear,
                'month' => $rekMonth,
                'day'   => $rekDay,
                'zoyo'  => $rekZoyo,
                'zouyo' => $rekZoyo,
                'kojo'  => $rekKojo,
                'total' => [
                    'zoyo'  => array_sum(array_map(fn($v)=> (int)($v??0), $rekZoyo)),
                    'zouyo' => array_sum(array_map(fn($v)=> (int)($v??0), $rekZoyo)),
                    'kojo'  => array_sum(array_map(fn($v)=> (int)($v??0), $rekKojo)),
                ],
            ],
            'seisan'   => [
                'year'  => $seiYear,
                'month' => $seiMonth,
                'day'   => $seiDay,
                'zoyo'  => $seiZoyo,
                'zouyo' => $seiZoyo,
                'kojo'  => $seiKojo,
                'total' => [
                    'zoyo'  => array_sum(array_map(fn($v)=> (int)($v??0), $seiZoyo)),
                    'zouyo' => array_sum(array_map(fn($v)=> (int)($v??0), $seiZoyo)),
                    'kojo'  => array_sum(array_map(fn($v)=> (int)($v??0), $seiKojo)),
                ],
            ],
        ]);



    }


    /**
     * これからの贈与（ヘッダ/受贈者/プラン明細20行）を保存
     */
    private function storeFutureGifts(int $dataId, Request $request): array
    
     {
 
         // ★ 既定値 1 に正規化
         $dataId = ($dataId !== null && $dataId > 0) ? $dataId : 1;
 
        // ★ 受贈者番号を先に確定（以降のブロックで未定義にならないように）
        $recipientNo = $this->intOrNull($request->input('future_recipient_no'));

        // サマリ用カウンタ
        $headerTouched    = false;
        $recipientTouched = false;
        $planRowsSaved    = 0;

        

        // 何も入力が無ければスキップ
        $hasAny =
             $request->filled('future_base_year') ||
             $request->filled('future_base_month') ||
             $request->filled('future_base_day') ||
            ($recipientNo !== null) ||
             !empty($request->input('cal_amount', [])) ||
             !empty($request->input('set_amount', []));
        if (!$hasAny) return ['header'=>false, 'recipient'=>false, 'plan_rows'=>0];
 
         // 1) ヘッダ（1:1）
        \App\Models\FutureGiftHeader::unguarded(function () use ($dataId, $request) {
             \App\Models\FutureGiftHeader::updateOrCreate(
                 ['data_id' => (int)$dataId],
                 [
                     'base_year'  => $this->intOrNull($request->input('future_base_year')),
                     'base_month' => $this->intOrNull($request->input('future_base_month')),
                     'base_day'   => $this->intOrNull($request->input('future_base_day')),
                     // 贈与者名スナップショット（任意）
                     'donor_name' => $this->strOrNull($request->input('donor_name') ?? $request->input('customer_name')),
                 ]
             );
         });
 
         if ($request->hasAny(['future_base_year','future_base_month','future_base_day'])) {
             $headerTouched = true;
         }

 
 
 
         // 2) 受贈者（No 2..9）
         if ($recipientNo !== null) {
            \App\Models\FutureGiftRecipient::unguarded(function () use ($dataId, $recipientNo, $request) {
                 \App\Models\FutureGiftRecipient::updateOrCreate(
                     ['data_id' => (int)$dataId, 'recipient_no' => (int)$recipientNo],
                     ['recipient_name' => $this->strOrNull($request->input('future_recipient_name'))]
                 );
             });

             $recipientTouched = true;

         }

        if ($recipientNo !== null) {
            
            /*
            // ====== ★ デバッグ: この時点で届いているキーを必ず把握（set_cum が来ていないかの切り分け） ======
            Log::debug('FG[store] 2025_11_13_A request snapshot', [
                'keys'          => array_keys($request->all() ?? []),
                'has_set_cum'   => $request->has('set_cum'),
                'has_set_amt'   => $request->has('set_amount'),
                'has_cal_cum'   => $request->has('cal_cum'),
                'recipient_no'  => $recipientNo,
            ]);
            // set_cum 全体（存在しなくても null として出す）
            Log::debug('FG[store] 2025_11_13_B set_cum array', [
                'set_cum' => $request->input('set_cum'),
            ]);
            // set_amount / set_after_basic も併せて観測（フォールバックの動作確認用）
            Log::debug('FG[store] 2025_11_13_C set_amount / set_after_basic array', [
                'set_amount'      => $request->input('set_amount'),
                'set_after_basic' => $request->input('set_after_basic'),
            ]);
            // ★ set_cum が来ていない場合を警告で明示（今回のケース）
            if (!$request->has('set_cum')) {
                Log::warning('FG[store] set_cum is missing in request; will fall back to after_basic or amount-basic110');
            }
            */

         
            
            
            // ------------------------------------------------------------
            // サーバ側フォールバック：set_cum を「基礎控除後（set_after_basic）」の累計で補完
            // 起点は 0 行目（過年度 after_basic）。未送信なら 0 とみなす。
            // ------------------------------------------------------------
            $baseAfterBasic0 = $this->toThousand($request->input('set_after_basic.0')) ?? 0;
            $runningSetCumK  = max(0, (int)$baseAfterBasic0);

            // ★★★ 事前に配列を取得して presence を array_key_exists で判定（"0" も存在として扱う）★★★
            $arrCalAmount      = (array)$request->input('cal_amount', []);
            $arrCalBasic       = (array)$request->input('cal_basic', []);
            $arrCalAfterBasic  = (array)$request->input('cal_after_basic', []);
            $arrCalTax         = (array)$request->input('cal_tax', []);
            $arrCalCum         = (array)$request->input('cal_cum', []);
            $arrSetAmount      = (array)$request->input('set_amount', []);
            $arrSetBasic110    = (array)$request->input('set_basic110', []);
            $arrSetAfterBasic  = (array)$request->input('set_after_basic', []);
            $arrSetTax20       = (array)$request->input('set_tax20', []);
            $arrSetCum         = (array)$request->input('set_cum', []);


            // ★ 0 行目（現時点）は必ず保存対象に含める（将来行は従来どおり空ならスキップ）
            for ($i = 0; $i <= 20; $i++) {
                
            
                /*
                if ($i <= 1){
                    // ★ 各行の値は $allEmpty 判定の前に必ずログ化（null/0 でも必ず残す）
                    Log::debug('FG[store] 2025_11_13_D row inputs', [
                        'i'                 => $i,
                        'gift_year.i'       => $request->input("gift_year.$i"),
                        'set_cum.i'         => $request->input("set_cum.$i"),
                        'set_amount.i'      => $request->input("set_amount.$i"),
                        'set_basic110.i'    => $request->input("set_basic110.$i"),
                        'set_after_basic.i' => $request->input("set_after_basic.$i"),
                    ]);
                }
                */

                // ★★★ filled() は "0" を空扱いしてしまうため has/presence を array_key_exists で判定 ★★★
                $present = (
                    array_key_exists($i, $arrCalAmount)     ||
                    array_key_exists($i, $arrCalBasic)      ||
                    array_key_exists($i, $arrCalAfterBasic) ||
                    array_key_exists($i, $arrCalTax)        ||
                    array_key_exists($i, $arrCalCum)        ||
                    array_key_exists($i, $arrSetAmount)     ||
                    array_key_exists($i, $arrSetBasic110)   ||
                    array_key_exists($i, $arrSetAfterBasic) ||
                    array_key_exists($i, $arrSetTax20)      ||
                    array_key_exists($i, $arrSetCum)
                );
                $allEmpty = !$present;
                // 0行目は「空でも保存」する。1..20行は従来どおり空ならスキップ
                if ($allEmpty && $i !== 0) {
                    continue;
                }

                 // create 経路で data_id が確実に入るよう unguarded で包む
                // ★空/未送信は「キー自体を更新しない」＝既存値を保持
                $updates = [];
                $put = function(string $col, $val) use (&$updates) {
                    if ($val === null || $val === '') return;
                    $updates[$col] = $val;
                };
                $put('gift_year',  $this->intOrNull($request->input("gift_year.$i")));
                $put('age',        $this->intOrNull($request->input("age.$i")));
                $put('calendar_amount_thousand',          $this->toThousand($request->input("cal_amount.$i")));
                $put('calendar_basic_deduction_thousand', $this->toThousand($request->input("cal_basic.$i")));
                $put('calendar_after_basic_thousand',     $this->toThousand($request->input("cal_after_basic.$i")));
                $put('calendar_special_tax_thousand',     $this->toThousand($request->input("cal_tax.$i")));
                $put('calendar_add_cum_thousand',         $this->toThousand($request->input("cal_cum.$i")));
                $put('settlement_amount_thousand',        $this->toThousand($request->input("set_amount.$i")));
                $put('settlement_110k_basic_thousand',    $this->toThousand($request->input("set_basic110.$i")));
                $put('settlement_after_basic_thousand',   $this->toThousand($request->input("set_after_basic.$i")));
                $put('settlement_after_25m_thousand',     $this->toThousand($request->input("set_after_25m.$i")));
                $put('settlement_tax20_thousand',         $this->toThousand($request->input("set_tax20.$i")));
                // ★★★ 追加：精算課税の実際の贈与税額を保存する（これが欠けていた） ★★★
                // UI の "set_tax20.$i" が 20％税額なので、それを settlement_tax_thousand に保存する。
                //$put('settlement_tax_thousand',           $this->toThousand($request->input("set_tax20.$i")));
                
                // 元の set_cum が来ていればそのまま、無ければフォールバック計算（下のブロック）に任せる
                if (array_key_exists($i, $arrSetCum)) {
                    $put('settlement_add_cum_thousand', $this->toThousand($arrSetCum[$i]));
                    
                    /*
                    Log::debug('FG[store] 2025_11_13_F set_cum provided -> use as-is', [
                        'i' => $i, 'set_cum_raw' => $arrSetCum[$i]
                    ]);
                    */
                    
                    
                }

if ($i == 1){
        
    /*
    Log::debug("2025_11_13_01 Request data:", $request->all());
    
    /// まず、set_cum 配列全体をログに出力して確認
    Log::debug("2025_11_13_02 Request data for set_cum:", [
        // ★ Log::debug の第2引数は array 必須。null の可能性があるので配列で包む
        'set_cum' => $request->input('set_cum'),
    ]);
    */
    
    
    // 入力された 'set_cum.$i' の値を確認（存在しない場合はデフォルト値0を使用）
    $setCumValue = $request->input("set_cum.$i", 0); // デフォルト値を0に設定
    $convertedValue = $this->toThousand($setCumValue);
    
    /*
    // ログに出力
    Log::debug("2025_11_13_03 Saving settlement_add_cum_thousand:", [
        'index' => $i,
        'original_value' => $setCumValue,
        'converted_value' => $convertedValue,
    ]);
    
    // settlement_add_cum_thousand に変換後の値を設定する前にデバッグ
    Log::debug("2025_11_13_04 Setting settlement_add_cum_thousand:", [
        'settlement_add_cum_thousand' => $convertedValue, // ここで保存される値を確認
    ]);
    */

}


                
                // --- ここからフォールバック計算：set_cum[i] 未送信時のみ補完 ---
                $setCumWasProvided = array_key_exists($i, $arrSetCum);
                if (!$setCumWasProvided) {
                    // afterBasic[i] を優先的に取得（無ければ set_amount / set_basic110 から推定）
                    $afterBasicI = array_key_exists($i, $arrSetAfterBasic)
                        ? $this->toThousand($arrSetAfterBasic[$i])
                        : null;
                    if ($afterBasicI === null) {
                        $amountI = array_key_exists($i, $arrSetAmount)
                            ? $this->toThousand($arrSetAmount[$i])
                            : null;
                        // set_basic110 は通常 -1100（千円）で来る想定。絶対値で 1100 として扱う。
                        $basic110I = array_key_exists($i, $arrSetBasic110)
                            ? $this->toThousand($arrSetBasic110[$i])
                            : null;
                        if ($amountI !== null && $basic110I !== null) {
                            $afterBasicI = max(0, (int)$amountI - abs((int)$basic110I));
                        }
                    }
                    if ($afterBasicI !== null) {
                        $runningSetCumK += max(0, (int)$afterBasicI);
                        // 既に updates に明示指定が無ければ、ここで累計を保存
                        if (!array_key_exists('settlement_add_cum_thousand', $updates)) {
                            $updates['settlement_add_cum_thousand'] = $runningSetCumK;
                        }
                        
                        /*
                        Log::debug('FG[store] 2025_11_13_G fallback set_cum computed', [
                            'i' => $i, 'afterBasicI' => $afterBasicI, 'runningSetCumK' => $runningSetCumK
                        ]);
                        */
                        
                    }
                } else {
                    // フロントから set_cum が来ている場合は、その値を採用しつつランニングも更新
                    $providedCum = $this->toThousand($arrSetCum[$i]);
                    if ($providedCum !== null) {
                        $runningSetCumK = (int)$providedCum;
                    }
                }
                // --- フォールバックここまで ---
                


                // --- 0行目専用：cal_cum / set_cum の扱いを“明示指定 or 明示許可時のみ”に変更 ---
                if ($i === 0) {
                    // 明示許可フラグ（通常の autosave ではフォールバックしない）
                    $enableRow0Fallback = (bool)$request->boolean('enable_row0_past_fallback', false) && !$request->boolean('autosave', false);

                    // 0行目 cal_cum：リクエストに cal_cum.0 が来ていればそのまま採用（toThousandで正規化）
                    if (!array_key_exists('calendar_add_cum_thousand', $updates)) {
                        if ($request->has('cal_cum.0')) {
                            $updates['calendar_add_cum_thousand'] = max(0, (int)$this->toThousand($request->input('cal_cum.0')));
                            
                            /*
                            Log::debug('FG[store] row0 cal_cum provided -> use request', [
                                'cal_cum.0' => $request->input('cal_cum.0'),
                                'saved'     => $updates['calendar_add_cum_thousand'],
                            ]);
                            */
                            
                            
                        } elseif ($enableRow0Fallback) {
                            [$calK, $_setKdummy] = $this->computeRow0CumFromPast($dataId, $recipientNo, $request);
                            if ($calK !== null) {
                                $updates['calendar_add_cum_thousand'] = max(0, (int)$calK);
                                
                                /*
                                Log::debug('FG[store] row0 cal_cum fallback from Past', ['saved' => $updates['calendar_add_cum_thousand']]);
                                */
                                
                            }
                        } else {
                            
                            /*
                            Log::debug('FG[store] row0 cal_cum skipped (no request & no fallback)');
                            */
                            
                        }
                    }

                    // 0行目 set_cum：リクエストに set_cum.0 が来ていればそのまま採用、なければ許可時のみ Past から算出
                    if (!array_key_exists('settlement_add_cum_thousand', $updates)) {
                        if ($request->has('set_cum.0')) {
                            $updates['settlement_add_cum_thousand'] = max(0, (int)$this->toThousand($request->input('set_cum.0')));
                            
                            /*
                            Log::debug('FG[store] row0 set_cum provided -> use request', [
                                'set_cum.0' => $request->input('set_cum.0'),
                                'saved'     => $updates['settlement_add_cum_thousand'],
                            ]);
                            */
                            
                            
                        } elseif ($enableRow0Fallback) {
                            [$_calKdummy, $setK] = $this->computeRow0CumFromPast($dataId, $recipientNo, $request);
                            if ($setK !== null) {
                                $updates['settlement_add_cum_thousand'] = max(0, (int)$setK);
                                
                                /*
                                Log::debug('FG[store] row0 set_cum fallback from Past', ['saved' => $updates['settlement_add_cum_thousand']]);
                                */
                                
                            }
                        } else {
                            
                            /*
                            Log::debug('FG[store] row0 set_cum skipped (no request & no fallback)');
                            */
                            
                            
                        }
                    }
                }
                // --- 0行目専用（明示許可制）ここまで ---

    
                /*
                if ($i == 1){
                    // ★ この行で保存する updates をログに吐く（実際に DB に入る前の値）
                    Log::debug('FG[store] 2025_11_13_E updates before save', [
                        'i' => $i, 'updates' => $updates, 'runningSetCumK' => $runningSetCumK,
                    ]);
                }
                */
 


                \App\Models\FutureGiftPlanEntry::unguarded(function () use ($dataId, $recipientNo, $i, $updates) {
                    $row = \App\Models\FutureGiftPlanEntry::firstOrNew([
                        'data_id' => (int)$dataId,
                        'recipient_no' => (int)$recipientNo,
                        'row_no' => (int)$i,
                    ]);
                    if (!empty($updates)) {
                        $row->fill($updates)->save();
                    }
                });

                 $planRowsSaved++;
                 
             }
         }


        return ['header'=>$headerTouched, 'recipient'=>$recipientTouched, 'plan_rows'=>$planRowsSaved];
    }
     

    /**
     * 0行目の cal_cum / set_cum の安全フォールバック算出（千円）
     * - 暦年：改正ルックバックの範囲内合算（受贈者単位）
     * - 精算：2024年以降件数×1100 を控除後の累計（受贈者単位）
     * - フロント未送信時の穴埋めに限定して使用
     */
    private function computeRow0CumFromPast(int $dataId, int $recipientNo, Request $request): array
    {
        
        $giftBasicDeductionK = $this->resolveGiftBasicDeductionKForController($dataId, $request);

        // 基準日：future_base_ が来ていればそれを優先、無ければ 2024-12-31
        $y = (int)$request->input('future_base_year');
        $m = (int)$request->input('future_base_month');
        $d = (int)$request->input('future_base_day');
        try {
            if ($y >= 1900 && $m >= 1 && $m <= 12 && $d >= 1 && $d <= 31) {
                $death = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $d));
            } else {
                $death = new \DateTimeImmutable('2024-12-31');
            }
        } catch (\Throwable $e) {
            $death = new \DateTimeImmutable('2024-12-31');
        }

        // ルックバック判定（R8末/固定/7年）の簡易版
        $end     = $death;
        $r8End   = new \DateTimeImmutable('2026-12-31');
        $r9Start = new \DateTimeImmutable('2027-01-01');
        $r12End  = new \DateTimeImmutable('2030-12-31');
        if ($death <= $r8End) {
            $start = $death->sub(new \DateInterval('P3Y'));
        } elseif ($death >= $r9Start && $death <= $r12End) {
            $start = new \DateTimeImmutable('2024-01-01');
        } else {
            $start = $death->sub(new \DateInterval('P7Y'));
        }

        // 暦年（千円）
        $calRows = \App\Models\PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->get(['gift_year','gift_month','gift_day','amount_thousand']);
        $within3K = 0; $over3K = 0;
        foreach ($calRows as $r) {
            $ak = (int)($r->amount_thousand ?? 0);
            if ($ak <= 0) continue;
            try {
                $gd = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', (int)$r->gift_year, (int)$r->gift_month, (int)$r->gift_day));
            } catch (\Throwable $e) { continue; }
            if ($gd < $start || $gd > $end) continue;
            $threeYearsAgo = $end->sub(new \DateInterval('P3Y'));
            if ($gd >= $threeYearsAgo) $within3K += $ak; else $over3K += $ak;
        }
        $calCumK = $within3K + max(0, $over3K - $giftBasicDeductionK);        

        // 精算（千円）
        $setRows = \App\Models\PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->get(['gift_year','amount_thousand']);
        $sumSetK = 0; $cnt2024 = 0;
        foreach ($setRows as $r) {
            $ak = max(0, (int)($r->amount_thousand ?? 0));
            $sumSetK += $ak;
            $yy = (int)($r->gift_year ?? 0);
            if ($yy >= 2024) $cnt2024++;
        }
        $setCumK = max(0, $sumSetK - $giftBasicDeductionK * $cnt2024);        

        return [$calCumK, $setCumK];
    }



    private function resolveGiftBasicDeductionYenForController(int $dataId, Request $request): int
    {
        $companyId = (int)($request->user()?->company_id ?? 0);
        if ($companyId <= 0) {
            $companyId = (int)(Data::query()->where('id', $dataId)->value('company_id') ?? 0);
        }

        /** @var \App\Services\Zouyo\ZouyoGeneralRateResolver $resolver */
        $resolver = app(ZouyoGeneralRateResolver::class);

        // 新実装が入ったら単票マスター(id=1固定)を優先
        if (method_exists($resolver, 'getBasicDeductionYenFromSingleMaster')) {
            return (int) $resolver->getBasicDeductionYenFromSingleMaster(
                $companyId > 0 ? $companyId : null
            );
        }

        // 互換: 既存の年指定Resolverが残っている間は旧実装へフォールバック
        $preferredYear = (int)($request->input('future_base_year') ?? $request->input('header_year') ?? self::MASTER_KIHU_YEAR);
        if ($preferredYear <= 0) {
            $preferredYear = self::MASTER_KIHU_YEAR;
        }

        return $resolver->getBasicDeductionYen(
            $companyId > 0 ? $companyId : null,
            $preferredYear
        );
    }

    private function resolveGiftBasicDeductionKForController(int $dataId, Request $request): int
    {
        return (int) round($this->resolveGiftBasicDeductionYenForController($dataId, $request) / 1000);
    }

    /**
     * これからの贈与（ヘッダ/プラン）取得API
     * GET /zouyo/future/fetch?data_id=1&future_recipient_no=3
     * ※ 後方互換として recipient_no も受け付ける
     */
    public function fetchFutureGifts(Request $request): JsonResponse
    {


        /*
        \Log::info('[FETCH-FUTURE] hit', ['q' => $request->query()]);
        */
        
        
    
        $data = $this->resolveAuthorizedDataOrFail($request, 'view');
    
        // ① future_recipient_no 優先、無ければ recipient_no
        $recipientNo = (int) ($request->query('future_recipient_no', $request->query('recipient_no', 0)));
    



        // ② 無ければ「最小の受贈者番号」を既定に（あれば）
        if ($recipientNo <= 0) {
            $recipientNo = (int) \App\Models\FutureGiftRecipient::where('data_id', $data->id)
                            ->min('recipient_no');
        }
    
        // ③ それでも無ければ空を返して 200
        if ($recipientNo <= 0) {
            $makeArr = fn($n)=>array_fill(1,$n,null);
            return response()->json([
                'status' => 'ok',
                'data_id' => $data->id,
                'recipient_no' => null,
                'header' => ['year'=>null,'month'=>null,'day'=>null],
                'plan' => [
                    'gift_year'=>$makeArr(20),'age'=>$makeArr(20),
                    'cal_amount'=>$makeArr(20),'cal_basic'=>$makeArr(20),
                    'cal_after_basic'=>$makeArr(20),'cal_tax'=>$makeArr(20),
                    'cal_cum'=>$makeArr(20),
                    'set_amount'=>$makeArr(20),'set_basic110'=>$makeArr(20),
                    'set_after_basic'=>$makeArr(20),'set_after_25m'=>$makeArr(20),
                    'set_tax20'=>$makeArr(20),'set_cum'=>$makeArr(20),
                ],
                

                // 過年度ブロック（空）
                'rekinen' => [
                    'year'=>$makeArr(10),'month'=>$makeArr(10),'day'=>$makeArr(10),
                    'zoyo'=>$makeArr(10),'zouyo'=>$makeArr(10),'kojo'=>$makeArr(10),
                    'total'=>['zoyo'=>0,'zouyo'=>0,'kojo'=>0],
                ],
                'seisan' => [
                    'year'=>$makeArr(10),'month'=>$makeArr(10),'day'=>$makeArr(10),
                    'zoyo'=>$makeArr(10),'zouyo'=>$makeArr(10),'kojo'=>$makeArr(10),
                    'total'=>['zoyo'=>0,'zouyo'=>0,'kojo'=>0],
                ],
                'giftAmount' => 0,
                'accumulatedAmount' => 0,

                
            ]);
        }
    
        // 以降は現状のロジック（そのまま）
        $header = ['year'=>null,'month'=>null,'day'=>null];
        if ($fh = \App\Models\FutureGiftHeader::where('data_id', $data->id)->first()) {
            $header['year']  = $fh->base_year;
            $header['month'] = $fh->base_month;
            $header['day']   = $fh->base_day;
        }
    
        $makeArr = fn($n)=>array_fill(1,$n,null);
        $plan = [
            'gift_year'=>$makeArr(20),'age'=>$makeArr(20),
            'cal_amount'=>$makeArr(20),'cal_basic'=>$makeArr(20),
            'cal_after_basic'=>$makeArr(20),'cal_tax'=>$makeArr(20),
            'cal_cum'=>$makeArr(20),
            'set_amount'=>$makeArr(20),'set_basic110'=>$makeArr(20),
            'set_after_basic'=>$makeArr(20),'set_after_25m'=>$makeArr(20),
            'set_tax20'=>$makeArr(20),'set_cum'=>$makeArr(20),
        ];
        $rows = \App\Models\FutureGiftPlanEntry::where([
                    'data_id'=>$data->id,'recipient_no'=>$recipientNo
                ])->orderBy('row_no')->get();
        foreach ($rows as $r) {
            $i = (int)$r->row_no; if ($i<1||$i>20) continue;
            $plan['gift_year'][$i]  = $r->gift_year;
            $plan['age'][$i]        = $r->age;
            $plan['cal_amount'][$i]      = $r->calendar_amount_thousand;
            $plan['cal_basic'][$i]       = $r->calendar_basic_deduction_thousand;
            $plan['cal_after_basic'][$i] = $r->calendar_after_basic_thousand;
            $plan['cal_tax'][$i]         = $r->calendar_special_tax_thousand;
            $plan['cal_cum'][$i]         = $r->calendar_add_cum_thousand;
            $plan['set_amount'][$i]      = $r->settlement_amount_thousand;
            $plan['set_basic110'][$i]    = $r->settlement_110k_basic_thousand;
            $plan['set_after_basic'][$i] = $r->settlement_after_basic_thousand;
            $plan['set_after_25m'][$i]   = $r->settlement_after_25m_thousand;
            $plan['set_tax20'][$i]       = $r->settlement_tax20_thousand;
            $plan['set_cum'][$i]         = $r->settlement_add_cum_thousand;
        }



        // === ここから追加：過年度（rekinen / seisan）も同梱して返す ===
        $pack = function ($collection) {
            $out = [
                'year'=>[], 'month'=>[], 'day'=>[],
                'zoyo'=>[], 'zouyo'=>[], 'kojo'=>[],
                'total'=>['zoyo'=>0, 'zouyo'=>0, 'kojo'=>0],
            ];
            foreach ($collection as $r) {
                $i = (int)$r->row_no;
                if ($i < 1 || $i > 10) continue;
                $out['year'][$i]  = $r->gift_year       === null ? null : (int)$r->gift_year;
                $out['month'][$i] = $r->gift_month      === null ? null : (int)$r->gift_month;
                $out['day'][$i]   = $r->gift_day        === null ? null : (int)$r->gift_day;
                $z = $r->amount_thousand === null ? null : (int)$r->amount_thousand;
                $k = $r->tax_thousand    === null ? null : (int)$r->tax_thousand;
                $out['zoyo'][$i]  = $z;
                $out['zouyo'][$i] = $z; // エイリアスを同値で
                $out['kojo'][$i]  = $k;
                if ($z !== null) { $out['total']['zoyo']  += $z; $out['total']['zouyo'] += $z; }
                if ($k !== null) { $out['total']['kojo'] += $k; }
            }
            return $out;
        };

        $calRows = \App\Models\PastGiftCalendarEntry::query()
            ->where('data_id', $data->id)
            ->where('recipient_no', $recipientNo)
            ->where('amount_thousand', '>', 0) // ★ 0円贈与を除外
            ->whereBetween('row_no', [1,10])
            ->orderBy('row_no')
            ->get(['row_no','gift_year','gift_month','gift_day','amount_thousand','tax_thousand']);

            
            
        $setRows = \App\Models\PastGiftSettlementEntry::query()
            ->where('data_id', $data->id)
            ->where('recipient_no', $recipientNo)
            ->whereBetween('row_no', [1,10])
            ->orderBy('row_no')
            ->get(['row_no','gift_year','gift_month','gift_day','amount_thousand','tax_thousand']);

        $rekinen = $pack($calRows);
        $seisan  = $pack($setRows);
        $giftAmount = (int)($rekinen['total']['zoyo'] ?? 0);     // 千円合計



        // 累計（ルックバック）はフロントで動的計算するため 0 のままでOK（互換用に項目は残す）
        $accumulatedAmount = 0;


        // ▼ 互換用 past ブロック：1件以上あるときだけ同梱（空配列で上書きされるのを防ぐ）
        $pastCalendar = $calRows->map(fn($r) => [
            'gift_year'       => $r->gift_year,
            'gift_month'      => $r->gift_month,
            'gift_day'        => $r->gift_day,
            'amount_thousand' => $r->amount_thousand,
            'tax_thousand'    => $r->tax_thousand,
        ])->values()->all();

        $pastSettlement = $setRows->map(fn($r) => [
            'gift_year'       => $r->gift_year,
            'gift_month'      => $r->gift_month,
            'gift_day'        => $r->gift_day,
            'amount_thousand' => $r->amount_thousand,
            'tax_thousand'    => $r->tax_thousand,
        ])->values()->all();

        $hasAnyPast = (count($pastCalendar) + count($pastSettlement)) > 0;

        $resp = [
            'status'=>'ok',
            'data_id'=>$data->id,
            'recipient_no'=>$recipientNo,
            'header'=>$header,

            'plan'=>$plan,
            // ▼ 追加：過年度（暦年/精算）を同梱（zoyo / zouyo の両方を返す）
            'rekinen'=>$rekinen,
            'seisan'=>$seisan,


             // ▼ 互換フィールド（フロントのフォールバック用）
             'giftAmount'=>$giftAmount,
             'accumulatedAmount'=>$accumulatedAmount,
             
            // ▼ デバッグ: サーバが実際に返す4行目の値
            'debug' => [
                'plan_cal_amount_4' => $plan['cal_amount'][4] ?? null,
            ],

        ];
        if ($hasAnyPast) {
            $resp['past'] = [
                'calendar_entries'   => $pastCalendar,
                'settlement_entries' => $pastSettlement,
                'giftAmountK'        => $giftAmount,
                'accumulatedAmountK' => 0,
            ];
        }
        return response()->json($resp);
        
    }
    
    
    public function fetchFuture(Request $request)
    {

        // 既存の本実装 fetchFutureGifts() に統一
        return $this->fetchFutureGifts($request);

    }
    

    /**
     * 遺産分割（現時点）の保存：ヘッダ＋明細(recipient_no=2..10)
     */
    private function storeInheritanceDistribution(int $dataId, Request $request): void
    {

        // ★ 既定値 1 に正規化
        $dataId = ($dataId !== null && $dataId > 0) ? $dataId : 1;


        // 何も入力が無ければ早期return
        $hasAny =
            $request->filled('input_mode') ||
            !empty($request->input('id_taxable_manu', [])) ||
            !empty($request->input('id_taxable_auto', [])) ||
            !empty($request->input('id_other_credit', [])) ||
            !empty($request->input('id_cash_share', [])) ||
            !empty($request->input('id_other_share', []));

        if (!$hasAny) return;

        // 1) ヘッダ（1:1）… 分割方法の選択のみ
        $mode = (string)$request->input('input_mode', 'auto'); // 'auto' | 'manual'
        $methodCode = $mode === 'manual' ? 9 : 0;          // 0=法定, 9=手入力（暫定）
        // ★ data_id が $guarded の場合、updateOrCreate の create 分岐で data_id が入らず 1364 になる。
        //   → firstOrNew + 直代入で確実に data_id を入れる（親ファースト）
        $header = \App\Models\InheritanceDistributionHeader::firstOrNew([
            'data_id' => (int)$dataId,
        ]);
        if (!$header->exists) {
            $header->data_id = (int)$dataId;
        }
        $header->method_code = (int)$methodCode;
        $header->method_note = null;
        $header->save();

        // 2) 明細（recipient_no=2..10）
        $manu       = (array)$request->input('id_taxable_manu',  []);
        $auto       = (array)$request->input('id_taxable_auto',  []);
        $other      = (array)$request->input('id_other_credit',  []);
        $cashShare  = (array)$request->input('id_cash_share',    []);
        $otherShare = (array)$request->input('id_other_share',   []);

        // ★追加：テーブルに列が無い環境でもSQLエラーにしない（列存在チェック）
        $memberTable = null;
        $hasCashShareCol  = false;
        $hasOtherShareCol = false;
        if (class_exists(\App\Models\InheritanceDistributionMember::class)) {
            $memberTable = (new \App\Models\InheritanceDistributionMember())->getTable();
            $hasCashShareCol  = Schema::hasColumn($memberTable, 'cash_share_value_thousand');
            $hasOtherShareCol = Schema::hasColumn($memberTable, 'other_asset_share_value_thousand');
        }

        for ($no = 2; $no <= 10; $no++) {
            // ★重要：$existing を必ず定義（Undefined variable $existing 対策）
            //   - 既存行を取得して、autoモード時に「手入力値を壊さない」ためにも必要
            $existing = null;
            if (class_exists(\App\Models\InheritanceDistributionMember::class)) {
                $existing = \App\Models\InheritanceDistributionMember::where('data_id', (int)$dataId)
                    ->where('recipient_no', (int)$no)
                    ->first();
            }

            $vOther = $this->toThousand($other[$no] ?? null);
            $vCashShare  = $this->toThousand($cashShare[$no]  ?? null);
            $vOtherShare = $this->toThousand($otherShare[$no] ?? null);

            // ★未定義防止：後段の closure で必ず使うので先に初期化
            $newAuto = null;
            $newManu = null;
            $newOther = null;
            $newCashShare = null;
            $newOtherShare = null;

             if ($mode === 'manual') {
                $vManu = $this->toThousand($manu[$no] ?? null);
                $vAuto = $existing?->taxable_auto_value_thousand; // 既存autoは維持
 
                 // 手入力モード：そのまま保存
                $newCashShare  = $vCashShare;
                $newOtherShare = $vOtherShare;

                if (
                    $vManu === null &&
                    $vAuto === null &&
                    $vOther === null &&
                    $vCashShare === null &&
                    $vOtherShare === null
                ) {
                     continue;
                 }

                $newManu  = $vManu;
                $newAuto  = $vAuto;
                $newOther = $vOther;
             } else {
                 $existingManu = $existing?->taxable_manu_value_thousand;
                 $postedAuto = $this->toThousand($auto[$no] ?? null);
                 $newAuto    = $postedAuto !== null ? $postedAuto : ($existing?->taxable_auto_value_thousand ?? null);

                 $newManu  = $existingManu;
                 $newOther = $vOther ?? ($existing?->other_tax_credit_thousand ?? null);

                // 法定相続割合：手入力の金融/その他は壊さない
                $newCashShare  = $existing?->cash_share_value_thousand ?? null;
                $newOtherShare = $existing?->other_asset_share_value_thousand ?? null;

                if (
                    $newManu === null &&
                    $newAuto === null &&
                    $newOther === null &&
                    $newCashShare === null &&
                    $newOtherShare === null
                ) {
                     continue;
                 }
             }

            // ★テーブルに列が無い環境を考慮しつつ update payload を組み立てる
            $update = [
                'taxable_auto_value_thousand' => $newAuto,
                'taxable_manu_value_thousand' => $newManu,
                'other_tax_credit_thousand'   => $newOther,
            ];
            if ($hasCashShareCol) {
                $update['cash_share_value_thousand'] = $newCashShare;
            }
            if ($hasOtherShareCol) {
                $update['other_asset_share_value_thousand'] = $newOtherShare;
            }

            \App\Models\InheritanceDistributionMember::unguarded(function () use ($dataId, $no, $update) {
                \App\Models\InheritanceDistributionMember::updateOrCreate(
                    ['data_id' => (int)$dataId, 'recipient_no' => (int)$no],
                    $update
                );
            });

        }
    }


    public function jigyoEigyoDetails(Request $request)
    {


        $data = $this->resolveAuthorizedDataOrFail($request);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = ZouyoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $out = ['inputs' => is_array($payload) ? $payload : []];

        return view('zouyo.details.jigyo_eigyo_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
        ]);
    }

    public function saveJigyoEigyoDetails(Request $request): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        $payload = $this->sanitizeDetailPayload($request->except(['_token', 'data_id']));

        $calculations = $this->calculateJigyoEigyo($payload);
        $payload = array_replace($payload, $calculations);

        $payload['syunyu_jigyo_eigyo_shotoku_prev'] = $this->valueOrZero($payload['jigyo_eigyo_uriage_prev'] ?? null);
        $payload['syunyu_jigyo_eigyo_shotoku_curr'] = $this->valueOrZero($payload['jigyo_eigyo_uriage_curr'] ?? null);
        $payload['shotoku_jigyo_eigyo_shotoku_prev'] = (int) ($payload['jigyo_eigyo_shotoku_prev'] ?? 0);
        $payload['shotoku_jigyo_eigyo_shotoku_curr'] = (int) ($payload['jigyo_eigyo_shotoku_curr'] ?? 0);

        $this->updateZouyoInputPayload($data, $payload);

        $this->touchDataUpdatedAt((int) $data->id);

        /*
        return redirect()->route('zouyo.input', ['data_id' => $data->id])->with('success', '保存しました');
        */
        return redirect()->route('zouyo.input', ['data_id' => $data->id]);
    }

    public function fudosanDetails(Request $request)
    {
        $data = $this->resolveAuthorizedDataOrFail($request);
        $kihuYear = $data->kihu_year ? (int) $data->kihu_year : null;
        $warekiPrev = $kihuYear ? $this->toWarekiYear($kihuYear - 1) : '前年';
        $warekiCurr = $kihuYear ? $this->toWarekiYear($kihuYear) : '当年';
        $payload = ZouyoInput::query()
            ->where('data_id', $data->id)
            ->value('payload');
        $out = ['inputs' => is_array($payload) ? $payload : []];

        return view('zouyo.details.fudosan_details', [
            'dataId' => $data->id,
            'kihuYear' => $kihuYear,
            'warekiPrev' => $warekiPrev,
            'warekiCurr' => $warekiCurr,
            'out' => $out,
        ]);
    }

    public function saveFudosanDetails(Request $request): RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        $payload = $this->sanitizeDetailPayload($request->except(['_token', 'data_id']));

        $calculations = $this->calculateFudosan($payload);
        $payload = array_replace($payload, $calculations);

        $payload['syunyu_fudosan_shotoku_prev'] = $this->valueOrZero($payload['fudosan_shunyu_prev'] ?? null);
        $payload['syunyu_fudosan_shotoku_curr'] = $this->valueOrZero($payload['fudosan_shunyu_curr'] ?? null);

        $adjPrev = (int) ($payload['fudosan_shotoku_prev'] ?? 0);
        if ($adjPrev < 0) {
            $adjPrev += $this->valueOrZero($payload['fudosan_fusairishi_prev'] ?? null);
        }

        $adjCurr = (int) ($payload['fudosan_shotoku_curr'] ?? 0);
        if ($adjCurr < 0) {
            $adjCurr += $this->valueOrZero($payload['fudosan_fusairishi_curr'] ?? null);
        }

        $payload['shotoku_fudosan_shotoku_prev'] = $adjPrev;
        $payload['shotoku_fudosan_shotoku_curr'] = $adjCurr;

        $this->updateZouyoInputPayload($data, $payload);
        
        $this->touchDataUpdatedAt((int) $data->id);
        

        /*
        return redirect()->route('zouyo.input', ['data_id' => $data->id])->with('success', '保存しました');
        */
        return redirect()->route('zouyo.input', ['data_id' => $data->id]);
    }

    public function syoriIndex(Request $request)
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        $setting = ZouyoSyoriSetting::query()->where('data_id', $data->id)->first();
        $payload = $this->syoriDefaultPayload();

        if ($setting && is_array($setting->payload)) {
            $payload = array_replace($payload, array_intersect_key($setting->payload, $payload));
        }

        $payload = $this->applyStandardRates($payload);

        return view('zouyo.syori_menu', [
            'dataId' => $data->id,
            'settings' => $payload,
        ]);
    }

    public function syoriSave(ZouyoSyoriRequest $request): RedirectResponse
    {

        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        $validated = $request->validated();

        $payload = array_intersect_key($validated, $this->syoriDefaultPayload());
        $payload = $this->applyStandardRates($payload);

        $userId = (int) auth()->id();

        ZouyoSyoriSetting::unguarded(function () use ($data, $payload, $userId): void {
            $record = ZouyoSyoriSetting::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->data_id = $data->id;
                $record->company_id = $data->company_id;
                $record->group_id = $data->group_id;
                $record->created_by = $userId ?: null;
            }

            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;
            $record->payload = $payload;
            $record->updated_by = $userId ?: null;

            $record->save();
        });

        $goto = (string) $request->input('redirect_to', '');
        $routeParams = ['data_id' => $data->id];

        if ($goto === 'input') {
            /*
            return redirect()->route('zouyo.input', $routeParams)->with('success', '保存しました');
            */
            return redirect()->route('zouyo.input', $routeParams);
        }

        if ($goto === 'master') {
            /*
            return redirect()->route('zouyo.master', $routeParams)->with('success', '保存しました');
            */
            return redirect()->route('zouyo.master', $routeParams);
        }

        if ($goto === 'data_master') {
            /*
            return redirect()->route('data.index', $routeParams)->with('success', '保存しました');
            */
            return redirect()->route('data.index', $routeParams);
        }

        /*
        return redirect()->route('zouyo.syori', $routeParams)->with('success', '保存しました');
        */
        return redirect()->route('zouyo.syori', $routeParams);
    }

    public function master(Request $request)
    {
        $dataId = (int) ($request->query('data_id') ?? 0);
        if ($dataId <= 0) {
            return redirect()->route('zouyo.index');
        }

        $data = $this->resolveCompanyScopedDataOrFail($request);

        // ★ ZouyoMasterSheet が未導入環境でも落ちないようにガード
        $grid = [];
        if (class_exists(\App\Services\ZouyoMasterSheet::class)) {
            $grid = \App\Services\ZouyoMasterSheet::grid();
        }
        return view('zouyo.master', [
            'dataId' => $data->id,
            'grid'   => $grid,
        ]);

    }

    public function shotokuMaster(Request $request)    
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('zouyo.master.shotoku_master', [
            'dataId' => $data->id,
            'rates' => $this->getShotokuMasterRates(),            
        ]);
    }

    public function juminMaster(Request $request)    
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('zouyo.master.jumin_master', [
            'dataId' => $data->id,
            'rates' => $this->getJuminMasterRates(),
        ]);
    }

    public function tokureiMaster(Request $request, $masterService)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);

        return view('zouyo.master.tokurei_master', [
            'dataId' => $data->id,
            'rates' => $this->getTokureiMasterRates(),            
        ]);
    }

    public function shinkokutokureiMaster(Request $request, $masterService)
    {
        $data = $this->resolveCompanyScopedDataOrFail($request);
        
        return view('zouyo.master.shinkokutokurei_master', [
            'dataId' => $data->id,
            'rates' => $this->getShinkokutokureiMasterRates(),            
        ]);
    }



    /**
     * ZouyoMasterService は存在しないため、まずは依存を外す。
     * 後続で、実際の贈与税マスター取得ロジックへ置き換える。
     */
    private function getShotokuMasterRates(): array
    {

        return [];

    }

    private function getJuminMasterRates(): array
    {

        return [];

    }

    private function getTokureiMasterRates(): array
    {

        return [];

    }

    private function getShinkokutokureiMasterRates(): array
    {

        return [];

    }


    private function getFurusatoInputPayload(Data $data): array
    {
        return optional(FurusatoInput::query()
            ->where('data_id', $data->id)
            ->first())->payload ?? [];
    }

    private function getStoredFurusatoResults(int $dataId): array
    {
        $payload = FurusatoResult::query()
            ->where('data_id', $dataId)
            ->value('payload');

        return is_array($payload) ? $payload : [];
    }

    private function storeFurusatoResults(Data $data, array $results): void
    {
        $userId = (int) auth()->id();

        FurusatoResult::unguarded(function () use ($data, $results, $userId): void {
            $record = FurusatoResult::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->data_id = $data->id;
                $record->created_by = $userId ?: null;
            }

            $record->company_id = $data->company_id;
            $record->group_id = $data->group_id;
            $record->payload = $results;
            $record->updated_by = $userId ?: null;

            $record->save();
        });
    }

    private function sanitizeDetailPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->toNullableInt($value);
        }

        return $payload;
    }

    private function calculateJigyoEigyo(array $inputs): array
    {
        $keihiFields = [
            'jigyo_eigyo_keihi_1',
            'jigyo_eigyo_keihi_2',
            'jigyo_eigyo_keihi_3',
            'jigyo_eigyo_keihi_4',
            'jigyo_eigyo_keihi_5',
            'jigyo_eigyo_keihi_6',
            'jigyo_eigyo_keihi_7',
            'jigyo_eigyo_keihi_sonota',
        ];

        $result = [];

        foreach (['prev', 'curr'] as $period) {
            $uriage = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_uriage_%s', $period)] ?? null);
            $urigenka = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_urigenka_%s', $period)] ?? null);
            $sashihiki1 = $uriage - $urigenka;
            $result[sprintf('jigyo_eigyo_sashihiki_1_%s', $period)] = $sashihiki1;

            $keihiTotal = 0;
            foreach ($keihiFields as $field) {
                $keihiTotal += $this->valueOrZero($inputs[sprintf('%s_%s', $field, $period)] ?? null);
            }
            $result[sprintf('jigyo_eigyo_keihi_gokei_%s', $period)] = $keihiTotal;

            $sashihiki2 = $sashihiki1 - $keihiTotal;
            $result[sprintf('jigyo_eigyo_sashihiki_2_%s', $period)] = $sashihiki2;

            $senjuusha = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_senjuusha_kyuyo_%s', $period)] ?? null);
            $mae = $sashihiki2 - $senjuusha;
            $result[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_mae_%s', $period)] = $mae;

            $tokubetsuKojo = $this->valueOrZero($inputs[sprintf('jigyo_eigyo_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);
            $result[sprintf('jigyo_eigyo_shotoku_%s', $period)] = $mae - $tokubetsuKojo;
        }

        foreach ($result as $key => $value) {
            $result[$key] = (int) $value;
        }

        return $result;
    }

    private function calculateFudosan(array $inputs): array
    {
        $keihiFields = [
            'fudosan_keihi_1',
            'fudosan_keihi_2',
            'fudosan_keihi_3',
            'fudosan_keihi_4',
            'fudosan_keihi_5',
            'fudosan_keihi_6',
            'fudosan_keihi_7',
            'fudosan_keihi_sonota',
        ];

        $result = [];

        foreach (['prev', 'curr'] as $period) {
            $shunyu = $this->valueOrZero($inputs[sprintf('fudosan_shunyu_%s', $period)] ?? null);

            $keihiTotal = 0;
            foreach ($keihiFields as $field) {
                $keihiTotal += $this->valueOrZero($inputs[sprintf('%s_%s', $field, $period)] ?? null);
            }
            $result[sprintf('fudosan_keihi_gokei_%s', $period)] = $keihiTotal;

            $sashihiki = $shunyu - $keihiTotal;
            $result[sprintf('fudosan_sashihiki_%s', $period)] = $sashihiki;

            $senjuusha = $this->valueOrZero($inputs[sprintf('fudosan_senjuusha_kyuyo_%s', $period)] ?? null);
            $mae = $sashihiki - $senjuusha;
            $result[sprintf('fudosan_aoi_tokubetsu_kojo_mae_%s', $period)] = $mae;

            $tokubetsuKojo = $this->valueOrZero($inputs[sprintf('fudosan_aoi_tokubetsu_kojo_gaku_%s', $period)] ?? null);
            $result[sprintf('fudosan_shotoku_%s', $period)] = $mae - $tokubetsuKojo;
        }

        foreach ($result as $key => $value) {
            $result[$key] = (int) $value;
        }

        return $result;
    }

    private function updateFurusatoInputPayload(Data $data, array $updates): void
    {
        $userId = (int) auth()->id();

        FurusatoInput::unguarded(function () use ($data, $updates, $userId): void {
            $record = FurusatoInput::firstOrNew(['data_id' => $data->id]);

            if (! $record->exists) {
                $record->fill([
                    'data_id'    => $data->id,
                    'company_id' => $data->company_id,
                    'group_id'   => $data->group_id,
                    'created_by' => $userId ?: null,
                ]);
            }

            $current = is_array($record->payload) ? $record->payload : [];
            $record->payload = array_replace($current, $updates);
            $record->updated_by = $userId ?: null;

            $record->save();
        });
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    private function valueOrZero(?int $value): int
    {
        return $value ?? 0;
    }

    private function resolveAuthorizedDataOrFail(Request $request, string $ability = 'view'): Data
    {

        /*
        \Log::info('[AUTH-CHECK] start', [
            'env'         => app()->environment(),
            'isLocal'     => app()->isLocal(),
            'debug'       => config('app.debug'),
            'data_id_in'  => $request->input('data_id'),
            'data_id_q'   => $request->query('data_id'),
            'selected_id' => session('selected_data_id'),
            'expectsJson' => $request->expectsJson(),
        ]);
        */

        // 1) data_id を決定
        $id = (int) ($request->input('data_id') ?? $request->query('data_id') ?? session('selected_data_id') ?? 0);
        if ($id <= 0) {
            // AJAX なら JSON で丁寧に返す
            if ($request->expectsJson() || $request->boolean('autosave')) {
                abort(response()->json(['ok'=>false,'message'=>'data_id が指定されていません。'], 422));
            }
            abort_unless(false, 422, 'data_id が指定されていません。');
        }


        // 2) 対象データを取得
        $data = Data::with('guest')->findOrFail($id);

        // 3) ローカル/デバッグ時の緩和（未ログイン→擬似ログイン、会社/グループ照合をスキップ）
        if (app()->isLocal() || config('app.debug')) {

            /*
            \Log::info('[AUTH-CHECK] local/debug branch');
            */


            if (!auth()->check()) {
                // 開発用ダミーユーザーでログイン
                auth()->loginUsingId(1);
                
                /*
                \Log::info('[AUTH-CHECK] loginUsingId(1) done');
                */

                // 念のため属性が必要ならここで上書き（ユーザーモデルに項目がある想定）
                $u = auth()->user();
                if ($u) {
                    $u->company_id = $u->company_id ?? $data->company_id;
                    $u->group_id   = $u->group_id   ?? $data->group_id;

                        /*
                        \Log::info('[AUTH-CHECK] user after patch', [
                            'user_id'    => $u->id ?? null,
                            'company_id' => $u->company_id ?? null,
                            'group_id'   => $u->group_id ?? null,
                        ]);
                        */
                }
    
                /*
                \Log::info('[AUTH-CHECK] allow (local/debug)');
                */

            }
            // 照合はスキップしてそのまま返す（開発時のみ）
            return $data;
        }

        // 4) 本番の通常認可
        $me = $request->user();
        if (!$me) {
            if ($request->expectsJson() || $request->boolean('autosave')) {
                abort(response()->json(['ok'=>false,'message'=>'未ログインです。'], 401));
            }
            abort_unless(false, 401);
        }
        $sameCompany = ((int)$data->company_id === (int)($me->company_id ?? 0));
        $sameGroup   = ((int)$data->group_id   === (int)($me->group_id   ?? 0));
        $isPrivileged= (method_exists($me,'isOwner') && $me->isOwner()) || in_array(strtolower((string)$me->role), ['owner','registrar'], true);
        if (!$sameCompany || (!$isPrivileged && !$sameGroup)) {
            
            \Log::warning('[AUTH-CHECK] forbidden', [
                'sameCompany' => $sameCompany, 'sameGroup' => $sameGroup, 'isPriv' => $isPrivileged
            ]);            
            
            if ($request->expectsJson() || $request->boolean('autosave')) {
                abort(response()->json(['ok'=>false,'message'=>'権限がありません。'], 403));
            }
            abort_unless(false, 403);
        }
        
        /*
        \Log::info('[AUTH-CHECK] allow (production)');
        */


        return $data;
    }


    private function resolveCompanyScopedDataOrFail(Request $request): Data
    {
        // --- data_id を取得（入力→クエリ→セッションの順） ---
        $id = (int) ($request->input('data_id') ?? $request->query('data_id') ?? session('selected_data_id') ?? 0);

        abort_unless($id > 0, 422, 'data_id が指定されていません。');

        $data = Data::with('guest')->findOrFail($id);

        // ★ ローカル/DEBUG は会社・グループ照合を緩和（擬似ログイン＋照合スキップ）
        if (app()->isLocal() || config('app.debug')) {
            if (!auth()->check()) {
                auth()->loginUsingId(1);
            }
            // 必要なら属性を補完（存在しないなら無視されます）
            $u = auth()->user();
            if ($u) {
                $u->company_id = $u->company_id ?? $data->company_id;
                $u->group_id   = $u->group_id   ?? $data->group_id;
            }
            return $data;
        }

        // --- 本番のみ厳格照合 ---
        $me = $request->user();
        abort_unless($me, 401);
        if ((int) $data->company_id !== (int) ($me->company_id ?? 0)) {
            abort(403);
        }
        return $data;
    }


    /**
     * 入力画面で保存成功したときに datas.updated_at を明示更新する
     */
    private function touchDataUpdatedAt(int $dataId): void
    {
        if ($dataId <= 0) {
            return;
        }

        Data::query()
            ->where('id', $dataId)
            ->update([
                'updated_at' => now(),
            ]);
    }


    private function syoriDefaultPayload(): array
    {
        return [
            'detail_mode' => 1,
            'bunri_flag' => 0,
            'one_stop_flag' => 1,
            'shitei_toshi_flag' => 0,
            'pref_standard_rate' => 0.04,
            'muni_standard_rate' => 0.06,
            'pref_applied_rate' => 0.04,
            'muni_applied_rate' => 0.06,
            'pref_equal_share' => 1500,
            'muni_equal_share' => 3500,
            'other_taxes_amount' => 0,
        ];
    }

    private function applyStandardRates(array $payload): array
    {
        $shitei = (int) ($payload['shitei_toshi_flag'] ?? 0);

        if ($shitei === 1) {
            $payload['pref_standard_rate'] = 0.02;
            $payload['muni_standard_rate'] = 0.08;
        } else {
            $payload['pref_standard_rate'] = 0.04;
            $payload['muni_standard_rate'] = 0.06;
        }

        if (! array_key_exists('pref_applied_rate', $payload) || $payload['pref_applied_rate'] === null) {
            $payload['pref_applied_rate'] = $payload['pref_standard_rate'];
        }

        if (! array_key_exists('muni_applied_rate', $payload) || $payload['muni_applied_rate'] === null) {
            $payload['muni_applied_rate'] = $payload['muni_standard_rate'];
        }

        $payload['detail_mode'] = (int) ($payload['detail_mode'] ?? 1);
        $payload['bunri_flag'] = (int) ($payload['bunri_flag'] ?? 0);
        $payload['one_stop_flag'] = (int) ($payload['one_stop_flag'] ?? 1);
        $payload['shitei_toshi_flag'] = $shitei;
        $payload['pref_applied_rate'] = (float) $payload['pref_applied_rate'];
        $payload['muni_applied_rate'] = (float) $payload['muni_applied_rate'];
        $payload['pref_standard_rate'] = (float) $payload['pref_standard_rate'];
        $payload['muni_standard_rate'] = (float) $payload['muni_standard_rate'];
        $payload['pref_equal_share'] = (int) ($payload['pref_equal_share'] ?? 1500);
        $payload['muni_equal_share'] = (int) ($payload['muni_equal_share'] ?? 3500);
        $payload['other_taxes_amount'] = (int) ($payload['other_taxes_amount'] ?? 0);

        return $payload;
    }



    /**
     * future専用autosaveかどうかの判定で用いる：
     * 過去タブ系の入力（inherit_*, rekinen_*, seisan_*）が1つでもあれば true
     */
    private function hasAnyPastInputs(Request $req): bool
    {
        $keys = $req->keys();
        foreach ($keys as $k) {
            if (str_starts_with($k, 'inherit_')) return true;
            if (str_starts_with($k, 'rekinen_')) return true;
            if (str_starts_with($k, 'seisan_'))  return true;
        }
        // 行配列送信を想定した name[]/name[1] 形式の検知（Guzzle変換後でもキーに現れる）
        // 明示キーが無い場合は個別チェック
        $maybe = [
            'inherit_year','inherit_month','inherit_day',
            // 代表的な行キー（存在チェックのみ）
            'rekinen_year.1','rekinen_month.1','rekinen_day.1','rekinen_zoyo.1','rekinen_kojo.1',
            'seisan_year.1','seisan_month.1','seisan_day.1','seisan_zoyo.1','seisan_kojo.1',
        ];
        foreach ($maybe as $m) {
            if ($req->has($m)) return true;
        }
        return false;
    }



    /**
     * DB に保存済みの「最適贈与」結果を取得（無ければ空配列）
     * モデル／テーブル未作成の環境でも安全に動くようガードしています。
     */
    private function getStoredZouyoResults(int $dataId): array
    {
        try {
            // モデルがまだ無い／autoload できない場合は空配列
            if (!class_exists(\App\Models\ZouyoResult::class)) {
                return [];
            }

            $payload = \App\Models\ZouyoResult::query()
                ->where('data_id', $dataId)
                ->value('payload');

            return is_array($payload) ? $payload : [];
        } catch (\Throwable $e) {
            
            /*
            \Log::warning('getStoredZouyoResults failed', [
                'data_id' => $dataId,
                'error'   => $e->getMessage(),
            ]);
            */
            
            return [];
        }
    }
    

    
    /**
     * 計算専用エンドポイント
     * - 保存とは独立して、POSTデータを元に Service を呼び出す
     * - 計算結果はセッションへ格納（後続でDB保存/結果タブ表示へ拡張可能）
     */
    public function calc(Request $request, ZouyotaxCalc $service)
    {

        // ★ 最終フォールバック：query / session の data_id を input 側へマージ
        $did = (int)($request->input('data_id')
            ?? $request->query('data_id')
            ?? session('selected_data_id')
            ?? 0);
        if ($did > 0) {
            $request->merge(['data_id' => $did]);
        }

        // 必須: data_id の存在検証（将来的に AuthorizesData 等の親ファーストに差し替え可）
        $v = Validator::make($request->all(), [
            'data_id' => ['required','integer','min:1'],
        ]);
        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }


        // ★ 親データを認可付きで確定（data_id を元に Data を取得）
        //   - ローカル/DEBUG では loginUsingId(1) まで行う既存ロジックに乗せる
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');


        // ★ 先に提案書ヘッダ＋家族構成を保存
        //   - 氏名を空欄にした行もここで削除される
        //   - 「計算開始」押下時にも、空欄状態が DB に反映される
        $this->storeProposalHeaderAndFamily($data->id, $request);



        // ★ 追加：
        //   「計算開始」押下時も、過年度の贈与(input03)を先に保存する。
        //   /zouyo/calc は /zouyo/save と別エンドポイントなので、
        //   ここで明示的に保存しないと past_gift_* 系がDBに反映されない。
        $this->storePastGifts($data->id, $request);



        // ★ 遺産分割(現時点)：「手入力」モードのときだけ、手入力した課税価格を DB に保存
        //
        //   - input_mode = 'manual' のとき：
        //       → id_taxable_manu[2..10] を InheritanceDistributionMember.taxable_manu_value_thousand に保存
        //       → InheritanceDistributionHeader.method_code = 9（手入力）
        //   - input_mode = 'auto' のとき：
        //       → DB には書き込まず、Service 側の自動按分結果のみを使用
        if ((string)$request->input('input_mode') === 'manual') {
            $this->storeInheritanceDistribution($data->id, $request);
        }
        

        $this->touchDataUpdatedAt((int) $data->id);        
        

        try {
            // POST全体を渡して計算（Service側で必要フィールド抽出）
            $dataId = (int)$request->input('data_id');
            $payload = $request->all();
            $heirs = ProposalFamilyMember::where('data_id', $dataId)
                ->whereBetween('row_no', [2, 10])
                ->get()
                ->toArray();
            $result = $service->compute($payload, $heirs);
    
    
    
            // ▼ compute() の戻り値（before/after/delta/projections）をそのまま保存
            if (!isset($result['meta']) || !is_array($result['meta'])) {
                $result['meta'] = [];
            }
            $result['meta']['ts'] = now()->toDateTimeString();

            // 実体は Cache に置き、セッションにはキーのみ保存
            $sid = Session::getId(); // 同一ブラウザ内で一意
            $cacheKey = "zouyo:results:{$sid}";
            \Cache::put($cacheKey, $result, now()->addMinutes(60));
            Session::put('zouyo.results_key', $cacheKey);

            // ★ 結果タブ(input06)へ明示遷移
            /*
            return redirect()
                ->route('zouyo.input', [
                    'active_tab' => 'input06',
                    'show_result' => 1,    // Blade 側の既定切り替え用
                ])
                ->with('active_tab', 'input06') // セッション経由の保険
                ->with('success', '計算が完了しました。');
            */
            
            return redirect()
                ->route('zouyo.input', [
                    'active_tab' => 'input06',
                    'show_result' => 1,    // Blade 側の既定切り替え用
                ])
                ->with('active_tab', 'input06'); // セッション経由の保険


        } catch (\Throwable $e) {
            
            /*
            Log::error('[ZouyotaxCalc] calc failed', [
                'data_id' => (int)$request->input('data_id'),
                'ex'      => $e->getMessage(),
            ]);
            */
            // 開発中は例外を握りつぶさず、Laravel標準の例外画面（file/line/trace）に任せる
            if (config('app.debug')) {
                throw $e;
            }

            // 本番想定：ユーザーには詳細を出さず、ログに突合用ID付きで残す
            $errorId = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\Log::error("[ZouyoController][calc][$errorId] {$e->getMessage()}", [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['calc' => "計算でエラーが発生しました。（Error ID: {$errorId}）"]
            );
            
        }

    }


    /**
     * 過年度の贈与額を取得するAPI
     */
    public function fetch(Request $request)
    {


        $data = $this->resolveAuthorizedDataOrFail($request, 'view');
        $recipientNo = (int) $request->input('recipient_no', 0);
        abort_unless($recipientNo > 0, 422, 'recipient_no is required');

        $giftAmount = (int) PastGiftCalendarEntry::query()
            ->where('data_id', $data->id)
            ->where('recipient_no', $recipientNo)
            ->sum('amount_thousand');

        $accumulatedAmount = (int) PastGiftSettlementEntry::query()
            ->where('data_id', $data->id)
            ->where('recipient_no', $recipientNo)
            ->sum('amount_thousand');

        return response()->json([
            'giftAmount' => $giftAmount,
            'accumulatedAmount' => $accumulatedAmount,
        ]);


    }




    public function fetchPastData(Request $request)
    {

        // 既存の本実装 pastFetch() に統一
        $request->query->set('recipient_no', (int) $request->input('recipient_no', 0));
        return $this->pastFetch($request);

    }



    
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
    
        // JSONで返却
        return response()->json([
            'giftAmount' => $giftAmount,
            'accumulatedAmount' => $accumulatedAmount,
        ]);
    }


    /**
     * 過年度贈与（暦年/精算）の行データ(1..10)と合計を返す
     * レスポンス形：
     * {
     *   "status": "ok",
     *   "rekinen": {
     *     "year":  { "1":2023, ... }, "month":{...}, "day":{...},
     *     "zoyo":  { "1":120, ... },  "kojo": { "1":10, ... },   // ← 千円
     *     "total": { "zoyo": 1234, "kojo": 56 }                  // ← 千円
     *   },
     *   "seisan": {
     *     "year": {...}, "month": {...}, "day": {...},
     *     "zoyo": {...}, "kojo": {...},
     *     "total": { "zoyo": ..., "kojo": ... }
     *   }
     * }
     */
    public function pastFetch(Request $request): JsonResponse
    {
        // 親ファースト認可（ローカル/DEBUG時は loginUsingId(1) まで行う）
        $data = $this->resolveAuthorizedDataOrFail($request, 'view');
        $dataId      = (int) $data->id;
        $recipientNo = (int) $request->query('recipient_no', 0);
        abort_unless($recipientNo > 0, 422, 'recipient_no is required');

        // 1..10行のみを対象
        $calRows = PastGiftCalendarEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->whereBetween('row_no', [1, 10])
            ->get(['row_no','gift_year','gift_month','gift_day','amount_thousand','tax_thousand']);

        $setRows = PastGiftSettlementEntry::query()
            ->where('data_id', $dataId)
            ->where('recipient_no', $recipientNo)
            ->orderBy('row_no')
            ->whereBetween('row_no', [1, 10])
            ->get(['row_no','gift_year','gift_month','gift_day','amount_thousand','tax_thousand']); // ★typo修正: mouth_thousand→amount_thousand

        $pack = function ($rows) {
            $out = [
                'year' => [], 'month' => [], 'day' => [],
                'zoyo' => [], 'zouyo' => [], 'kojo' => [],
                'total' => ['zoyo' => 0, 'zouyo' => 0, 'kojo' => 0],
            ];
            foreach ($rows as $r) {
                $i = (int)$r->row_no;
                if ($i < 1 || $i > 10) continue;
                $z = $r->amount_thousand === null ? null : (int)$r->amount_thousand;
                $k = $r->tax_thousand === null ? null : (int)$r->tax_thousand;
        
                $out['year'][$i]  = $r->gift_year;
                $out['month'][$i] = $r->gift_month;
                $out['day'][$i]   = $r->gift_day;
                $out['zoyo'][$i]  = $z;
                $out['zouyo'][$i] = $z;
                $out['kojo'][$i]  = $k;
        
                if ($z !== null) {
                    $out['total']['zoyo']  += $z;
                    $out['total']['zouyo'] += $z;
                }
                if ($k !== null) {
                    $out['total']['kojo'] += $k; // ← ★ ここで合計
                }
            }
            return $out;



        };

        $rekinen = $pack($calRows);
        $seisan  = $pack($setRows);

        return response()->json([
            'status'            => 'ok',
            'data_id'           => $dataId,
            'recipient_no'      => $recipientNo,
            'rekinen'           => $rekinen,
            'seisan'            => $seisan,
            // 既存互換（クライアントが参照している場合に備えたサマリ）
            'giftAmount'        => (int)($rekinen['total']['zoyo'] ?? 0),
            'accumulatedAmount' => (int)($seisan['total']['zoyo'] ?? 0),
        ]);


    }





    
    public function saveFamily(Request $request): JsonResponse|RedirectResponse
    {
        /*
        \Log::info('[Zouyo][saveFamily] called', [
            'keys'    => array_keys($request->all()),
            'data_id' => $request->input('data_id'),
        ]);
        */

        $data = $this->resolveAuthorizedDataOrFail($request, 'update');

        /*
        \Log::info('[Zouyo][saveFamily] resolved data', [
            'data_id' => $data->id,
        ]);
        */

        $this->storeProposalHeaderAndFamily($data->id, $request);
        
        $this->touchDataUpdatedAt((int) $data->id);        
    
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        /*    
        return back()->with('success', '家族構成とヘッダを保存しました');
        */
        return back();
    }
    

    public function savePast(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $this->storePastGifts($data->id, $request);

        $this->touchDataUpdatedAt((int) $data->id);


    
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }



        $goto = (string) $request->input('redirect_to', '');

        if ($goto === 'input') {
            /*
            return redirect()
                ->route('zouyo.input', ['data_id' => $data->id])
                ->with('active_tab', (string) ($request->input('active_tab') ?: 'input03'))
                ->with('success', '過年度の贈与を保存しました');
            */


            return redirect()
                ->route('zouyo.input', ['data_id' => $data->id])
                ->with('active_tab', (string) ($request->input('active_tab') ?: 'input03'));


        }
        /*
        return back()
            ->with('active_tab', (string) ($request->input('active_tab') ?: 'input03'))
            ->with('success', '過年度の贈与を保存しました');
        */

        return back()
            ->with('active_tab', (string) ($request->input('active_tab') ?: 'input03'));

    }
    

    // オートセーブ(JSON)と通常遷移(リダイレクト)の両対応
    public function saveFuture(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        
/*        
Log::debug('[FG DEBUG] set_tax20 full request', [
    'set_tax20' => $request->input('set_tax20'),
    'keys'      => array_keys($request->all())
]);
*/

        
        $summary = $this->storeFutureGifts($data->id, $request);
    

        if (
            ($summary['header'] ?? false)
            || ($summary['recipient'] ?? false)
            || ((int)($summary['plan_rows'] ?? 0) > 0)
        ) {
            $this->touchDataUpdatedAt((int) $data->id);
        }



        /*
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }
    
        return back()->with('success', 'これからの贈与を保存しました');
        */
        
        // 実運用では try/catch とバリデーションを置いてください
        // --- 保存処理（既存ロジック） ---
        // $this->futureSaver->handle($request); など

        $isAjax = $request->ajax() || $request->wantsJson() || $request->boolean('autosave');

        if ($isAjax) {
            // オートセーブ用の軽量レスポンス
            return response()->json([
                'status'     => 'ok',
                'active_tab' => $request->input('active_tab'),
                'next_tab'   => $request->input('next_tab'),
                'data_id'    => $request->input('data_id'),
                'saved'      => $summary,
                'saved_at'   => now()->toDateTimeString(),
            ]);
        }

        // 通常フォームPOST時は従来通りの遷移
        /*
        return redirect()->back()->with('success', '保存しました');
        */
        
        return redirect()->back();
    }
    
    public function saveInheritance(Request $request): JsonResponse|RedirectResponse    
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'update');
        $this->storeInheritanceDistribution($data->id, $request);
        
        $this->touchDataUpdatedAt((int) $data->id);
        

        // オートセーブ/非同期（tabs切替時）に対応
        if ($request->ajax() || $request->wantsJson() || $request->boolean('autosave')) {
            return response()->json([
                'status'     => 'ok',
                'active_tab' => $request->input('active_tab'),
                'next_tab'   => $request->input('next_tab'),
                'data_id'    => $request->input('data_id'),
            ]);
        }

        // 通常フォームPOSTは従来どおりリダイレクト
        /*
        return back()->with('success', '遺産分割情報を保存しました');
        */

        return back();

    }
    

    public function previewInheritanceBefore(Request $request, ZouyotaxCalc $service): JsonResponse
    {
        $data = $this->resolveAuthorizedDataOrFail($request, 'view');

        try {
            $context = $this->makeInputContext($request, $data->id);
            $payload = $this->buildStoredPayloadForInheritancePreview($data->id, $context);
            
            $payload = $this->mergeInheritancePreviewRequestPayload($payload, $request);
            [$previewHeader, $previewFamily, $previewInheritance] =
                $this->buildInheritancePreviewPrefillFromPayload($payload, $context);
            

            // ★重要：
            //   タブクリック直後の未保存変更もプレビュー計算に反映させるため、
            //   heirs は DB から再読込せず、request をマージ済みの previewFamily から組み立てる。
            $heirs = $this->buildPreviewHeirsFromFamily($previewFamily);


            /*
            if (config('app.debug')) {
                Log::debug('2026.04.08 00001 [previewInheritanceBefore] request/payload snapshot', [
                    'data_id'     => $data->id,
                    'input_mode'  => $payload['input_mode'] ?? null,
                    'name'        => $payload['name'] ?? [],
                    'bunsi'       => $payload['bunsi'] ?? [],
                    'bunbo'       => $payload['bunbo'] ?? [],
                    'relationship'=> $payload['relationship'] ?? [],
                    'property'    => $payload['property'] ?? [],
                    'cash'        => $payload['cash'] ?? [],
                    'id_taxable_manu' => $payload['id_taxable_manu'] ?? [],
                    'id_cash_share'   => $payload['id_cash_share'] ?? [],
                    'id_other_share'  => $payload['id_other_share'] ?? [],
                    'id_other_credit' => $payload['id_other_credit'] ?? [],
                    'heirs'       => $heirs,
                ]);
            }
            */


            $results = $service->compute($payload, $heirs);


            /*
            if (config('app.debug')) {
                Log::debug('2026.04.08 00002 [previewInheritanceBefore] compute result snapshot', [
                    'data_id'          => $data->id,
                    'basic_deduction'  => $results['before']['summary']['basic_deduction'] ?? null,
                    'taxable_estate'   => $results['before']['summary']['taxable_estate'] ?? null,
                    'sozoku_tax_total' => $results['before']['summary']['sozoku_tax_total'] ?? null,
                    'before_heirs'     => $results['before']['heirs'] ?? [],
                ]);
            }
            */

            $response = [
                'status'  => 'ok',
                'preview' => $this->buildInheritancePreviewResponse(
                    $results['before'] ?? [],

                    $previewHeader,
                    $previewFamily,
                    $previewInheritance,
                    
                ),

            ];

            if (config('app.debug')) {
                $response['debug'] = [
                    'input_mode' => $payload['input_mode'] ?? null,
                    'bunsi'      => $payload['bunsi'] ?? [],
                    'bunbo'      => $payload['bunbo'] ?? [],
                    'summary'    => [
                        'basic_deduction'  => $results['before']['summary']['basic_deduction'] ?? null,
                        'taxable_estate'   => $results['before']['summary']['taxable_estate'] ?? null,
                        'sozoku_tax_total' => $results['before']['summary']['sozoku_tax_total'] ?? null,
                    ],
                ];
            }

            return response()->json($response);

        } catch (\Throwable $e) {
            if (config('app.debug')) {
                throw $e;
            }

            Log::error('2026.04.03 00001 [ZouyoController][previewInheritanceBefore] failed', [
                'data_id' => $data->id,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => '遺産分割プレビュー計算に失敗しました。',
            ], 500);
        }
    }

    private function buildStoredPayloadForInheritancePreview(int $dataId, array $context): array
    {
        $payload = [
            'data_id' => $dataId,
        ];

        $header = (array)($context['prefillHeader'] ?? []);
        $family = (array)($context['prefillFamily'] ?? []);
        $inheritance = (array)($context['prefillInheritance'] ?? []);

        if (array_key_exists('year', $header)) {
            $payload['header_year'] = $header['year'];
        }
        if (array_key_exists('month', $header)) {
            $payload['header_month'] = $header['month'];
        }
        if (array_key_exists('day', $header)) {
            $payload['header_day'] = $header['day'];
        }
        if (array_key_exists('per', $header)) {
            $payload['per'] = $header['per'];
        }

        foreach ($family as $rowNo => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowNo = (int)$rowNo;
            if ($rowNo < 1 || $rowNo > 10) {
                continue;
            }

            $payload['name'][$rowNo]                = $row['name'] ?? null;
            $payload['relationship'][$rowNo]        = $row['relationship_code'] ?? null;
            $payload['relationship_code'][$rowNo]   = $row['relationship_code'] ?? null;
            $payload['civil_share_bunsi'][$rowNo]   = $row['civil_share_bunsi'] ?? null;
            $payload['civil_share_bunbo'][$rowNo]   = $row['civil_share_bunbo'] ?? null;
            $payload['bunsi'][$rowNo]               = $row['bunsi'] ?? null;
            $payload['bunbo'][$rowNo]               = $row['bunbo'] ?? null;
            $payload['twenty_percent_add'][$rowNo]  = $row['twenty_percent_add'] ?? 0;
            $payload['tokurei_zouyo'][$rowNo]       = $row['tokurei_zouyo'] ?? 0;
            $payload['property'][$rowNo]            = $row['property'] ?? null;
            $payload['cash'][$rowNo]                = $row['cash'] ?? null;
        }

        $payload['input_mode'] = ((int)($inheritance['method_code'] ?? 0) === 9) ? 'manual' : 'auto';

        foreach ((array)($inheritance['members'] ?? []) as $no => $row) {
            if (!is_array($row)) {
                continue;
            }

            $no = (int)$no;
            if ($no < 2 || $no > 10) {
                continue;
            }

            if (array_key_exists('taxable_manu', $row)) {
                $payload['id_taxable_manu'][$no] = $row['taxable_manu'];
            }
            if (array_key_exists('cash_share', $row)) {
                $payload['id_cash_share'][$no] = $row['cash_share'];
            }
            if (array_key_exists('other_share', $row)) {
                $payload['id_other_share'][$no] = $row['other_share'];
            }
        }

        foreach ((array)($inheritance['other_credit'] ?? []) as $no => $value) {
            $no = (int)$no;
            if ($no < 2 || $no > 10) {
                continue;
            }
            $payload['id_other_credit'][$no] = $value;
        }

        return $payload;
    }


    private function mergeInheritancePreviewRequestPayload(array $payload, Request $request): array
    {
        foreach (['input_mode', 'header_year', 'header_month', 'header_day', 'per', 'customer_name'] as $key) {
            if ($request->exists($key)) {
                $payload[$key] = $request->input($key);
            }
        }

        foreach ([
            'name',
            'relationship',
            'relationship_code',
            'civil_share_bunsi',
            'civil_share_bunbo',
            'bunsi',
            'bunbo',
            'twenty_percent_add',
            'tokurei_zouyo',
            'property',
            'cash',
            'id_taxable_manu',
            'id_cash_share',
            'id_other_share',
            'id_other_credit',
        ] as $key) {
            $value = $request->input($key);
            if (!is_array($value)) {
                continue;
        }

            foreach ($value as $idx => $v) {
                $payload[$key][$idx] = $v;
            }
        }

        return $payload;
    }

    private function buildInheritancePreviewPrefillFromPayload(array $payload, array $context): array
    {
        $header = (array)($context['prefillHeader'] ?? []);
        $family = (array)($context['prefillFamily'] ?? []);
        $inheritance = (array)($context['prefillInheritance'] ?? []);

        if (array_key_exists('customer_name', $payload)) {
            $header['customer_name'] = $payload['customer_name'];
        }
        if (array_key_exists('header_year', $payload)) {
            $header['year'] = $payload['header_year'];
        }
        if (array_key_exists('header_month', $payload)) {
            $header['month'] = $payload['header_month'];
        }
        if (array_key_exists('header_day', $payload)) {
            $header['day'] = $payload['header_day'];
        }
        if (array_key_exists('per', $payload)) {
            $header['per'] = $payload['per'];
        }

        for ($no = 1; $no <= 10; $no++) {
            if (!isset($family[$no]) || !is_array($family[$no])) {
                $family[$no] = [];
            }

            $map = [
                'name'               => 'name',
                'relationship_code'  => 'relationship_code',
                'civil_share_bunsi'  => 'civil_share_bunsi',
                'civil_share_bunbo'  => 'civil_share_bunbo',
                'bunsi'              => 'bunsi',
                'bunbo'              => 'bunbo',
                'property'           => 'property',
                'cash'               => 'cash',
                'twenty_percent_add' => 'twenty_percent_add',
                'tokurei_zouyo'      => 'tokurei_zouyo',
            ];

            foreach ($map as $payloadKey => $familyKey) {
                $arr = is_array($payload[$payloadKey] ?? null) ? $payload[$payloadKey] : [];
                if (array_key_exists($no, $arr)) {
                    $family[$no][$familyKey] = $arr[$no];
                }
            }
        }

        $inheritance['method_code'] = (($payload['input_mode'] ?? 'auto') === 'manual') ? 9 : 0;

        if (!isset($inheritance['members']) || !is_array($inheritance['members'])) {
            $inheritance['members'] = [];
        }
        if (!isset($inheritance['other_credit']) || !is_array($inheritance['other_credit'])) {
            $inheritance['other_credit'] = [];
        }

        for ($no = 2; $no <= 10; $no++) {
            if (!isset($inheritance['members'][$no]) || !is_array($inheritance['members'][$no])) {
                $inheritance['members'][$no] = [];
            }

            $taxableArr = is_array($payload['id_taxable_manu'] ?? null) ? $payload['id_taxable_manu'] : [];
            $cashArr    = is_array($payload['id_cash_share'] ?? null) ? $payload['id_cash_share'] : [];
            $otherArr   = is_array($payload['id_other_share'] ?? null) ? $payload['id_other_share'] : [];
            $creditArr  = is_array($payload['id_other_credit'] ?? null) ? $payload['id_other_credit'] : [];

            if (array_key_exists($no, $taxableArr)) {
                $inheritance['members'][$no]['taxable_manu'] = $taxableArr[$no];
            }
            if (array_key_exists($no, $cashArr)) {
                $inheritance['members'][$no]['cash_share'] = $cashArr[$no];
            }
            if (array_key_exists($no, $otherArr)) {
                $inheritance['members'][$no]['other_share'] = $otherArr[$no];
            }
            if (array_key_exists($no, $creditArr)) {
                $inheritance['other_credit'][$no] = $creditArr[$no];
            }
        }

        return [$header, $family, $inheritance];
    }



    /**
     * 遺産分割プレビュー計算用の heirs を、DB ではなく現在フォーム値ベースで組み立てる
     *
     * calc() では ProposalFamilyMember::toArray() を service に渡しているため、
     * ここでもできるだけ同じキー構成になるように揃える。
     */
    private function buildPreviewHeirsFromFamily(array $family): array
    {
        $heirs = [];

        for ($no = 2; $no <= 10; $no++) {
            $row = is_array($family[$no] ?? null) ? $family[$no] : [];

            $name = $this->strOrNull($row['name'] ?? null);
            if ($name === null) {
                continue;
            }

            $relationshipCode = $this->intOrNull($row['relationship_code'] ?? null);
            $shareNumerator   = $this->intOrNull($row['bunsi'] ?? ($row['share_numerator'] ?? null));
            $shareDenominator = $this->intOrNull($row['bunbo'] ?? ($row['share_denominator'] ?? null));
            $civilBunsi       = $this->strOrNull($row['civil_share_bunsi'] ?? null);
            $civilBunbo       = $this->strOrNull($row['civil_share_bunbo'] ?? null);
            $twentyPercent    = (int)((bool)($row['twenty_percent_add'] ?? ($row['surcharge_twenty_percent'] ?? 0)));
            $tokureiZouyo     = (int)((bool)($row['tokurei_zouyo'] ?? 0));
            $propertyThousand = $this->signedThousandOrNull($row['property'] ?? ($row['property_thousand'] ?? null));
            $cashThousand     = $this->toThousand($row['cash'] ?? ($row['cash_thousand'] ?? null));

            $heirCategory = array_key_exists('heir_category', $row)
                ? $this->intOrNull($row['heir_category'])
                : $this->mapHeirCategory($this->strOrNull($row['souzokunin'] ?? null));

            $heirs[] = [
                // ProposalFamilyMember に近いキー
                'row_no'                    => $no,
                'name'                      => $name,
                'gender'                    => $this->strOrNull($row['gender'] ?? null),
                'relationship_code'         => $relationshipCode,
                'adoption_note'             => $this->strOrNull($row['yousi'] ?? ($row['adoption_note'] ?? null)),
                'heir_category'             => $heirCategory,
                'civil_share_bunsi'         => $civilBunsi,
                'civil_share_bunbo'         => $civilBunbo,
                'share_numerator'           => $shareNumerator,
                'share_denominator'         => $shareDenominator,
                'surcharge_twenty_percent'  => $twentyPercent,
                'tokurei_zouyo'             => $tokureiZouyo,
                'birth_year'                => $this->intOrNull($row['birth_year'] ?? null),
                'birth_month'               => $this->intOrNull($row['birth_month'] ?? null),
                'birth_day'                 => $this->intOrNull($row['birth_day'] ?? null),
                'age'                       => $this->intOrNull($row['age'] ?? null),
                'property_thousand'         => $propertyThousand,
                'cash_thousand'             => $cashThousand,

                // Service 側が UI 互換キーを参照していても落ちないように同値で持たせる
                'relationship'              => $relationshipCode,
                'bunsi'                     => $shareNumerator,
                'bunbo'                     => $shareDenominator,
                'twenty_percent_add'        => $twentyPercent,
                'property'                  => $propertyThousand,
                'cash'                      => $cashThousand,
            ];
        }

        return $heirs;
    }



    private function buildInheritancePreviewResponse(
        array $before,
        array $prefillHeader,
        array $prefillFamily,
        array $prefillInheritance
    ): array {
        $summary   = is_array($before['summary'] ?? null) ? $before['summary'] : [];
        $heirsRows = is_array($before['heirs'] ?? null) ? $before['heirs'] : [];

        $heirsByIdx = [];
        foreach ($heirsRows as $row) {
            $idx = (int)($row['row_index'] ?? 0);
            if ($idx >= 2 && $idx <= 10) {
                $heirsByIdx[$idx] = is_array($row) ? $row : [];
            }
        }

        $mode = ((int)($prefillInheritance['method_code'] ?? 0) === 9) ? 'manual' : 'auto';

        $donorName = trim((string)(
            Arr::get($prefillFamily, '1.name', Arr::get($prefillHeader, 'customer_name', '')) ?? ''
        ));

        $basePropertyK = (int)($this->normalizePreviewKyenValue(Arr::get($prefillFamily, '1.property', 0)) ?? 0);
        $baseCashK     = (int)($this->normalizePreviewKyenValue(Arr::get($prefillFamily, '1.cash', 0)) ?? 0);
        $baseOtherK    = max(0, $basePropertyK - $baseCashK);

        $legalHeirCount = 0;
        for ($no = 2; $no <= 10; $no++) {
            $bunsi = (int)(Arr::get($prefillFamily, "{$no}.bunsi", $heirsByIdx[$no]['bunsi'] ?? 0) ?? 0);
            $bunbo = (int)(Arr::get($prefillFamily, "{$no}.bunbo", $heirsByIdx[$no]['bunbo'] ?? 0) ?? 0);
            if ($bunsi >= 1 && $bunbo >= 1) {
                $legalHeirCount++;
            }
        }

        $sumFieldYen = function (string $field) use ($heirsByIdx): int {
            $sum = 0;
            for ($i = 2; $i <= 10; $i++) {
                $sum += (int)($heirsByIdx[$i][$field] ?? 0);
            }
            return $sum;
        };

        $sumTwoTenthsYen = 0;
        $sumSettlementGiftYen = 0;
        $sumRawSubtotalYen = 0;
        $sumPayableYen = 0;
        $sumRefundYen = 0;
        $sumLifetimeGiftK = 0;
        $members = [];
        

        $taxableEstateTotalK = (int)round(((int)($summary['taxable_estate'] ?? 0)) / 1000);
        $taxableEstateShareKByHeir = [];
        for ($no = 2; $no <= 10; $no++) {
            $taxableEstateShareKByHeir[$no] = 0;
        }

        $gcd = function (int $a, int $b): int {
            $a = abs($a);
            $b = abs($b);
            while ($b !== 0) {
                $t = $a % $b;
                $a = $b;
                $b = $t;
            }
            return $a === 0 ? 1 : $a;
        };

        $lcm = function (int $a, int $b) use ($gcd): int {
            $a = abs($a);
            $b = abs($b);
            if ($a === 0 || $b === 0) {
                return 0;
            }
            return (int)($a / $gcd($a, $b) * $b);
        };

        $bunboLcm = 1;
        $targets = [];
        for ($no = 2; $no <= 10; $no++) {
            $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
            $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
            if ($bunsi >= 1 && $bunbo >= 1) {
                $targets[] = $no;
                $bunboLcm = $lcm($bunboLcm, $bunbo);
            }
        }

        $weights = [];
        $sumW = 0;
        foreach ($targets as $no) {
            $bunsi = (int)($heirsByIdx[$no]['bunsi'] ?? 0);
            $bunbo = (int)($heirsByIdx[$no]['bunbo'] ?? 0);
            $w = ($bunbo > 0) ? (int)($bunsi * ($bunboLcm / $bunbo)) : 0;
            $weights[$no] = $w;
            $sumW += $w;
        }

        if ($taxableEstateTotalK > 0 && $sumW > 0) {
            $allocatedK = 0;
            $lastNo = null;
            foreach ($targets as $no) {
                $w = (int)($weights[$no] ?? 0);
                if ($w <= 0) {
                    continue;
                }
                $lastNo = $no;
                $shareK = (int)floor($taxableEstateTotalK * $w / $sumW);
                $taxableEstateShareKByHeir[$no] = $shareK;
                $allocatedK += $shareK;
            }
            if ($lastNo !== null) {
                $taxableEstateShareKByHeir[$lastNo] += ($taxableEstateTotalK - $allocatedK);
            }
        }


        for ($no = 2; $no <= 10; $no++) {
            $hasName = trim((string)(Arr::get($prefillFamily, "{$no}.name", '') ?? '')) !== '';
            $calcRow = $heirsByIdx[$no] ?? [];
            $hasCalcRow = !empty($calcRow);

            $cashShareRaw = null;
            $otherShareRaw = null;
            $taxableManuRaw = null;

            if ($mode === 'manual') {

                $cashShareRaw   = $this->normalizePreviewKyenValue(
                    Arr::get($prefillInheritance, "members.{$no}.cash_share")
                );
                $otherShareRaw  = $this->normalizePreviewKyenValue(
                    Arr::get($prefillInheritance, "members.{$no}.other_share")
                );
                $taxableManuRaw = $this->normalizePreviewKyenValue(
                    Arr::get($prefillInheritance, "members.{$no}.taxable_manu")
                );

                if ($taxableManuRaw === null && ($cashShareRaw !== null || $otherShareRaw !== null)) {
                    $taxableManuRaw = (int)($cashShareRaw ?? 0) + (int)($otherShareRaw ?? 0);
                }
            } else {
                if ($hasCalcRow) {
                    $cashShareRaw   = (int)($calcRow['cash_share'] ?? 0);
                    $otherShareRaw  = (int)($calcRow['other_share'] ?? 0);
                    $taxableManuRaw = (int)($cashShareRaw ?? 0) + (int)($otherShareRaw ?? 0);
                }
            }

            $otherCreditRaw = $this->normalizePreviewKyenValue(
                Arr::get($prefillInheritance, "other_credit.{$no}")
            );

            $lifetimeGiftYen = $hasCalcRow ? (int)($calcRow['past_gift_included_yen'] ?? 0) : 0;
            $lifetimeGiftK = (int)round($lifetimeGiftYen / 1000);

            if ($hasName) {
                $sumLifetimeGiftK += $lifetimeGiftK;
            }

            $bunsi = (int)(Arr::get($prefillFamily, "{$no}.bunsi", $calcRow['bunsi'] ?? 0) ?? 0);
            $bunbo = (int)(Arr::get($prefillFamily, "{$no}.bunbo", $calcRow['bunbo'] ?? 0) ?? 0);
            $legalShareText = ($bunsi >= 1 && $bunbo >= 1) ? ($bunsi . '/' . $bunbo) : '';

            $twoTenthsYen = $hasCalcRow
                ? max(0, (int)($calcRow['final_tax_yen'] ?? 0) - (int)($calcRow['sanzutsu_tax_yen'] ?? 0))
                : 0;
            $settlementGiftYen = $hasCalcRow ? (int)($calcRow['settlement_gift_tax_yen'] ?? 0) : 0;
            $rawSubtotalYen = $hasCalcRow ? (int)($calcRow['raw_final_after_settlement_yen'] ?? 0) : 0;
            $payableTaxYen = $hasCalcRow ? (int)($calcRow['payable_tax_yen'] ?? 0) : 0;
            $refundTaxYen = $hasCalcRow ? (int)($calcRow['refund_tax_yen'] ?? 0) : 0;
            $creditTotalYen = $hasCalcRow
                ? (int)($calcRow['gift_tax_credit_calendar_yen'] ?? 0)
                    + (int)($calcRow['spouse_relief_yen'] ?? 0)
                    + (int)($calcRow['other_credit_yen'] ?? 0)
                : 0;

            if ($hasName) {
                $sumTwoTenthsYen      += $twoTenthsYen;
                $sumSettlementGiftYen += $settlementGiftYen;
                $sumRawSubtotalYen    += $rawSubtotalYen;
                $sumPayableYen        += $payableTaxYen;
                $sumRefundYen         += $refundTaxYen;
            }

            $members[$no] = [
                'has_name'                  => $hasName,
                'cash_share'                => $hasName ? $this->formatPreviewKyen($cashShareRaw) : '',
                'other_share'               => $hasName ? $this->formatPreviewKyen($otherShareRaw) : '',
                'taxable_manu'              => $hasName ? $this->formatPreviewKyen($taxableManuRaw) : '',
                'other_credit'              => $hasName ? $this->formatPreviewKyen($otherCreditRaw) : '',
                'lifetime_gift_addition'    => $hasName ? $this->formatPreviewKyen($lifetimeGiftK) : '',
                'taxable_total'             => $hasName ? $this->formatPreviewKyen((int)($taxableManuRaw ?? 0) + $lifetimeGiftK) : '',
                'taxable_estate_share'      => $hasName ? $this->formatPreviewKyen($taxableEstateShareKByHeir[$no] ?? 0, true) : '',                
                'legal_share_text'          => $hasName ? $legalShareText : '',
                'legal_tax'                 => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($calcRow['legal_tax_yen'] ?? 0) : '',
                'anbun_ratio'               => $hasName && $hasCalcRow ? number_format((float)($calcRow['anbun_ratio'] ?? 0), 4, '.', '') : '',
                'sanzutsu_tax'              => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($calcRow['sanzutsu_tax_yen'] ?? 0) : '',
                'two_tenths_amount'         => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($twoTenthsYen, true) : '',
                'gift_tax_credit_calendar'  => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($calcRow['gift_tax_credit_calendar_yen'] ?? 0) : '',
                'spouse_relief'             => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($calcRow['spouse_relief_yen'] ?? 0) : '',
                'credit_total'              => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($creditTotalYen, true) : '',
                'sashihiki_tax'             => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($calcRow['sashihiki_tax_yen'] ?? 0) : '',
                'settlement_gift_tax'       => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($settlementGiftYen) : '',
                'raw_subtotal'              => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($rawSubtotalYen) : '',
                'payable_tax'               => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($payableTaxYen) : '',
                'refund_tax'                => $hasName && $hasCalcRow ? $this->formatPreviewYenAsKyen($refundTaxYen) : '',
            ];
        }

        $legalHeirCount = 0;
        for ($no = 2; $no <= 10; $no++) {
            $bunsi = (int)(Arr::get($prefillFamily, "{$no}.bunsi", $heirsByIdx[$no]['bunsi'] ?? 0) ?? 0);
            $bunbo = (int)(Arr::get($prefillFamily, "{$no}.bunbo", $heirsByIdx[$no]['bunbo'] ?? 0) ?? 0);
            if ($bunsi >= 1 && $bunbo >= 1) {
                $legalHeirCount++;
            }
        }

        $basicDeductionYen = (int)($summary['basic_deduction'] ?? (30_000_000 + 6_000_000 * $legalHeirCount));
                 

        $basicDeductionFormulaLabel = trim((string)($summary['basic_deduction_formula_label'] ?? ''));
        if ($basicDeductionFormulaLabel === '') {
            $basicDeductionBaseKyen = (int)($summary['basic_deduction_base_kyen'] ?? 30000);
            $basicDeductionPerHeirKyen = (int)($summary['basic_deduction_per_heir_kyen'] ?? 6000);
            $basicDeductionFormulaLabel = number_format($basicDeductionBaseKyen) . '千円＋'
                . number_format($basicDeductionPerHeirKyen) . '千円× '
                . $legalHeirCount . '人';
        }


        $creditTotalYen = (int)($summary['total_gift_tax_credits'] ?? 0)
                        + (int)($summary['total_spouse_relief'] ?? 0)
                        + (int)($summary['total_other_credits'] ?? 0);

        return [
            'mode' => $mode,
            'left' => [
                'customer_name'            => $donorName,
                'cash_total'               => $this->formatPreviewKyen($baseCashK, true),
                'other_total'              => $this->formatPreviewKyen($baseOtherK, true),
                'property_total'           => $this->formatPreviewKyen($basePropertyK, true),
                'lifetime_gift_total'      => $this->formatPreviewKyen($sumLifetimeGiftK),
                'taxable_total_overall'    => $this->formatPreviewKyen($basePropertyK + $sumLifetimeGiftK),
                'basic_deduction_label'    => '基礎控除額　' . $basicDeductionFormulaLabel,
                'basic_deduction_amount'   => $this->formatPreviewYenAsKyen($basicDeductionYen),
                'taxable_estate'           => $this->formatPreviewYenAsKyen($summary['taxable_estate'] ?? 0),
                'anbun_ratio_total'        => '1.0000',
                'sozoku_tax_total'         => $this->formatPreviewYenAsKyen($summary['sozoku_tax_total'] ?? 0),
                'sanzutsu_total'           => $this->formatPreviewYenAsKyen($sumFieldYen('sanzutsu_tax_yen')),
                'two_tenths_total'         => $this->formatPreviewYenAsKyen($sumTwoTenthsYen, true),
                'gift_tax_credit_total'    => $this->formatPreviewYenAsKyen($summary['total_gift_tax_credits'] ?? 0),
                'spouse_relief_total'      => $this->formatPreviewYenAsKyen($summary['total_spouse_relief'] ?? 0),
                'other_credit_total'       => $this->formatPreviewYenAsKyen($summary['total_other_credits'] ?? 0),
                'credit_total'             => $this->formatPreviewYenAsKyen($creditTotalYen, true),
                'sashihiki_total'          => $this->formatPreviewYenAsKyen($summary['total_sashihiki_tax'] ?? 0),
                'settlement_credit_total'  => $this->formatPreviewYenAsKyen($sumSettlementGiftYen, true),
                'subtotal_total'           => $this->formatPreviewYenAsKyen($sumRawSubtotalYen),
                'payable_total'            => $this->formatPreviewYenAsKyen($sumPayableYen),
                'refund_total'             => $this->formatPreviewYenAsKyen($sumRefundYen),
            ],
            'members' => $members,
        ];
    }

    private function formatPreviewKyen($value, bool $blankZero = false): string
    {

        $int = $this->normalizePreviewKyenValue($value);
        if ($int === null) {
            return '';
        }

        if ($blankZero && $int === 0) {
            return '';
        }

        return number_format($int);
    }



    private function normalizePreviewKyenValue($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $s = preg_replace('/[^\d\-]/u', '', (string)$value);
        if ($s === '' || $s === '-' || $s === '+') {
            return null;
        }

        return (int)$s;
    }




    private function formatPreviewYenAsKyen($value, bool $blankZero = false): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $int = (int)round(((int)$value) / 1000);
        if ($blankZero && $int === 0) {
            return '';
        }

        return number_format($int);
    }





    /**
     * これからの贈与タブの初期表示データを返す（フロントの applyFuturePayload が読む形）
     * GET /zouyo/future/fetch?data_id=...&future_recipient_no=...
     */
    public function futureFetch(Request $request): JsonResponse
    {
        try {
            // --- 入力検証（data_id と recipient_no は必須） ---
            $v = validator($request->all(), [
                'data_id'            => ['required','integer','min:1'],
                'future_recipient_no'=> ['required','integer','min:1'],
            ]);
            if ($v->fails()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Invalid parameters',
                    'detail'=> $v->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $dataId = (int)$request->input('data_id');
            $rn     = (int)$request->input('future_recipient_no');

            // --- ヘッダ（贈与年月日）: 保存先が未確定でも空/old を返せばフロントは動く ---
            // 既存で ZouyoInput 等に保持している場合は取得して反映してください（例示は空）
            $header = [
                'year'  => null,
                'month' => null,
                'day'   => null,
            ];

            // --- plan（将来行のサーバ側保持がある場合のみ詰める。無ければ空） ---
            $plan = [
                'cal_amount'      => new \stdClass(), // 空 object（配列だと mapObjFormatted の既存実装でズレるため）
                'cal_basic'       => new \stdClass(),
                'cal_after_basic' => new \stdClass(),
                'cal_tax'         => new \stdClass(),
                'cal_cum'         => new \stdClass(),
                'set_amount'      => new \stdClass(),
                'set_basic110'    => new \stdClass(),
                'set_after_basic' => new \stdClass(),
                'set_after_25m'   => new \stdClass(),
                'set_tax20'       => new \stdClass(),
                'set_cum'         => new \stdClass(),
                'gift_month'      => new \stdClass(),
                'gift_day'        => new \stdClass(),
            ];

            // --- 過年度（past）: 可能なら DB から取得。無ければ空配列で返す ---
            $calendar = [];
            try {
                // 例: PastGiftCalendarEntry テーブルに (data_id, recipient_no, gift_yen, gift_year, gift_month, gift_day) がある想定
                $entries = PastGiftCalendarEntry::query()
                    ->where('data_id', $dataId)
                    ->where('amount_thousand', '>', 0) // ★ 過年度贈与がゼロの行は除外
                    ->where(function($q) use ($rn) {
                        if (Schema::hasColumn((new PastGiftCalendarEntry)->getTable(), 'recipient_no')) {
                            $q->where('recipient_no', $rn);
                        }
                    })
                    ->orderBy('gift_year')
                    ->orderBy('gift_month')
                    ->orderBy('gift_day')
                    ->get();


                foreach ($entries as $e) {
                    $k = (int)($e->amount_thousand ?? 0);
                    $calendar[] = [
                        'year'           => (int)($e->gift_year  ?? 0),
                        'month'          => (int)($e->gift_month ?? 0),
                        'day'            => (int)($e->gift_day   ?? 0),
                        'amount_yen'      => $k * 1000,
                        'amount_thousand' => $k,
                        'tax_thousand'    => (int)($e->tax_thousand ?? 0), // ✅ これを追加！
                
                    ];
                }
            } catch (\Throwable $e) {
                
                /*
                // モデル未連携でも 500 にしない
                Log::warning('futureFetch: calendar load failed: '.$e->getMessage());
                */
                
            }

            $past = [
                // applyFuturePayload は calendar / list / items / gifts / past_gifts / calendar_entries のいずれか配列を読める
                'calendar' => $calendar,
                // 表示用フォールバック合計（円/千円）を入れておくとクライアント側が拾う
                'giftAmountYen' => array_sum(array_map(fn($c)=> (int)$c['amount_yen'], $calendar)),
                'giftAmountK'   => array_sum(array_map(fn($c)=> (int)$c['amount_thousand'], $calendar)),
            ];

            // --- rekinen（年/月/日/贈与額(千円) の辞書配列）: PAST を再編 ---
            // フロントは {year:{idx:y}, month:{idx:m}, day:{idx:d}, zoyo:{idx:k}} 形式を許容
            $rekinen = [
                'year'  => new \stdClass(),
                'month' => new \stdClass(),
                'day'   => new \stdClass(),
                'zoyo'  => new \stdClass(), // 'zouyo' でも可だが片方で統一
                'kojo'  => new \stdClass(),   // ★ 追加
                'zouyo' => new \stdClass(),   // ★ 前方互換用に zoyo のコピー
                
            ];
            // 1..N 連番を付け直す（キーは文字列でも OK）
            foreach ($calendar as $i => $c) {
                $idx = (string)($i + 1);
                $rekinen['year']->{$idx}  = (int)$c['year'];
                $rekinen['month']->{$idx} = (int)$c['month'];
                $rekinen['day']->{$idx}   = (int)$c['day'];
                $rekinen['zoyo']->{$idx}  = (int)$c['amount_thousand']; // 千円
                $rekinen['zouyo']->{$idx} = (int)$c['amount_thousand'];
                $rekinen['kojo']->{$idx}  = isset($c['tax_thousand']) ? (int)$c['tax_thousand'] : null;

            }
            
            
            $rekinen['total'] = [
                'zoyo'  => array_sum(array_filter((array) $rekinen['zoyo'],  'is_numeric')),
                'zouyo' => array_sum(array_filter((array) $rekinen['zouyo'], 'is_numeric')),
                'kojo'  => array_sum(array_filter((array) $rekinen['kojo'],  'is_numeric')),
            ];

            
            // --- birth（受贈者の生年月日） ---
            $birth = null;
            try {
                // 例: PastGiftRecipient に (data_id, recipient_no, birth_year, birth_month, birth_day)
                $rec = PastGiftRecipient::query()
                    ->where('data_id', $dataId)
                    ->where(function($q) use ($rn) {
                        if (Schema::hasColumn((new PastGiftRecipient)->getTable(), 'recipient_no')) {
                            $q->where('recipient_no', $rn);
                        }
                    })
                    ->first();
                if ($rec) {
                    $birth = [
                        'year'  => (int)($rec->birth_year  ?? 0),
                        'month' => (int)($rec->birth_month ?? 0),
                        'day'   => (int)($rec->birth_day   ?? 0),
                    ];
                }
            } catch (\Throwable $e) {
                
                /*
                Log::info('futureFetch: birth not available: '.$e->getMessage());
                */
                
                
            }

            // --- 返却 ---
            return response()->json([
                'ok'          => true,
                'header'      => $header,
                'recipient_no'=> $rn,
                'plan'        => $plan,
                'past'        => $past,
                'rekinen'     => $rekinen,
                'birth'       => $birth,
            ], Response::HTTP_OK, [], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            
            /*
            Log::error('futureFetch failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            */
            
            
            
            // 500 を出さず、JSON で返す（フロントは graceful に表示できる）
            return response()->json([
                'ok'    => false,
                'error' => 'Internal error',
            ], Response::HTTP_OK);
        }
    }
 
    private function requestHasAnyFutureRows(Request $r): bool
    {
        for ($i=1;$i<=20;$i++){
            foreach (['cal_amount','cal_basic','cal_after_basic','cal_tax','cal_cum','set_amount','set_basic110','set_after_basic','set_after_25m','set_tax20','set_cum'] as $k){
                if ($r->has("{$k}.{$i}")) return true;
            }
        }
        return false;
    }
   



}