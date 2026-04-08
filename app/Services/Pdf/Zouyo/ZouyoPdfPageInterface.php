<?php

namespace App\Services\Pdf\Zouyo;

use TCPDF;

/**
 * 贈与レポートの各ページを描画するためのインターフェース。
 */
interface ZouyoPdfPageInterface
{
    /**
     * 1ページ分のコンテンツを TCPDF に描画する。
     *
     * 契約：
     * - 実装側で必ず $pdf->AddPage() を呼ぶこと
     * - フォント／余白は呼び出し側が設定済みとする（必要に応じて上書きOK）
     *
     * @param  TCPDF               $pdf     TCPDF インスタンス
     * @param  array<string,mixed> $payload 贈与計算結果や画面入力値などの集約
     */
    public function render(TCPDF $pdf, array $payload): void;
}
