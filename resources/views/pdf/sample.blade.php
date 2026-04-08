<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <style>
    /* DomPDFは外部CSSより埋め込みが安定。IPAexを明示 */
    @page { margin: 18mm 16mm; }
    body {
      font-family: 'ipaexg', 'ipaexm', sans-serif;
      font-size: 12pt;
      line-height: 1.5;
    }
    h1 {
      font-size: 16pt;
      margin: 0 0 8mm;
      border-bottom: 1px solid #333;
      padding-bottom: 4mm;
    }
    .meta {
      font-size: 10pt;
      margin-bottom: 8mm;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      border: 0.3mm solid #000;
      padding: 2mm 3mm;
      text-align: left;
      vertical-align: top;
      font-size: 10pt;
    }
    th {
      background: #f0f0f0;
      width: 30%;
      white-space: nowrap;
    }
  </style>
</head>
<body>
  <h1>{{ $title }}</h1>
  <div class="meta">生成時刻：{{ $now }}</div>
  <p>これは日本語フォント（IPAex）を使ったPDFのサンプルです。文字化けせず表示されれば設定は問題ありません。</p>
  <table>
    <tr><th>会社名</th><td>株式会社ぞうよ</td></tr>
    <tr><th>説明</th><td>寄付・贈与に関する帳票を出力するサンプル。A4縦、12pt 基準。</td></tr>
  </table>
</body>
</html>