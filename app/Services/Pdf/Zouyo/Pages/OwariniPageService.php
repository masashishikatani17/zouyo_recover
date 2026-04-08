<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 1: はじめに
 */
class OwariniPageService implements ZouyoPdfPageInterface
{

    
    public function render(TCPDF $pdf, array $payload): void
    {

        // テンプレートPDFのパス（例：resources/pdf/00_hyoshi.pdf）
        //$templatePath = resource_path('/views/pdf/00_hyoshi.pdf');
        $templatePath = resource_path('/views/pdf/10_pr_owarini.pdf');
        
        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        // 0. ページ追加（契約：各ページクラスが AddPage を呼ぶ）
        $pdf->AddPage();
        $pdf->useTemplate($tpl);


        $pdf->SetFont('mspgothic03', '',10);
        $wakusen = 0;
        $x = 255;
        $y = 190;
        $pdf->MultiCell(30, 6, '(10ページ)', $wakusen, 'R', 0, 0, $x, $y);

        /*
        //外枠ボックス
        $pdf->SetLineStyle(["width"=>0.8,"cap"=>"round","join"=>"round","color"=>[0,0,0]]);//RGB
        $pdf->Rect(
            20,  //左上x
            10,  //左上y
            260,   //幅
            190,   //高さ
            'D'  //'D':周りの線のみ描画 'F':塗りつぶしのみ描画 'DF':線+塗りつぶし
        );
        
        //タイトルボックス
        $pdf->SetLineStyle(["width"=>1,"cap"=>"round","join"=>"round","color"=>[0,112,192]]);//RGB
        //塗りつぶしの設定
        $pdf->SetFillColor(0,112,192);//RGB　R:0 G:191 B:255
        //矩形を出力
        $pdf->Rect(
            50,  //左上x
            60,  //左上y
            200,   //幅
            30,   //高さ
            'DF'  //'D':周りの線のみ描画 'F':塗りつぶしのみ描画 'DF':線+塗りつぶし
        );
        */
  
        $wakusen = 0;

    }

    
    
    
}
