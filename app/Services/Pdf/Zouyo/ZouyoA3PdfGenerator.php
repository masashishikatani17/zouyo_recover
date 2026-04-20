<?php

namespace App\Services\Pdf\Zouyo;

use setasign\Fpdi\Tcpdf\Fpdi;
use App\Models\ProposalHeader;
use App\Models\ProposalFamilyMember;
use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Services\Pdf\Zouyo\Pages\A3CoverPageService;
use App\Services\Pdf\Zouyo\Pages\A3HajimeniPageService;
use App\Services\Pdf\Zouyo\Pages\A3KazeihikakuintroPageService;
use App\Services\Pdf\Zouyo\Pages\A3KakuzoyoPlanPageService;
use App\Services\Pdf\Zouyo\Pages\A3FamilyGiftPlanPageService;
use App\Services\Pdf\Zouyo\Pages\A3KakujinZouyoPageService;
use App\Services\Pdf\Zouyo\Pages\A3SouzokukazeikakakuPageService;
use App\Services\Pdf\Zouyo\Pages\A3ZouyoGenzeikokaPageService;
use App\Services\Pdf\Zouyo\Pages\A3SouzokuninzaisansuiiPageService;
use App\Services\Pdf\Zouyo\Pages\A3KakujinzaisansuiiPageService;
use App\Services\Pdf\Zouyo\Pages\A3OwariniPageService;


/**
 * 贈与レポートPDF（A3版）を組み立てるジェネレータ。
 *
 * A3ページ構成:
 *  0: 表紙
 *  1: はじめに
 *  2: 比較説明
 *  3: 家族構成贈与プラン
 *  4: 各人別贈与額
 *  5: 各人別贈与額
 *  6: （廃止）
 *  7: 贈与後の相続税
 *  8: 贈与による減税効果
 *  9: 相続人別財産の推移
 * 10: 各人別財産の推移
 * 11: おわりに
*/
class ZouyoA3PdfGenerator
{
    /**
     * ページID → ページ描画サービスの対応表
     *
     * @var array<string,string>
     */
    private array $pageServiceMap = [
        // 0: 表紙
        '0' => A3CoverPageService::class,
        '1' => A3HajimeniPageService::class,
        '2' => A3KazeihikakuintroPageService::class,
        '3' => A3FamilyGiftPlanPageService::class,
        '4' => A3KakuzoyoPlanPageService::class,
        '5' => A3KakujinZouyoPageService::class,
        '7' => A3SouzokukazeikakakuPageService::class,
        '8' => A3ZouyoGenzeikokaPageService::class,
        '9' => A3SouzokuninzaisansuiiPageService::class,
        '10' => A3KakujinzaisansuiiPageService::class,
        '11' => A3OwariniPageService::class,
    ];

    public function __construct(
        // 今後 UseCase や Repository を注入する場合はここに追記
    ) {
    }

    /**
     * 選択されたページ構成で PDF バイナリを生成する。
     *
     * @param  int                   $dataId   対象データID（今は未使用だが将来 payload 構築に利用）
     * @param  array<int,int|string> $pageIds  POST された pages[] の値
     * @return string                           PDFバイナリ
     */
    public function generate(int $dataId, array $pageIds): string
    {
        // 1. ページIDを 0〜11 の整数に正規化        
        $pageIds = $this->normalizePageIds($pageIds);

        if (empty($pageIds)) {
            throw new \InvalidArgumentException('PDFにする項目が選択されていません。');
        }

        // 2. 表紙などで使う共通 payload を構築
        $payload = $this->buildPayload($dataId);

        // 3. FPDI(TCPDF拡張) インスタンス生成（A3横）
        //    → setSourceFile()/importPage() が使えるようになる
        $pdf = new Fpdi('L', 'mm', 'A3', true, 'UTF-8', false);        

        $pdf->SetCreator('Laravel TCPDF');
        $pdf->SetAuthor('Zouyo App');
        $pdf->SetTitle('最適贈与額計算システム"贈与名人"');

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pdf->SetMargins(10, 10, 10);
        $pdf->SetFooterMargin(5);
        $pdf->SetAutoPageBreak(true, 5);
        

        // 日本語フォント（fontconv で登録した MS P 明朝）
        $pdf->setFontSubsetting(true);
        $pdf->SetFont('mspmincho02', '', 10);

        // 4. 各ページサービスを順番に呼び出し
        foreach ($pageIds as $pageId) {
            $key         = (string) $pageId;
            $serviceClass = $this->pageServiceMap[$key] ?? null;
            if ($serviceClass === null) {
                // まだ実装していないページIDはスキップ（0=表紙だけ描画）
                continue;
            }

            /** @var ZouyoPdfPageInterface $service */
            $service = app($serviceClass);
            
            /*
            \Log::info('Calling PDF page service', [
                'page_id' => $key,
                'class'   => $serviceClass,
            ]);
            */

            $service->render($pdf, $payload);
        }

        // 5. PDFバイナリを文字列として返す
        return $pdf->Output('zouyo_report.pdf', 'S');
    }

    /**
     * ページIDの正規化（現在有効なページIDのみに限定・重複削除）。     
     * @param  array<int,int|string> $pageIds
     * @return array<int,int>
     */
    private function normalizePageIds(array $pageIds): array
    {
        $pageIds = array_map('intval', $pageIds);
        $allowedPageIds = array_map('intval', array_keys($this->pageServiceMap));
        $pageIds = array_filter($pageIds, fn (int $v) => in_array($v, $allowedPageIds, true));
        $pageIds = array_values(array_unique($pageIds));

        return $pageIds;
    }


    /**
     * PDFページで利用する共通 payload を構築する。
     *
     * - ProposalHeader を data_id から取得して header 配列を構成
     * - CoverPageService 互換の summary も併せて用意する
     */
    private function buildPayload(int $dataId): array
    {
        // DBから提案書ヘッダデータを取得
        $ph = ProposalHeader::query()
            ->where('data_id', $dataId)
            ->first();

        // 取得したデータを配列にセット（無ければ空配列のまま）
        $header = [];
        if ($ph) {
            $header = [
                'customer_name' => $ph->customer_name,
                'title'         => $ph->title,
                'year'          => $ph->doc_year,
                'month'         => $ph->doc_month,
                'day'           => $ph->doc_day,
                'proposer_name' => $ph->proposer_name,
                // 合計欄も payload 側に持たせておく（title.blade と同じ意味）
                'per'           => $ph->after_tax_yield_percent,
                'property_110'  => $ph->property_total_thousand,
                'cash_110'      => $ph->cash_total_thousand,
                'asset_input_mode' => in_array((string)($ph->asset_input_mode ?? ''), ['split', 'combined'], true)
                    ? (string)$ph->asset_input_mode
                    : 'split',                
                
            ];
        }


        // ▼ 家族構成（1..10行）も payload に乗せる（ZouyoController::makeInputContext と同等イメージ）
        $family = [];
        $rows = ProposalFamilyMember::query()
            ->where('data_id', $dataId)
            ->orderBy('row_no')
            ->get();

        foreach ($rows as $r) {
            $i = (int) $r->row_no;
            if ($i < 1 || $i > 10) {
                continue;
            }
            $family[$i] = [
                'name'              => $r->name,
                'gender'            => $r->gender,
                'relationship_code' => $r->relationship_code,
                'yousi'             => $r->adoption_note,
                'souzokunin'        => match ($r->heir_category) {
                    0       => '被相続人',
                    1       => '法定相続人',
                    2       => '法定相続人以外',
                    default => null,
                },
                'civil_share_bunsi' => $r->civil_share_bunsi,
                'civil_share_bunbo' => $r->civil_share_bunbo,
                'bunsi'             => $r->share_numerator,
                'bunbo'             => $r->share_denominator,
                'twenty_percent_add'=> (int) $r->surcharge_twenty_percent,
                'tokurei_zouyo'     => (int) $r->tokurei_zouyo,
                'birth_year'        => $r->birth_year,
                'birth_month'       => $r->birth_month,
                'birth_day'         => $r->birth_day,
                'age'               => $r->age,
                'property'          => $r->property_thousand,
                'cash'              => $r->cash_thousand,
            ];
        }


        // CoverPageService 用の summary（フォールバック込み）
        $summary = [
            'target_name' => $header['customer_name'] ?? '（氏名未設定）',
            'year'        => $header['year']          ?? (int) date('Y'),
        ];

        return [
            // ★ 各ページサービス側で DB にアクセスしたいので data_id も渡しておく
            'data_id'     => $dataId,
            'header'      => $header,
            'summary'     => $summary,
            'family'      => $family,
            // 事務所名は config などから取得（無ければダミー）
            'office_name' => config('app.office_name', '（事務所名）'),
        ];
    }
    
    
    
    
}
