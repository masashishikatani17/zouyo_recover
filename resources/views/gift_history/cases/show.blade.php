<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>贈与履歴管理 - 対象データ</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f5f7fb;
            color: #1f2937;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
        }

        .page {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            border: 1px solid #d9e2ef;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 22px;
        }

        h1 {
            margin: 0 0 14px;
            font-size: 24px;
        }

        .alert {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-weight: 700;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            width: 220px;
            background: #f8fafc;
            color: #334155;
        }

        .buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 7px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            line-height: 1.2;
            white-space: nowrap;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #fff;
        }

        .btn-muted {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .note {
            margin-top: 18px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            line-height: 1.7;
        }
    </style>
</head>

<body>
<div class="page">
    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <div class="card">
        <h1>贈与履歴管理データ</h1>

        <table>
            <tr>
                <th>氏名</th>
                <td>{{ $case->customer_name_snapshot ?? '（氏名未入力）' }}</td>
            </tr>
            <tr>
                <th>データ名・案件名</th>
                <td>{{ $case->data_name_snapshot ?? '（データ名なし）' }}</td>
            </tr>
            <tr>
                <th>既存データID</th>
                <td>{{ $case->data_id }}</td>
            </tr>
            <tr>
                <th>贈与明細件数</th>
                <td>{{ number_format($case->entries_count) }}件</td>
            </tr>
            <tr>
                <th>最終更新日</th>
                <td>{{ optional($case->updated_at)->format('Y/m/d H:i') }}</td>
            </tr>
        </table>

        <div class="note">
            画面0の第1実装では、ここまでが動作確認範囲です。<br>
            次の段階で、画面1：親族入力画面と、贈与名人からの親族初回取り込みを追加します。
        </div>

        <div class="buttons">
            <a href="{{ $backUrl }}" class="btn btn-muted">一覧に戻る</a>
            <a href="{{ route('gift-history.family.edit', $case) }}" class="btn btn-primary">親族入力へ</a>
            <a href="{{ route('gift-history.index') }}" class="btn btn-primary">贈与履歴一覧へ</a>
        </div>
    </div>
</div>
</body>
</html>