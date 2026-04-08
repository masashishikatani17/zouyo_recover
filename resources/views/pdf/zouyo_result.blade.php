@php
    // Controller 側から渡してもらう想定
    // $results = [
    //   'summary' => [...],
    //   'details' => [...],
    // ];
    $summary = $results['summary'] ?? [];
    $details = $results['details'] ?? [];
@endphp

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-size: 10pt;
        }
        h1 {
            font-size: 14pt;
            text-align: center;
            margin-bottom: 10px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 3px;
        }
        th {
            background-color: #f0f0f0;
        }
        .text-right {
            text-align: right;
        }
        .mt-2 {
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <h1>贈与シミュレーション結果</h1>

    <table>
        <tr>
            <th>対象者</th>
            <td>{{ $summary['target_name'] ?? '（未設定）' }}</td>
        </tr>
        <tr>
            <th>シミュレーション年度</th>
            <td>{{ $summary['year'] ?? '—' }}年</td>
        </tr>
        <tr>
            <th>合計贈与額</th>
            <td class="text-right">
                {{ number_format($summary['total_gift'] ?? 0) }} 円
            </td>
        </tr>
        <tr>
            <th>想定贈与税額</th>
            <td class="text-right">
                {{ number_format($summary['gift_tax'] ?? 0) }} 円
            </td>
        </tr>
    </table>

    <p class="mt-2">【年別の推移】</p>

    <table>
        <tr>
            <th>年</th>
            <th>贈与額</th>
            <th>贈与税額</th>
            <th>贈与後資産残高</th>
        </tr>
        @foreach($details as $row)
            <tr>
                <td class="text-right">{{ $row['year_label'] ?? '' }}</td>
                <td class="text-right">
                    {{ number_format($row['gift_amount'] ?? 0) }}
                </td>
                <td class="text-right">
                    {{ number_format($row['gift_tax'] ?? 0) }}
                </td>
                <td class="text-right">
                    {{ number_format($row['asset_after'] ?? 0) }}
                </td>
            </tr>
        @endforeach
    </table>
    
    ㎡　㈱　㈲　①　②　③　④　⑤　★　☆　　
    
</body>
</html>
