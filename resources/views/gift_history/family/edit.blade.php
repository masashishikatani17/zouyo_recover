<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>贈与履歴管理 - 親族入力</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
    }

        h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: 0.04em;
        }

        .sub {
            margin-top: 6px;
            color: #6b7280;
            line-height: 1.6;
        }

        .card {
            background: #fff;
            border: 1px solid #d9e2ef;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 18px;
            margin-bottom: 16px;
        }

        .case-summary {
            display: grid;
            grid-template-columns: 180px 1fr 180px 1fr;
            gap: 8px 12px;
            line-height: 1.6;
        }

        .case-summary dt {
            font-weight: 700;
            color: #334155;
        }

        .case-summary dd {
            margin: 0;
        }

        .alert {
            margin-bottom: 14px;
            padding: 12px 14px;
            border-radius: 10px;
            font-weight: 700;
            line-height: 1.6;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn,
        button.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 7px 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            line-height: 1.2;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #fff;
        }

        .btn-secondary {
            background: #eef2ff;
            color: #1e3a8a;
            border-color: #c7d2fe;
        }

        .btn-muted {
            background: #f3f4f6;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-warning {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
    }

        table {
            width: 100%;
            min-width: 1320px;
            border-collapse: collapse;
            background: #fff;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 6px;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            font-size: 12px;
            text-align: center;
            color: #334155;
            white-space: nowrap;
        }

        td {
            font-size: 13px;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            min-height: 32px;
            border: 1px solid #b7c4d6;
            border-radius: 6px;
            padding: 4px 6px;
            font-size: 13px;
            box-sizing: border-box;
            background: #fff;
        }

        .w-no { width: 44px; text-align: center; }
        .w-name { width: 160px; }
        .w-gender { width: 72px; }
        .w-rel { width: 130px; }
        .w-note { width: 120px; }
        .w-heir { width: 120px; }
        .w-small { width: 72px; }
        .w-check { width: 72px; text-align: center; }
        .w-date { width: 70px; }
        .w-money { width: 105px; }

        .text-end {
            text-align: right;
        }

        .help {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #78350f;
            border-radius: 10px;
            padding: 12px 14px;
            line-height: 1.7;
            margin-bottom: 14px;
        }

    .muted {
            color: #6b7280;
        }

        .footer-actions {
            margin-top: 16px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
    </style>
</head>

<body>
<div class="page">
    <div class="header">
        <div>
            <h1>親族入力</h1>
            <div class="sub">
                贈与履歴管理で使用する親族15人を登録します。<br>
                取り込み後は、贈与名人側とは独立してこのシステム側で保持します。
            </div>
        </div>
        <div class="buttons">
            <a href="{{ route('gift-history.show', $case) }}" class="btn btn-muted">対象データへ戻る</a>
            <a href="{{ $backUrl }}" class="btn btn-muted">一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <dl class="case-summary">
            <dt>氏名</dt>
            <dd>{{ $case->customer_name_snapshot ?? '（氏名未入力）' }}</dd>
            <dt>データ名・案件名</dt>
            <dd>{{ $case->data_name_snapshot ?? '（データ名なし）' }}</dd>
            <dt>既存データID</dt>
            <dd>{{ $case->data_id }}</dd>
            <dt>贈与明細件数</dt>
            <dd>{{ number_format($case->entries_count) }}件</dd>
        </dl>
    </div>

    <div class="card">
        <div class="toolbar">
            <div>
                <strong>画面1：親族15人入力</strong>
                <div class="muted">既存側に存在しない行は空欄行として表示します。</div>
            </div>

            <div class="buttons">
                @if (! $hasNonEmptyFamilyMember)
                    <form method="POST" action="{{ route('gift-history.family.import', $case) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary">
                            贈与名人から親族情報を取り込む
                        </button>
                    </form>
                @else
                    <span class="btn btn-warning">親族情報は入力済みです</span>
                @endif
            </div>
        </div>

        <div class="help">
            <strong>HELP</strong><br>
            この画面では、贈与履歴管理で使用する親族情報を15行固定で管理します。
            続柄候補は贈与名人側の続柄マスターを初期候補として取り込みます。
            保存後は贈与履歴管理側のデータとして独立管理されます。
        </div>

        <form method="POST" action="{{ route('gift-history.family.update', $case) }}">
            @csrf

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th class="w-no">No</th>
                        <th class="w-name">氏名</th>
                        <th class="w-gender">性別</th>
                        <th class="w-rel">続柄</th>
                        <th class="w-note">養子縁組</th>
                        <th class="w-heir">相続人区分</th>
                        <th colspan="2">法定相続割合<br>民法上</th>
                        <th colspan="2">法定相続割合<br>税法上</th>
                        <th class="w-check">2割<br>加算</th>
                        <th class="w-check">特例<br>贈与</th>
                        <th colspan="3">生年月日</th>
                        <th class="w-small">年齢</th>
                        <th class="w-money">財産額<br>千円</th>
                        <th class="w-money">現金額<br>千円</th>
                    </tr>
                    <tr>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th class="w-small">分母</th>
                        <th class="w-small">分子</th>
                        <th class="w-small">分子</th>
                        <th class="w-small">分母</th>
                        <th></th>
                        <th></th>
                        <th class="w-date">年</th>
                        <th class="w-date">月</th>
                        <th class="w-date">日</th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @for ($rowNo = 1; $rowNo <= 15; $rowNo++)
                        @php
                            $member = $members->get($rowNo);
                            $base = "members.{$rowNo}";
                            $relationshipValue = old($base.'.relationship_code', $member?->relationship_code);
                            $heirValue = old($base.'.heir_category', $member?->heir_category);
                        @endphp
                        <tr>
                            <td class="w-no">
                                {{ $rowNo }}
                            </td>
                            <td class="w-name">
                                <input type="text" name="members[{{ $rowNo }}][name]"
                                       value="{{ old($base.'.name', $member?->name) }}">
                            </td>
                            <td class="w-gender">
                                <select name="members[{{ $rowNo }}][gender]">
                                    @php $genderValue = old($base.'.gender', $member?->gender); @endphp
                                    <option value=""></option>
                                    <option value="男" @selected($genderValue === '男')>男</option>
                                    <option value="女" @selected($genderValue === '女')>女</option>
                                </select>
                            </td>
                            <td class="w-rel">
                                <select name="members[{{ $rowNo }}][relationship_code]">
                                    <option value=""></option>
                                    @foreach ($relationshipOptions as $option)
                                        <option value="{{ $option->relation_no }}"
                                            @selected((string) $relationshipValue === (string) $option->relation_no)>
                                            {{ $option->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="w-note">
                                <input type="text" name="members[{{ $rowNo }}][adoption_note]"
                                       value="{{ old($base.'.adoption_note', $member?->adoption_note) }}">
                            </td>
                            <td class="w-heir">
                                <select name="members[{{ $rowNo }}][heir_category]">
                                    <option value=""></option>
                                    <option value="0" @selected((string) $heirValue === '0')>被相続人</option>
                                    <option value="1" @selected((string) $heirValue === '1')>法定相続人</option>
                                    <option value="2" @selected((string) $heirValue === '2')>法定相続人以外</option>
                                </select>
                            </td>
                            <td class="w-small">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][civil_share_bunbo]"
                                       value="{{ old($base.'.civil_share_bunbo', $member?->civil_share_bunbo) }}">
                            </td>
                            <td class="w-small">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][civil_share_bunsi]"
                                       value="{{ old($base.'.civil_share_bunsi', $member?->civil_share_bunsi) }}">
                            </td>
                            <td class="w-small">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][share_numerator]"
                                       value="{{ old($base.'.share_numerator', $member?->share_numerator) }}">
                            </td>
                            <td class="w-small">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][share_denominator]"
                                       value="{{ old($base.'.share_denominator', $member?->share_denominator) }}">
                            </td>
                            <td class="w-check">
                                <input type="hidden" name="members[{{ $rowNo }}][surcharge_twenty_percent]" value="0">
                                <input type="checkbox" name="members[{{ $rowNo }}][surcharge_twenty_percent]" value="1"
                                    @checked((string) old($base.'.surcharge_twenty_percent', (int) ($member?->surcharge_twenty_percent ?? 0)) === '1')>
                            </td>
                            <td class="w-check">
                                <input type="hidden" name="members[{{ $rowNo }}][tokurei_zouyo]" value="0">
                                <input type="checkbox" name="members[{{ $rowNo }}][tokurei_zouyo]" value="1"
                                    @checked((string) old($base.'.tokurei_zouyo', (int) ($member?->tokurei_zouyo ?? 0)) === '1')>
                            </td>
                            <td class="w-date">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][birth_year]"
                                       value="{{ old($base.'.birth_year', $member?->birth_year) }}">
                            </td>
                            <td class="w-date">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][birth_month]"
                                       value="{{ old($base.'.birth_month', $member?->birth_month) }}">
                            </td>
                            <td class="w-date">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][birth_day]"
                                       value="{{ old($base.'.birth_day', $member?->birth_day) }}">
                            </td>
                            <td class="w-small">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][age]"
                                       value="{{ old($base.'.age', $member?->age) }}">
                            </td>
                            <td class="w-money">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][property_thousand]"
                                       value="{{ old($base.'.property_thousand', $member?->property_thousand) }}">
                            </td>
                            <td class="w-money">
                                <input class="text-end" type="text" name="members[{{ $rowNo }}][cash_thousand]"
                                       value="{{ old($base.'.cash_thousand', $member?->cash_thousand) }}">
                            </td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>

            <div class="footer-actions">
                <div class="buttons">
                    <a href="{{ route('gift-history.show', $case) }}" class="btn btn-muted">対象データへ戻る</a>
                    <a href="{{ $backUrl }}" class="btn btn-muted">一覧へ戻る</a>
                </div>
                <button type="submit" class="btn btn-primary">親族情報を保存</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>