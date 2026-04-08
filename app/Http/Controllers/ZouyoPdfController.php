<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Pdf\Zouyo\ZouyoPdfGenerator;
use App\Services\Pdf\Zouyo\ZouyoA3PdfGenerator;
use Illuminate\Support\Facades\Log;
 

class ZouyoPdfController extends Controller
{
    public function __construct(
        private readonly ZouyoPdfGenerator $pdfGenerator,
        private readonly ZouyoA3PdfGenerator $a3PdfGenerator,        
    ) {}

    /**
     * チェックされたページをまとめてPDF出力する（フォームの「PDF作成」ボタン用）。
     */
    public function generateBySelection(Request $request)
    {



        $dataId = (int) (
            $request->input('data_id')
            ?? $request->query('data_id')
            ?? session('selected_data_id')
            ?? 0
        );
        abort_unless($dataId > 0, 422, 'data_id が指定されていません。');


        // ▼ 用紙サイズ
        $paperSize = strtoupper((string) $request->input('paper_size', 'A4'));
        if (!in_array($paperSize, ['A4', 'A3'], true)) {
            $paperSize = 'A4';
        }


        // ▼ 旧仕様の pages[] は A4 のフォールバックとして残す
        $legacyPages = (array) $request->input('pages', []);

        // ▼ 新仕様
        $pagesA4 = $this->normalizePages(
            (array) $request->input('pages_a4', $legacyPages),
            0,
            10
        );
        $pagesA3 = $this->normalizePages(
            (array) $request->input('pages_a3', []),
            0,
            9
        );


        $pages = $paperSize === 'A3' ? $pagesA3 : $pagesA4;

        // ★ PDF作成時の選択状態を保存
        //    - 既存キーは A4 用の後方互換として維持
        //    - 新仕様は paper_size / A4 / A3 をまとめて保存
        session()->put("zouyo.pdf_pages.{$dataId}", $pagesA4);
        session()->put("zouyo.pdf_state.{$dataId}", [
            'paper_size' => $paperSize,
            'pages_a4'   => $pagesA4,
            'pages_a3'   => $pagesA3,
         ]);
         

        if (empty($pages)) {
            return response('<pre>PDFにする項目が選択されていません。</pre>', 400)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        try {

            // ★ まずは Controller と Generator の整合性を優先し、2引数で統一して呼ぶ
            $pdfContent = $paperSize === 'A3'
                ? $this->a3PdfGenerator->generate($dataId, $pages)
                : $this->pdfGenerator->generate($dataId, $pages);

            /*
            // PDFが正常に生成されたか確認
            Log::debug('Generated PDF content size', ['bytes' => is_string($pdfContent) ? strlen($pdfContent) : null]);
            */
            
        } catch (\InvalidArgumentException $e) {
            // ★ 新規タブで読めるように HTML で返す
            return response('<pre>'.e($e->getMessage()).'</pre>', 400)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        } catch (\Throwable $e) {
            Log::error('[ZouyoPdfController] PDF generation failed', [
                'dataId'    => $dataId,
                'paperSize' => $paperSize,
                'pages'     => $pages,
                'error'     => $e->getMessage(),
            ]);
            return response('<pre>PDF生成に失敗しました: '.e($e->getMessage()).'</pre>', 500)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $filename = $paperSize === 'A3'
            ? 'zouyo_report_a3.pdf'
            : 'zouyo_report.pdf';

        
        
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');            
    }

    /**
     * ページ番号配列を整数化し、範囲内に正規化して重複排除する。
     *
     * @param  array<int,int|string> $pages
     * @return array<int,int>
     */
    private function normalizePages(array $pages, int $min, int $max): array
    {
        return array_values(array_unique(
            array_filter(
                array_map('intval', $pages),
                static fn (int $v) => $v >= $min && $v <= $max
            )
        ));
    }

}
