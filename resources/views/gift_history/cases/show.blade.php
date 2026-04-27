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
        

        .entries-section {
            margin-top: 22px;
        }

        .entries-section h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }

        .entries-table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
        }

        .entries-table {
            min-width: 1260px;
            margin-top: 0;
        }

        .entries-table th,
        .entries-table td {
            width: auto;
            padding: 8px 7px;
            white-space: nowrap;
            font-size: 13px;
        }

        .entries-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 12px;
            text-align: left;
        }

        .entries-table .number {
            text-align: right;
        }

        .entries-table .deadline {
            line-height: 1.6;
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            background: #eef2ff;
            color: #1e3a8a;
            font-weight: 700;
            font-size: 12px;
        }

        .badge-manual {
            background: #fff1f2;
            color: #9f1239;
        }

        .empty {
            padding: 20px 10px;
            text-align: center;
            color: #6b7280;
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


        @php
            $taxationLabels = [
                'calendar' => '暦年贈与',
                'settlement' => '相続時精算課税',
            ];

            $calendarTaxLabels = [
                'general' => '一般贈与',
                'tokurei' => '特例贈与',
            ];

            $taxReturnLabels = [
                'not_required' => '申告不要',
                'planned' => '申告予定',
                'filed' => '申告済み',
                'unknown' => '未確認',
            ];

            $contractLabels = [
                'yes' => '有',
                'no' => '無',
                'unknown' => '未確認',
            ];
        @endphp

        <div class="entries-section">
            <h2>登録済み生前贈与明細</h2>

            <div class="entries-table-wrap">
                <table class="entries-table">
                    <thead>
                    <tr>
                        <th class="number">No</th>
                        <th>贈与日</th>
                        <th>贈与形態</th>
                        <th>税率区分</th>
                        <th>贈与者</th>
                        <th>贈与者の続柄</th>
                        <th>受贈者</th>
                        <th>受贈者の続柄</th>
                        <th>財産種類</th>
                        <th>財産名</th>
                        <th class="number">贈与額</th>
                        <th>生前贈与加算期限</th>
                        <th class="number">贈与税額</th>
                        <th>申告</th>
                        <th>契約書</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="number">{{ $loop->iteration }}</td>
                            <td>{{ optional($entry->gift_date)->format('Y/m/d') }}</td>
                            <td>{{ $taxationLabels[$entry->gift_taxation_type] ?? $entry->gift_taxation_type }}</td>
                            <td>
                                @if ($entry->gift_taxation_type === 'calendar')
                                    {{ $calendarTaxLabels[$entry->calendar_tax_type] ?? '未選択' }}
                                @else
                                    相続時精算課税
                                @endif
                            </td>
                            <td>{{ $entry->donor_name_snapshot }}</td>
                            <td>{{ $entry->donor_relationship_from_recipient }}</td>
                            <td>{{ $entry->recipient_name_snapshot }}</td>
                            <td>{{ $entry->recipient_relationship_from_donor }}</td>
                            <td>{{ $entry->asset_category_name_snapshot }}</td>
                            <td>{{ $entry->asset_name ?: '—' }}</td>
                            <td class="number">{{ number_format((int) $entry->gift_amount_yen) }}円</td>
                            <td class="deadline">
                                @if ($entry->gift_taxation_type === 'calendar')
                                    3年：{{ optional($entry->addback_3year_deadline_date)->format('Y/m/d') }}<br>
                                    最終：{{ optional($entry->addback_final_deadline_date)->format('Y/m/d') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="number">
                                {{ number_format((int) $entry->tax_final_amount_yen) }}円
                                @if ($entry->tax_override_enabled)
                                    <span class="badge badge-manual">手入力</span>
                                @endif
                            </td>
                            <td>{{ $taxReturnLabels[$entry->tax_return_status] ?? '—' }}</td>
                            <td>{{ $contractLabels[$entry->gift_contract_status] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="empty">登録済みの生前贈与明細はありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="buttons">
            <a href="{{ $backUrl }}" class="btn btn-muted">一覧に戻る</a>
            <a href="{{ route('gift-history.family.edit', $case) }}" class="btn btn-primary">親族入力へ</a>
            <a href="{{ route('gift-history.entries.create', $case) }}" class="btn btn-primary">生前贈与登録へ</a>            
            <a href="{{ route('gift-history.index') }}" class="btn btn-primary">贈与履歴一覧へ</a>
        </div>
    </div>
</div>
</body>
</html>