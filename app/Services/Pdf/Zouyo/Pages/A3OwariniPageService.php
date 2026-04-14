<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 1: はじめに
 */
class A3OwariniPageService implements ZouyoPdfPageInterface
{

    
    public function render(TCPDF $pdf, array $payload): void
    {

        // ★ 現段階では既存テンプレートを使用
        //    A3専用テンプレートPDFができたら、このパスを差し替えてください
        $templatePath = resource_path('/views/pdf/A3_09_pr_owarini.pdf');
        

        if (!is_file($templatePath)) {
            throw new \RuntimeException('A3OwariniPageService: template not found: ' . $templatePath);
        }

        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tpl);        
        

        // ★ テンプレート実サイズに合わせてページ追加
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

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
        $pdf->SetFont('mspgothic03', '', 10);

        $pdf->MultiCell(
            $pageLabelW,
            $pageLabelH,
            '(10ページ)',
            $wakusen,
            'R',
            0,
            0,
            $x,
            $y
         );

     }



 }