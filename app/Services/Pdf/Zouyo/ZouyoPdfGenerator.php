<?php

namespace App\Services\Pdf\Zouyo;

use setasign\Fpdi\Tcpdf\Fpdi;
use App\Models\ProposalHeader;
use App\Models\ProposalFamilyMember;
use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use App\Services\Pdf\Zouyo\Pages\CoverPageService;
use App\Services\Pdf\Zouyo\Pages\HajimeniPageService;
use App\Services\Pdf\Zouyo\Pages\KazeihikakuintroPageService;
use App\Services\Pdf\Zouyo\Pages\SokusanhyouPageService;
use App\Services\Pdf\Zouyo\Pages\FamilyPageService;
use App\Services\Pdf\Zouyo\Pages\KakujinZouyoPageService;
use App\Services\Pdf\Zouyo\Pages\SouzokukazeikakakuPageService;
use App\Services\Pdf\Zouyo\Pages\KakujinsouzokuPageService;
use App\Services\Pdf\Zouyo\Pages\ZouyosetuzeihikakuPageService;
use App\Services\Pdf\Zouyo\Pages\KakujinzaisansuiiPageService;
use App\Services\Pdf\Zouyo\Pages\OwariniPageService;


/**
 * 贈与レポートPDF（A4版）を組み立てるジェネレータ。
 *
 * A4ページ構成:
 *  0: 表紙
 *  1: はじめに
 *  2: 暦年課税と相続時精算課税の比較
 *  3: 贈与税、相続税の速算表
 *  4: 家族構成、所有財産など
 *  5: 各人別贈与額および贈与税
 *  6: 贈与後の相続税の課税価格
 *  7: 各人別相続税額の試算
 *  8: 贈与による節税効果の時系列比較
 *  9: 対策後の各人別財産の推移
 * 10: おわりに
*/

class ZouyoPdfGenerator
{
    /**
     * ページID → ページ描画サービスの対応表
     *
     * @var array<string,string>
     */
    private array $pageServiceMap = [
        // 0: 表紙
        '0' => CoverPageService::class,
        '1' => HajimeniPageService::class,
        '2' => KazeihikakuintroPageService::class,
        '3' => SokusanhyouPageService::class,
        '4' => FamilyPageService::class,
        '5' => KakujinZouyoPageService::class,
        '6' => SouzokukazeikakakuPageService::class,
        '7' => KakujinsouzokuPageService::class,
        '8' => ZouyosetuzeihikakuPageService::class,
        '9' => KakujinzaisansuiiPageService::class,
        '10' => OwariniPageService::class,
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
        // 1. ページIDを 0〜10 の整数に正規化
        $pageIds = $this->normalizePageIds($pageIds);

        if (empty($pageIds)) {
            throw new \InvalidArgumentException('PDFにする項目が選択されていません。');
        }

        // 2. 表紙などで使う共通 payload を構築
        $payload = $this->buildPayload($dataId);

        // 3. FPDI(TCPDF拡張) インスタンス生成（A4横）
        //    → setSourceFile()/importPage() が使えるようになる
        $pdf = new Fpdi('L', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Laravel TCPDF');
        $pdf->SetAuthor('Zouyo App');
        $pdf->SetTitle('最適贈与プランナー');

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
     * ページIDの正規化（0〜10 の整数に限定・重複削除）。
     *
     * @param  array<int,int|string> $pageIds
     * @return array<int,int>
     */
    private function normalizePageIds(array $pageIds): array
    {
        $pageIds = array_map('intval', $pageIds);
        $pageIds = array_filter($pageIds, fn (int $v) => $v >= 0 && $v <= 10);
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
