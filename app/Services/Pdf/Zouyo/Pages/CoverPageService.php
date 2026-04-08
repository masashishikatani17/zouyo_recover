<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 0: 表紙ページ
 */
class CoverPageService implements ZouyoPdfPageInterface
{

    
    public function render(TCPDF $pdf, array $payload): void
    {

        // テンプレートPDFのパス（例：resources/pdf/00_hyoshi.pdf）
        $templatePath = resource_path('/views/pdf/00_hyoshi.pdf');
        //$templatePath = resource_path('/views/pdf/01_pr_hajimeni.pdf');
        
        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        // 0. ページ追加（契約：各ページクラスが AddPage を呼ぶ）
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        // ★ DBから組み立てられたヘッダ情報（ProposalHeader 由来）を優先的に使用
        $header  = $payload['header']  ?? [];
        $summary = $payload['summary'] ?? [];

        $customerName = trim((string)($header['customer_name'] ?? $summary['target_name'] ?? ''));


        $planTitle    = (string)($header['title']         ?? '');

        // シミュレーション日付（doc_year が無ければ summary/year → 現在年の順でフォールバック）
        $docYear  = (int)($header['year']  ?? $summary['year'] ?? date('Y'));
        $docMonth = $header['month'] ?? null;
        $docDay   = $header['day']   ?? null;

        $proposerName = (string)($header['proposer_name'] ?? '');
        

    
        



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

        // 1. 顧客情報ブロック
        // 対象者(顧客)　氏名
        if ($customerName !== '' && !str_contains($customerName, '氏名未設定')) {
            $pdf->SetFont('mspgothic03', '', 20);
            $xx = 40;
            $yy = 30;
            $pdf->MultiCell(200, 10, $customerName . ' 様', $wakusen, 'L', 0, 0, $xx, $yy);
        }


        // 2. タイトル（中央）
        $pdf->SetFont('mspgothic03', '', 24);
        $pdf->SetTextColor(255,255,255);    //白
        $xx = 50;
        $yy = 73;
        $pdf->MultiCell(200, 10, $planTitle, $wakusen, 'C', 0, 0, $xx, $yy);


        $pdf->SetTextColor(0,0,0);          //黒

        // 3.作成日（ProposalHeader の doc_year/month/day があればそれを使う。
        //    無ければ今日の日付を、同じ固定座標へ表示する）
        $pdf->SetFont('mspgothic03', '', 18);
        $xx = 50;
        $yy = 120;
        $dateText = ($docYear && $docMonth && $docDay)
            ? sprintf('%d年%d月%d日', $docYear, $docMonth, $docDay)
            : date('Y年n月j日');
        $pdf->MultiCell(200, 10, $dateText, $wakusen, 'C', 0, 0, $xx, $yy);


        // 4.担当者名（任意）
        $pdf->SetFont('mspgothic03', '', 20);
        $xx = 50;
        $yy = 160;
        $pdf->MultiCell(200, 10, $proposerName, $wakusen, 'C', 0, 0, $xx, $yy);



    }

    
    
    
}
