<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 3: 速算表
 */
class A3KazeihikakuintroPageService implements ZouyoPdfPageInterface
{

    
    public function render(TCPDF $pdf, array $payload): void
    {

        // テンプレートPDFのパス（例：resources/pdf/00_hyoshi.pdf）
        //$templatePath = resource_path('/views/pdf/00_hyoshi.pdf');
        $templatePath = resource_path('/views/pdf/A3_02_pr_rekisouhikaku.pdf');
        
        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);

        // 0. ページ追加（契約：各ページクラスが AddPage を呼ぶ）
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        $pdf->SetFont('mspgothic03', '',10);
        $wakusen = 0;


        //ページ番号印刷
        // ★ 右下ページ番号は用紙サイズ依存の固定値ではなく、
        //    テンプレートサイズから相対計算する
        $pageLabelW   = 30;
        $pageLabelH   =  6;
        $rightMargin  = 20;
        $bottomMargin = 20;

        $x = max(0, $size['width']  - $pageLabelW - $rightMargin );
        $y = max(0, $size['height'] - $pageLabelH - $bottomMargin);

        //$size['width'] . ' - ' . $pageLabelW . ' - ' . $rightMargin . '  ' . $size['height'] . ' - ' . $pageLabelH . ' - ' . $bottomMargin . '  (1ページ)',

        $pdf->MultiCell(
            $pageLabelW,
            $pageLabelH,
            '(2ページ)',
            $wakusen,
            'R',
            0,
            0,
            $x,
            $y
         );

    }

    
    
    
}
