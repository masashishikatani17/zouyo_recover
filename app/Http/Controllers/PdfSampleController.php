<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class PdfSampleController extends Controller
{
    public function show(): Response
    {
        // Blade をPDF化（フォントは CSS 側で ipaex を指定）
        $pdf = Pdf::loadView('pdf.sample', [
            'title' => 'ZOUYO PDF サンプル',
            'now'   => now()->format('Y-m-d H:i:s'),
        ])->setPaper('A4', 'portrait');

        // inline 表示（保存したい場合は ->download('sample.pdf')）
        return new Response($pdf->stream('sample.pdf'), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}