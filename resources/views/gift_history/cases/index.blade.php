<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>贈与履歴管理</title>
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
            max-width: 1180px;
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
        }

        .toolbar {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) auto auto;
            gap: 10px;
            align-items: end;
            margin-bottom: 14px;
        }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 5px;
        }

        input[type="text"],
        select {
            height: 38px;
            border: 1px solid #b7c4d6;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 14px;
            background: #fff;
        }

        .buttons {
            display: flex;
            gap: 8px;
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
            background: #fff;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 9px;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            font-size: 13px;
            text-align: left;
            color: #334155;
            white-space: nowrap;
        }

        td.number,
        th.number {
            text-align: right;
        }

        .sort-link {
            color: inherit;
            text-decoration: none;
        }

        .sort-link:hover {
            text-decoration: underline;
        }

        .muted {
            color: #6b7280;
        }

        .operation {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .pager-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .pager {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pager a,
        .pager span {
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #1f2937;
            font-weight: 700;
        }

        .pager span.active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }

        .pager span.ellipsis {
            border-color: transparent;
            background: transparent;
        }

        .empty {
            text-align: center;
            padding: 30px 10px;
            color: #6b7280;
        }
    </style>
</head>


<body>
<div class="page">
    <div class="header">
        <div>
            <h1>贈与履歴管理</h1>
            <div class="sub">
                贈与名人のNo1に入力されている氏名一覧から、贈与履歴管理を開始または再開します。<br>
                既存の贈与名人DBは読み取り専用で参照します。
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert">{{ session('status') }}</div>
    @endif

    <div class="card">
        <form method="GET" action="{{ route('gift-history.index') }}" class="toolbar">
            <div>
                <label for="q">氏名検索</label>
                <input id="q" type="text" name="q" value="{{ $q }}" placeholder="例：山本、木村">
            </div>

            <div>
                <label for="per_page">表示件数</label>
                <select id="per_page" name="per_page">
                    @foreach ($perPageOptions as $option)
                        <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}件</option>
                    @endforeach
                </select>
            </div>

            <div class="buttons">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">
                <button type="submit" class="btn btn-primary">検索</button>
                <a href="{{ route('gift-history.index') }}" class="btn btn-muted">クリア</a>
            </div>
        </form>

        @php
            $sortUrl = function (string $field) use ($sort, $direction) {
                return request()->fullUrlWithQuery([
                    'sort' => $field,
                    'direction' => ($sort === $field && $direction === 'asc') ? 'desc' : 'asc',
                    'page' => 1,
                ]);
            };

            $sortMark = function (string $field) use ($sort, $direction) {
                if ($sort !== $field) {
                    return '';
                }

                return $direction === 'asc' ? ' ▲' : ' ▼';
            };
        @endphp

        <table>
            <thead>
            <tr>
                <th class="number">
                    <a class="sort-link" href="{{ $sortUrl('no') }}">No{{ $sortMark('no') }}</a>
                </th>
                <th>
                    <a class="sort-link" href="{{ $sortUrl('customer_name') }}">氏名{{ $sortMark('customer_name') }}</a>
                </th>
                <th>
                    <a class="sort-link" href="{{ $sortUrl('data_name') }}">データ名・案件名{{ $sortMark('data_name') }}</a>
                </th>
                <th class="number">
                    <a class="sort-link" href="{{ $sortUrl('entry_count') }}">贈与明細件数{{ $sortMark('entry_count') }}</a>
                </th>
                <th>
                    <a class="sort-link" href="{{ $sortUrl('updated_at') }}">最終更新日{{ $sortMark('updated_at') }}</a>
                </th>
                <th class="number">操作</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($cases as $row)
                <tr>
                    <td class="number">{{ ($cases->firstItem() ?? 1) + $loop->index }}</td>
                    <td>{{ $row->customer_name !== '' ? $row->customer_name : '（氏名未入力）' }}</td>
                    <td>
                        {{ $row->data_name !== '' ? $row->data_name : '（データ名なし）' }}
                    </td>
                    <td class="number">{{ number_format($row->entry_count) }}件</td>
                    <td>
                        @if ($row->history_updated_at)
                            {{ $row->history_updated_at->format('Y/m/d H:i') }}
                        @else
                            <span class="muted">未作成</span>
                        @endif
                    </td>
                    <td>
                        <div class="operation">
                            @if ($row->case_id)
                                <a class="btn btn-primary" href="{{ route('gift-history.show', $row->case_id) }}">開く</a>
                            @else
                                <form method="POST" action="{{ route('gift-history.start') }}">
                                    @csrf
                                    <input type="hidden" name="data_id" value="{{ $row->data_id }}">
                                    <input type="hidden" name="back_url" value="{{ request()->fullUrl() }}">
                                    <button type="submit" class="btn btn-secondary">贈与履歴を開始</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">該当するデータがありません。</td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div class="pager-wrap">
            <div class="muted">
                全 {{ number_format($cases->total()) }} 件
                @if ($cases->total() > 0)
                    ／ {{ number_format($cases->firstItem()) }}〜{{ number_format($cases->lastItem()) }} 件を表示
                @endif
            </div>

            @if ($cases->hasPages())
                @php
                    $current = $cases->currentPage();
                    $last = $cases->lastPage();
                    $start = max(1, $current - 2);
                    $end = min($last, $current + 2);
                @endphp

                <nav class="pager" aria-label="ページネーション">
                    @if (! $cases->onFirstPage())
                        <a href="{{ $cases->url($current - 1) }}">前へ</a>
                    @endif

                    @if ($start > 1)
                        <a href="{{ $cases->url(1) }}">1</a>
                        @if ($start > 2)
                            <span class="ellipsis">…</span>
                        @endif
                    @endif

                    @for ($page = $start; $page <= $end; $page++)
                        @if ($page === $current)
                            <span class="active">{{ $page }}</span>
                        @else
                            <a href="{{ $cases->url($page) }}">{{ $page }}</a>
                        @endif
                    @endfor

                    @if ($end < $last)
                        @if ($end < $last - 1)
                            <span class="ellipsis">…</span>
                        @endif
                        <a href="{{ $cases->url($last) }}">{{ $last }}</a>
                    @endif

                    @if ($cases->hasMorePages())
                        <a href="{{ $cases->url($current + 1) }}">次へ</a>
                    @endif
                </nav>
            @endif
        </div>
    </div>
</div>
</body>
</html>