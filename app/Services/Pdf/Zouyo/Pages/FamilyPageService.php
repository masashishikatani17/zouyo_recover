<?php

namespace App\Services\Pdf\Zouyo\Pages;

use App\Services\Pdf\Zouyo\ZouyoPdfPageInterface;
use TCPDF;

/**
 * 4: 家族構成、所有財産など
 */
class FamilyPageService implements ZouyoPdfPageInterface
{

    
    public function render(TCPDF $pdf, array $payload): void
    {

        // テンプレートPDFのパス（例：resources/pdf/04_pr_kazokukosei.pdf）
        $templatePath = resource_path('/views/pdf/04_pr_kazokukosei.pdf');

        // テンプレートの存在確認（無ければ例外）
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Family template not found: {$templatePath}");
        }

        $pdf->setSourceFile($templatePath);
        $tpl = $pdf->importPage(1);

        // 0. ページ追加（契約：各ページクラスが AddPage を呼ぶ）
        $pdf->AddPage();
        $pdf->useTemplate($tpl);

        $wakusen = 0;

        // payload からヘッダ・家族配列を取得
        $header = $payload['header'] ?? [];
        $family = $payload['family'] ?? [];


        // ▼ 家族表の描画
        //   - ざっくり：左上 (startX, startY) から rowHeight ずつずらしながら
        //   - 列位置は title.blade のレイアウトに合わせて手動で配置
        $startX    = 20;  // 表の左端
        $startY    = 76.5;  // 1 行目（番号1）の縦位置
        $rowHeight = 6.62;   // 行の高さ

        // 各列の X 座標（mm）をざっくり定義
        // 番号 / 氏名 / 性別 / 続柄 / 相続人区分 / 年齢 / 所有財産 / 金融資産
        $colX = [
            'no'       => $startX,
            'name'     => $startX +  8,
            'gender'   => $startX + 37,
            'rel'      => $startX + 48,
            'yousi'    => $startX + 65,
            'souzoku'  => $startX + 85,

            'civil_share'  => $startX + 112,
            'houtei_share' => $startX + 133,

            'birth_year'  => $startX + 148,
            'birth_month' => $startX + 155,
            'birth_day'   => $startX + 162,

            'age'      => $startX + 187,
            'prop'     => $startX + 198,
            'cash'     => $startX + 228,
        ];
        
        


        $relationships = config('relationships');


        $pdf->SetFont('mspgothic03', '',10);
        $wakusen = 0;
        $x = 255;
        $y = 190;
        $pdf->MultiCell(30, 6, '４ページ', $wakusen, 'R', 0, 0, $x, $y);


        $pdf->SetFont('mspgothic03', '', 9);
        $pdf->SetTextColor(0, 0, 0);


        // 行 1〜10 までをテンプレ上の表に重ねて描画
        for ($i = 1; $i <= 10; $i++) {
            $row = $family[$i] ?? null;
            if (!$row) {
                continue;
            }



            // 仕様：
            // 氏名が空欄の行は、続柄を含めて各項目を空欄にする
            $name = trim((string)($row['name'] ?? ''));
            $hasName = $name !== '';


            // 氏名
            $x = $colX['name'];
            $y = $startY + ($i - 1) * $rowHeight;
            $pdf->MultiCell(28, 10, $name, $wakusen, 'L', 0, 0, $x, $y);

            // 性別
            if ($hasName) {
                $x = $colX['gender'];
                $pdf->MultiCell(10, 10, (string) ($row['gender'] ?? ''), $wakusen, 'C', 0, 0, $x, $y);
            }

            // 続柄（relationship_code → ラベル）
            if ($hasName) {
                $x = $colX['rel'];
                $relCode = $row['relationship_code'] ?? null;
                $relLabel = $relCode !== null && array_key_exists($relCode, $relationships)
                    ? $relationships[$relCode]
                    : '';
                $relFontSize = $this->resolveRelationshipFontSize($relLabel);
                $pdf->SetFontSize($relFontSize);
                $pdf->MultiCell(18, 10, $relLabel, $wakusen, 'L', 0, 0, $x, $y);
                $pdf->SetFontSize(9);
             }
             
             

            // 養子縁組
            if ($hasName) {
                $x = $colX['yousi'];
                $pdf->MultiCell(20, 10, (string) ($row['yousi'] ?? ''), $wakusen, 'L', 0, 0, $x, $y);
            }


            // 相続人区分（文字列そのまま）
            if ($hasName) {
                $x = $colX['souzoku'];
                $pdf->MultiCell(30, 10, (string) ($row['souzokunin'] ?? ''), $wakusen, 'L', 0, 0, $x, $y);
            }


            // 民法上の法定相続割合
            if ($hasName && (($row['civil_share_bunsi'] ?? null) !== null)) {
                $x = $colX['civil_share'];
                $pdf->MultiCell(20, 10, (string) ($row['civil_share_bunsi'] ?? '') . '/' . (string) ($row['civil_share_bunbo'] ?? ''), $wakusen, 'C', 0, 0, $x, $y);
            }


            // 税法上の法定相続割合
            if ($hasName && (($row['bunsi'] ?? null) !== null)) {
                $x = $colX['houtei_share'];
                $pdf->MultiCell(20, 10, (string) ($row['bunsi'] ?? '') . '/' . (string) ($row['bunbo'] ?? ''), $wakusen, 'C', 0, 0, $x, $y);
            }


            // 生年月日　年
            if ($hasName && (($row['birth_year'] ?? null) !== null)) {
                $x = $colX['birth_year'];
                $pdf->MultiCell(20, 10, (string) ($row['birth_year'] ?? '') . '年', $wakusen, 'R', 0, 0, $x, $y);
            }

            // 生年月日　月
            if ($hasName && (($row['birth_month'] ?? null) !== null)) {
                $x = $colX['birth_month'];
                $pdf->MultiCell(20, 10, (string) ($row['birth_month'] ?? '') . '月', $wakusen, 'R', 0, 0, $x, $y);
            }

            // 生年月日　日
            if ($hasName && (($row['birth_day'] ?? null) !== null)) {
                $x = $colX['birth_day'];
                $pdf->MultiCell(20, 10, (string) ($row['birth_day'] ?? '') . '日', $wakusen, 'R', 0, 0, $x, $y);
            }



            // 年齢
            $age = $row['age'] ?? null;
            if ($hasName && $age !== null) {
                $x = $colX['age'];
                $pdf->MultiCell(10, 10, (string) $age . '歳', $wakusen, 'L', 0, 0, $x, $y);
            }

            // 所有財産（千円）
            $prop = $row['property'] ?? null;

            if ($hasName && $prop !== null) {
                $x = $colX['prop'];
                $pdf->MultiCell(30, 10, number_format((int) $prop) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }

            // 金融資産（千円）
            $cash = $row['cash'] ?? null;
            
            if ($hasName && $cash !== null) {
                $x = $colX['cash'];
                $pdf->MultiCell(30, 10, number_format((int) $cash) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }
        }

        $pdf->SetFont('mspgothic03', '', 9);

        // ▼ 合計行（property[110], cash[110] 相当）も header から分かる範囲で描画
        $totalProp = $header['property_110'] ?? null;
        $totalCash = $header['cash_110']     ?? null;
        if ($totalProp !== null || $totalCash !== null) {
            // 合計行の Y は 11 行目
            $y = $startY + 10 * $rowHeight + 0;

            if ($totalProp !== null) {
                $x = $colX['prop'];
                $pdf->MultiCell(30, 10, number_format((int) $totalProp) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }
            if ($totalCash !== null) {
                $x = $colX['cash'];
                $pdf->MultiCell(30, 10, number_format((int) $totalCash) . ' 千円', $wakusen, 'R', 0, 0, $x, $y);
            }
        }

    }

    
    private function resolveRelationshipFontSize(string $label): float
    {
        $label = trim($label);
        if ($label === '') {
            return 9.0;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($label, 'UTF-8')
            : strlen($label);

        return match (true) {
            $length <= 3 => 9.0,
            $length === 4 => 8.5,
            $length === 5 => 8.0,
            $length === 6 => 7.0,
            $length === 7 => 6.5,
            default      => 6.0,
        };
    }
         
    
}
