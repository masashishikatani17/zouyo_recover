<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>贈与履歴管理 - 生前贈与登録</title>
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

        h2 {
            margin: 0 0 12px;
            font-size: 18px;
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
            grid-template-columns: 160px 1fr 160px 1fr;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
        }

        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            min-height: 36px;
            border: 1px solid #b7c4d6;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 14px;
            box-sizing: border-box;
            background: #fff;
        }

        textarea {
            min-height: 76px;
            resize: vertical;
        }

        .radio-row,
        .check-row {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            min-height: 36px;
        }

        .radio-row label,
        .check-row label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            font-weight: 700;
        }

        .party-wrap {
            display: grid;
            grid-template-columns: 1fr 72px 1fr;
            gap: 14px;
            align-items: stretch;
        }

        .party {
            border-radius: 12px;
            padding: 14px;
            border: 1px solid;
        }

        .party-donor {
            background: #fff7ed;
            border-color: #fed7aa;
        }

        .party-recipient {
            background: #eff6ff;
            border-color: #bfdbfe;
        }

        .party-title {
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 900;
            color: #475569;
        }

        .help {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #78350f;
            border-radius: 10px;
            padding: 12px 14px;
            line-height: 1.7;
            margin-top: 12px;
        }

        .addback-box {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 12px 14px;
            line-height: 1.8;
        }

        .manual-box {
            background: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 10px;
            padding: 12px 14px;
        }

        .confirm-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 14px;
            line-height: 1.7;
            font-weight: 700;
        }

        .muted {
            color: #6b7280;
        }

        .error-list {
            margin: 0 0 14px;
            padding-left: 20px;
        }

        .buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
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

        .btn-muted {
            background: #f3f4f6;
            color: #374151;
            border-color: #d1d5db;
        }

        .is-hidden {
            display: none !important;
        }
    </style>
</head>

<body>
<div class="page">
    <div class="header">
        <div>
            <h1>生前贈与登録</h1>
            <div class="sub">
                実際に行った贈与を1件ずつ登録します。金額は円単位で入力し、DBにも円単位で保存します。
            </div>
        </div>
        <div class="buttons">
            <a href="{{ route('gift-history.show', $case) }}" class="btn btn-muted">対象データへ戻る</a>
            <a href="{{ route('gift-history.family.edit', $case) }}" class="btn btn-muted">親族入力へ</a>
            <a href="{{ $backUrl }}" class="btn btn-muted">一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <div>入力内容を確認してください。</div>
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <dl class="case-summary">
            <dt>氏名</dt>
            <dd>{{ $case->customer_name_snapshot ?? '（氏名未入力）' }}</dd>
            <dt>データ名・案件名</dt>
            <dd>{{ $case->data_name_snapshot ?? '（データ名なし）' }}</dd>
            <dt>既存データID</dt>
            <dd>{{ $case->data_id }}</dd>
            <dt>登録済み贈与明細</dt>
            <dd>{{ number_format($case->entries_count) }}件</dd>
        </dl>
    </div>

    <form method="POST" action="{{ route('gift-history.entries.store', $case) }}" id="gift-entry-form">
        @csrf

        <div class="card">
            <h2>A. 贈与の基本情報</h2>
            <div class="form-grid">
                <div>
                    <label>贈与形態</label>
                    @php $taxation = old('gift_taxation_type', 'calendar'); @endphp
                    <div class="radio-row">
                        <label>
                            <input type="radio" name="gift_taxation_type" value="calendar" @checked($taxation === 'calendar')>
                            暦年贈与
                        </label>
                        <label>
                            <input type="radio" name="gift_taxation_type" value="settlement" @checked($taxation === 'settlement')>
                            相続時精算課税
                        </label>
                    </div>
                </div>
                <div>
                    <label for="gift_date">贈与日</label>
                    <input type="date" id="gift_date" name="gift_date" value="{{ old('gift_date') }}">
                </div>
                <div>
                    <label>生前贈与加算期限</label>
                    <div class="addback-box" id="addback-box">
                        <div>3年以内加算期限：<span id="addback-three">贈与日を入力すると自動表示されます</span></div>
                        <div>最終加算期限：<span id="addback-final">贈与日を入力すると自動表示されます</span></div>
                        <div class="muted" style="margin-top:6px;">
                            ※生前贈与加算期限は、暦年課税贈与の加算判定に使用する参考日です。<br>
                            実際に加算対象となるかどうかは、相続などにより財産を取得したかどうか等により判定します。
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>B. 贈与者・受贈者・続柄情報</h2>
            <div class="party-wrap">
                <div class="party party-donor">
                    <div class="party-title">贈与者：財産を渡す人</div>
                    <label for="donor_family_member_id">贈与者</label>
                    <select id="donor_family_member_id" name="donor_family_member_id">
                        <option value="">選択してください</option>
                        @foreach ($familyMembers as $member)
                            <option value="{{ $member->id }}"
                                    data-name="{{ $member->name }}"
                                @selected((string) old('donor_family_member_id') === (string) $member->id)>
                                {{ $member->row_no }}：{{ $member->name }}
                            </option>
                        @endforeach
                    </select>

                    <div style="margin-top:12px;">
                        <label id="donor-relationship-label">贈与者は、受贈者から見て</label>
                        <select id="donor_relationship_code_from_recipient" name="donor_relationship_code_from_recipient">
                            <option value="">選択してください</option>
                            @foreach ($relationshipOptions as $option)
                                <option value="{{ $option->relation_no }}"
                                    @selected((string) old('donor_relationship_code_from_recipient') === (string) $option->relation_no)>
                                    {{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="arrow">→</div>

                <div class="party party-recipient">
                    <div class="party-title">受贈者：財産を受け取る人</div>
                    <label for="recipient_family_member_id">受贈者</label>
                    <select id="recipient_family_member_id" name="recipient_family_member_id">
                        <option value="">選択してください</option>
                        @foreach ($familyMembers as $member)
                            <option value="{{ $member->id }}"
                                    data-name="{{ $member->name }}"
                                @selected((string) old('recipient_family_member_id') === (string) $member->id)>
                                {{ $member->row_no }}：{{ $member->name }}
                            </option>
                        @endforeach
                    </select>

                    <div style="margin-top:12px;">
                        <label id="recipient-relationship-label">受贈者は、贈与者から見て</label>
                        <select id="recipient_relationship_code_from_donor" name="recipient_relationship_code_from_donor">
                            <option value="">選択してください</option>
                            @foreach ($relationshipOptions as $option)
                                <option value="{{ $option->relation_no }}"
                                    @selected((string) old('recipient_relationship_code_from_donor') === (string) $option->relation_no)>
                                    {{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="help">
                続柄は、誰から見た続柄かを間違えないように、人名入りで確認します。<br>
                例：山本太郎さんは、山本一彦さんから見て「父」／山本一彦さんは、山本太郎さんから見て「長男」。
            </div>
        </div>

        <div class="card">
            <h2>C. 財産情報</h2>
            <div class="form-grid">
                <div>
                    <label for="asset_category">贈与財産の種類</label>
                    <select id="asset_category" name="asset_category">
                        <option value="">選択してください</option>
                        @foreach ($assetCategories as $value => $label)
                            <option value="{{ $value }}" @selected(old('asset_category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asset_name">財産名</label>
                    <input type="text" id="asset_name" name="asset_name" value="{{ old('asset_name') }}"
                           placeholder="例：現金、○○銀行普通預金、○○株式">
                </div>
                <div>
                    <label for="gift_amount_yen">贈与額（円）</label>
                    <input type="text" id="gift_amount_yen" name="gift_amount_yen" value="{{ old('gift_amount_yen') }}"
                           inputmode="numeric" placeholder="例：5,000,000">
                </div>
            </div>
            <div style="margin-top:14px;">
                <label for="asset_description">財産内容メモ</label>
                <textarea id="asset_description" name="asset_description" placeholder="株数、所在地、評価明細など">{{ old('asset_description') }}</textarea>
            </div>
        </div>

        <div class="card" id="calendar-tax-section">
            <h2>D. 暦年贈与の税率区分</h2>
            @php $calendarTaxType = old('calendar_tax_type'); @endphp
            <div class="radio-row">
                <label>
                    <input type="radio" name="calendar_tax_type" value="general" @checked($calendarTaxType === 'general')>
                    一般贈与
                </label>
                <label>
                    <input type="radio" name="calendar_tax_type" value="tokurei" @checked($calendarTaxType === 'tokurei')>
                    特例贈与
                </label>
            </div>
            <div class="help">
                暦年贈与を選択した場合は、入力者が必ず「一般贈与」または「特例贈与」を選択します。<br>
                システムによる判定補助は後続フェーズで追加します。第1実装では、入力者の選択結果を保存します。
            </div>
        </div>

        <div class="card is-hidden" id="settlement-tax-section">
            <h2>D. 相続時精算課税の確認</h2>
            <div class="form-grid">
                <div>
                    <label for="settlement_notification_date">精算課税選択届出書の提出日</label>
                    <input type="date" id="settlement_notification_date" name="settlement_notification_date"
                           value="{{ old('settlement_notification_date') }}">
                </div>
                <div>
                    <label>届出確認</label>
                    <div class="check-row">
                        <input type="hidden" name="settlement_election_confirmed" value="0">
                        <label>
                            <input type="checkbox" name="settlement_election_confirmed" value="1"
                                @checked((string) old('settlement_election_confirmed', '0') === '1')>
                            提出済み、または提出予定
                        </label>
                    </div>
                </div>
                <div>
                    <label>暦年課税に戻れない確認</label>
                    <div class="check-row">
                        <input type="hidden" name="settlement_no_return_confirmed" value="0">
                        <label>
                            <input type="checkbox" name="settlement_no_return_confirmed" value="1"
                                @checked((string) old('settlement_no_return_confirmed', '0') === '1')>
                            確認しました
                        </label>
                    </div>
                </div>
            </div>
            <div class="help">
                相続時精算課税を選択すると、この特定贈与者からの贈与について、以後は暦年課税に戻れないことを確認します。
            </div>
        </div>

        <div class="card">
            <h2>E. 税額・申告・備考</h2>
            <div class="manual-box">
                <div class="check-row">
                    <input type="hidden" name="tax_override_enabled" value="0">
                    <label>
                        <input type="checkbox" id="tax_override_enabled" name="tax_override_enabled" value="1"
                            @checked((string) old('tax_override_enabled', '0') === '1')>
                        贈与税額を手入力する
                    </label>
                </div>
                <div class="form-grid-2" style="margin-top:12px;">
                    <div>
                        <label for="tax_override_amount_yen">手入力贈与税額（円）</label>
                        <input type="text" id="tax_override_amount_yen" name="tax_override_amount_yen"
                               value="{{ old('tax_override_amount_yen') }}"
                               inputmode="numeric" placeholder="例：123,400">
                    </div>
                    <div>
                        <label for="tax_override_reason">手入力理由</label>
                        <input type="text" id="tax_override_reason" name="tax_override_reason"
                               value="{{ old('tax_override_reason') }}"
                               placeholder="例：申告書作成済みの金額に合わせる">
                    </div>
                </div>
                <div class="muted" style="margin-top:8px;">
                    チェックOFFの場合は自動計算値を使用します。チェックONの場合は手入力値を優先します。
                    チェックONの場合に入力した金額は、その後チェックをOFFにしても画面上では削除しません。
                </div>
            </div>

            <div class="form-grid" style="margin-top:14px;">
                <div>
                    <label for="tax_return_status">確定申告の有無</label>
                    <select id="tax_return_status" name="tax_return_status">
                        @php $taxReturn = old('tax_return_status'); @endphp
                        <option value=""></option>
                        <option value="not_required" @selected($taxReturn === 'not_required')>申告不要</option>
                        <option value="planned" @selected($taxReturn === 'planned')>申告予定</option>
                        <option value="filed" @selected($taxReturn === 'filed')>申告済み</option>
                        <option value="unknown" @selected($taxReturn === 'unknown')>未確認</option>
                    </select>
                </div>
                <div>
                    <label for="gift_contract_status">贈与契約書</label>
                    <select id="gift_contract_status" name="gift_contract_status">
                        @php $contract = old('gift_contract_status'); @endphp
                        <option value=""></option>
                        <option value="yes" @selected($contract === 'yes')>有</option>
                        <option value="no" @selected($contract === 'no')>無</option>
                        <option value="unknown" @selected($contract === 'unknown')>未確認</option>
                    </select>
                </div>
                <div>
                    <label>第1実装の税額自動計算</label>
                    <div class="muted">
                        自動計算は後続フェーズで実装します。<br>
                        今回は入力内容と手入力税額を保存します。
                    </div>
                </div>
            </div>

            <div style="margin-top:14px;">
                <label for="memo">備考</label>
                <textarea id="memo" name="memo" placeholder="申告書控え、契約書、通帳、財産評価明細、確認事項など">{{ old('memo') }}</textarea>
            </div>
        </div>

        <div class="card">
            <h2>登録前確認</h2>
            <div class="confirm-box" id="confirm-box">
                贈与者・受贈者・贈与額を入力すると確認内容を表示します。
            </div>

            <div class="footer-actions">
                <div class="buttons">
                    <a href="{{ route('gift-history.show', $case) }}" class="btn btn-muted">対象データへ戻る</a>
                    <a href="{{ route('gift-history.family.edit', $case) }}" class="btn btn-muted">親族入力へ</a>
                    <a href="{{ $backUrl }}" class="btn btn-muted">一覧へ戻る</a>
                </div>
                <button type="submit" class="btn btn-primary">生前贈与を登録</button>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const donorSelect = document.getElementById('donor_family_member_id');
    const recipientSelect = document.getElementById('recipient_family_member_id');
    const donorRelSelect = document.getElementById('donor_relationship_code_from_recipient');
    const recipientRelSelect = document.getElementById('recipient_relationship_code_from_donor');
    const giftDateInput = document.getElementById('gift_date');
    const giftAmountInput = document.getElementById('gift_amount_yen');
    const addbackBox = document.getElementById('addback-box');
    const addbackThree = document.getElementById('addback-three');
    const addbackFinal = document.getElementById('addback-final');
    const calendarSection = document.getElementById('calendar-tax-section');
    const settlementSection = document.getElementById('settlement-tax-section');
    const confirmBox = document.getElementById('confirm-box');
    const taxOverrideEnabled = document.getElementById('tax_override_enabled');
    const taxOverrideAmount = document.getElementById('tax_override_amount_yen');

    const selectedName = (select, fallback) => {
        const option = select?.selectedOptions?.[0];
        return option?.dataset?.name || fallback;
    };

    const selectedText = (select) => {
        const option = select?.selectedOptions?.[0];
        return option?.textContent?.trim() || '';
    };

    const taxationType = () => {
        return document.querySelector('input[name="gift_taxation_type"]:checked')?.value || 'calendar';
    };

    const parseDate = (value) => {
        if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }
        const [year, month, day] = value.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
            return null;
        }
        return date;
    };

    const addYears = (date, years) => {
        const result = new Date(date.getTime());
        const month = result.getMonth();
        result.setFullYear(result.getFullYear() + years);
        if (result.getMonth() !== month) {
            result.setDate(0);
        }
        return result;
    };

    const formatDate = (date) => {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}/${m}/${d}`;
    };

    const updateLabels = () => {
        const donorName = selectedName(donorSelect, '贈与者');
        const recipientName = selectedName(recipientSelect, '受贈者');

        document.getElementById('donor-relationship-label').textContent =
            `${donorName}さんは、${recipientName}さんから見て`;

        document.getElementById('recipient-relationship-label').textContent =
            `${recipientName}さんは、${donorName}さんから見て`;
    };

    const updateTaxationSections = () => {
        const isCalendar = taxationType() === 'calendar';
        calendarSection.classList.toggle('is-hidden', !isCalendar);
        settlementSection.classList.toggle('is-hidden', isCalendar);
        addbackBox.classList.toggle('is-hidden', !isCalendar);
        updateAddback();
    };

    const updateAddback = () => {
        if (taxationType() !== 'calendar') {
            return;
        }

        const date = parseDate(giftDateInput.value);
        if (!date) {
            addbackThree.textContent = '贈与日を入力すると自動表示されます';
            addbackFinal.textContent = '贈与日を入力すると自動表示されます';
            return;
        }

        const three = addYears(date, 3);
        const lawChange = new Date(2024, 0, 1);
        const finalDate = date < lawChange ? three : addYears(date, 7);

        addbackThree.textContent = formatDate(three);
        addbackFinal.textContent = formatDate(finalDate);
    };

    const updateManualTax = () => {
        // OFFにしても入力済み金額は消さない。
        taxOverrideAmount.disabled = false;
    };

    const updateConfirm = () => {
        const donorName = selectedName(donorSelect, '未選択');
        const recipientName = selectedName(recipientSelect, '未選択');
        const donorRel = selectedText(donorRelSelect) || '続柄未選択';
        const recipientRel = selectedText(recipientRelSelect) || '続柄未選択';
        const giftType = taxationType() === 'calendar' ? '暦年贈与' : '相続時精算課税';
        const amount = giftAmountInput.value || '金額未入力';

        let taxType = '';
        if (taxationType() === 'calendar') {
            const checked = document.querySelector('input[name="calendar_tax_type"]:checked')?.value;
            taxType = checked === 'tokurei' ? '特例贈与' : (checked === 'general' ? '一般贈与' : '税率区分未選択');
        } else {
            taxType = '相続時精算課税';
        }

        confirmBox.textContent =
            `${donorName}（${recipientName}さんから見て：${donorRel}） → ` +
            `${recipientName}（${donorName}さんから見て：${recipientRel}） ／ ` +
            `${giftType} ／ ${taxType} ／ 贈与額：${amount}円`;
    };

    const updateAll = () => {
        updateLabels();
        updateTaxationSections();
        updateManualTax();
        updateConfirm();
    };

    document.querySelectorAll('input[name="gift_taxation_type"]').forEach((el) => {
        el.addEventListener('change', updateAll);
    });

    document.querySelectorAll('input[name="calendar_tax_type"]').forEach((el) => {
        el.addEventListener('change', updateAll);
    });

    [
        donorSelect,
        recipientSelect,
        donorRelSelect,
        recipientRelSelect,
        giftDateInput,
        giftAmountInput,
        taxOverrideEnabled,
        taxOverrideAmount,
    ].forEach((el) => {
        el?.addEventListener('input', updateAll);
        el?.addEventListener('change', updateAll);
    });

    updateAll();
})();
</script>
</body>
</html>