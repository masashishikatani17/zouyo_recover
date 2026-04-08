<!--   kekka_kazeikakaku.blade  -->
{{-- kekka_kazeikakaku.blade　結果詳細（仮表示：贈与後の相続税の課税価格） --}}
 @php


  // 返却結果（input.blade 側で Cache/Session から解決済みの $resultsData を最優先）
  $r = $resultsData ?? [];

  // after は summary 本体、meta、projections.after はそのまま持つ
  $after         = $r['after']['summary'] ?? [];
  $afterMeta     = $r['after']['meta']    ?? [];
  $projAfter     = $r['projections']['after'] ?? [];

  // ★ t=0（現時点）のフォールバックに before.summary も見ておく
  $beforeSummary = $r['before']['summary'] ?? [];
  $projBefore    = $r['projections']['before'] ?? [];
  
  

  // 被相続人の氏名は name[1] を最優先で採用する。
  // 優先順：1) old('name.1') 2) request('name.1') 3) DB取得の $family[1]['name'] 4) $prefillFamily[1]['name']
  $candidates = [
      old('name.1', null),
      request()->input('name.1'),
      \Illuminate\Support\Arr::get($family ?? [], '1.name'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [], '1.name'),
  ];
  $heirName = '(未設定)';
  foreach ($candidates as $cand) {
      $cand = is_string($cand) ? trim($cand) : '';
      if ($cand !== '') { $heirName = $cand; break; }
  }



  // 現時点の年齢（被相続人＝row_no=1）を決定
  // 優先順：old('age.1') → request('age.1') → DBの $family[1]['age'] → $prefillFamily[1]['age']
  $ageCandidates = [
      old('age.1', null),
      request()->input('age.1'),
      \Illuminate\Support\Arr::get($family ?? [], '1.age'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [], '1.age'),
  ];
  $baseAge = null;
  foreach ($ageCandidates as $cand) {
      if ($cand === null) continue;
      // 文字が来る可能性もあるため安全に数値化
      $n = (int)preg_replace('/[^\d\-]/', '', (string)$cand);
      if ($n >= 0 && $n <= 130) { $baseAge = $n; break; }
  }

  // ▼ 所有財産の額（prop_amount）の算出準備
  //   重要：property は「総資産（現時点）」、cash はその内訳の一部（運用対象）という前提。
  //   よって t年後の総資産＝ (property - cash) + cash * (1+per)^t   （単位：千円）
  //   ※ t=0 のときは property と一致し、二重計上を防げます。
  //   取得優先度： old() → request() → $prefillFamily → $family
  $numK = function($v) {
      if ($v === null || $v === '') return 0;
      // 「1,234」や全角混じりにも対応
      $s = preg_replace('/[^\d\-]/u', '', (string)$v);
      return (int)$s;
  };
  $propCandidates = [
      old('property.1', null),
      request()->input('property.1'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [], '1.property'),
      \Illuminate\Support\Arr::get($family ?? [], '1.property'),
  ];
  $cashCandidates = [
      old('cash.1', null),
      request()->input('cash.1'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [], '1.cash'),
      \Illuminate\Support\Arr::get($family ?? [], '1.cash'),
  ];
  $propK = 0;
  foreach ($propCandidates as $c) { $propK = $numK($c); if ($propK !== 0) break; }
  $cashK = 0;
  foreach ($cashCandidates as $c) { $cashK = $numK($c); if ($cashK !== 0) break; }



  $perCandidates = [
      old('per', null),
      request()->input('per'),
      \Illuminate\Support\Arr::get($prefillHeader ?? [], 'per'),
      \Illuminate\Support\Arr::get($header ?? [], 'per'),
  ];
  $rate = 0.0;
  foreach ($perCandidates as $pc) {
      if ($pc === null || $pc === '') continue;
      $rate = (float)preg_replace('/[^\d\.\-]/', '', (string)$pc);
      $rate = $rate / 100.0;
      break;
  }



  // 千円 → 円への変換と非現金部分の算出（prop_amount 再計算用）
  $propYenNow = max(0, $propK) * 1000;
  $cashYenNow = max(0, $cashK) * 1000;
  $nonCashYen = max(0, $propYenNow - $cashYenNow);

  // 行ビルダー（年次0=現時点、1..20は Service の t と 1:1 で対応）
  // ・prop_amount も含め、年次 t と「t年後」を揃える

  $buildRow = function (int $t) use (
      $after,
      $afterMeta,
      $projAfter,
      $beforeSummary,
      $baseAge,
      $propK,
      $cashK,
      $rate
  ) {
      // サマリ部分は Service 側の t と 1:1 で対応
      $summary = ($t === 0)
          ? $after                                // t=0 は after.summary そのもの
          : (($projAfter[$t]['summary'] ?? []) ?: []);

      // t=0 で estate_base_yen が無ければ before.summary から補完
      if ($t === 0 && empty($summary['estate_base_yen']) && !empty($beforeSummary['estate_base_yen'])) {
          $summary['estate_base_yen'] = $beforeSummary['estate_base_yen'];
      }

      // t=0 のときだけ cum_row0 から incl_* を補完
      if ($t === 0) {
          $hasCal = array_key_exists('incl_calendar_yen', $summary) && $summary['incl_calendar_yen'] !== null;
          $hasSet = array_key_exists('incl_settlement_yen', $summary) && $summary['incl_settlement_yen'] !== null;
          $cumRow0 = $afterMeta['cum_row0'] ?? null;
          if ($cumRow0 && (!$hasCal || !$hasSet)) {
              $calK = (int)($cumRow0['cal_k'] ?? 0);
              $setK = (int)($cumRow0['set_k'] ?? 0);
              if (!$hasCal) {
                  $summary['incl_calendar_yen'] = $calK * 1000;
              }
              if (!$hasSet) {
                  $summary['incl_settlement_yen'] = $setK * 1000;
              }
              if (!array_key_exists('past_gift_included_total_yen', $summary)) {
                  $summary['past_gift_included_total_yen']
                      = (int)($summary['incl_calendar_yen'] ?? 0)
                      + (int)($summary['incl_settlement_yen'] ?? 0);
              }
          }
      }

      $base    = (int)($summary['estate_base_yen']        ?? 0);
      $decrCal = (int)($summary['gift_decr_calendar_yen'] ?? 0);
      $decrSet = (int)($summary['gift_decr_payment_yen']  ?? 0);
      $estateAfter = (int)($summary['estate_after_yen'] ?? ($base + $decrCal + $decrSet));

      // ▼ 所有財産の額（prop_amount）
      //   → Service 側が「これからの贈与」を差し引いた後のベース資産を
      //      summary['estate_base_yen'] に入れているので、それをそのまま表示する。
      //   ・t=0（対策後）: 非現金＋現金（将来贈与を無視）
      //   ・t>=1（対策後プロジェクション）: 非現金＋現金−将来贈与の流出
      $propAmountYen = (int)($summary['estate_base_yen'] ?? 0);

      return [
          'nenji'        => ($t === 0) ? '現時点' : ($t . '年後'),
          'age'          => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
          'prop_amount'  => $propAmountYen,
          // 贈与による財産の減少（暦年／精算）…Service の値をそのまま表示（いじらない）
          'gift_decr_cal'=> $decrCal,
          'gift_decr_set'=> $decrSet,
          // （贈与後）相続財産の額：Service の t 年次の estate_after_yen
          'estate_after' => $estateAfter,
          // 贈与加算累計額（暦年／精算）：Service の t 年次の incl_* をそのまま表示
          'incl_cal'     => (int)($summary['incl_calendar_yen']   ?? 0),
          'incl_set'     => (int)($summary['incl_settlement_yen'] ?? 0),
          'incl_total'   => (int)($summary['past_gift_included_total_yen'] ?? 0),
          // 課税価格：Service 側で定義した kazei_price_yen（なければ taxable_estate）
          'taxable'      => (int)($summary['kazei_price_yen'] ?? $summary['taxable_estate'] ?? 0),
      ];
  };


  $rows = [];
  $rows[] = $buildRow(0);
  for ($t = 1; $t <= 20; $t++) {
      $rows[] = $buildRow($t);
  }



  // ▼ 対策前の「贈与税累計額（これからの贈与を無視した生前贈与加算対象の贈与税額）」を一度だけ確定
  //    → before.summary の値を t=0..20 で共通利用
  $baseGiftBefore = (int)(
      $beforeSummary['gift_tax_cum_yen']
      ?? $beforeSummary['gift_tax_total_yen']
      ?? (
          (int)($beforeSummary['total_gift_tax_credits']      ?? 0)
        + (int)($beforeSummary['total_settlement_gift_taxes'] ?? 0)
      )
  );


  // ▼ 対策前/対策後の税額推移（贈与による節税効果）の行ビルダー
  //   ・t は Service 側の projections の添字と 1:1（t=0..20）
  //   ・相続税 = final_after_settlement_yen（なければ total_final_after_settlement / sozoku_tax_total）
  //   ・対策前の贈与税累計額 = gift_tax_cum_yen（なければ total_gift_tax_credits + total_settlement_gift_taxes）
  //   ・対策後の贈与税累計額 = future_calendar_gift_tax_cum_yen
  //        （無ければ従来どおり gift_tax_cum_yen / total_gift_tax_credits + total_settlement_gift_taxes をフォールバック）

  $buildEffectRow = function (int $t) use ($beforeSummary, $after, $projBefore, $projAfter, $baseAge, $baseGiftBefore) {
      // --- 対策前サマリ ---
      $before = ($t === 0)
          ? $beforeSummary
          : (($projBefore[$t]['summary'] ?? []) ?: []);

      // --- 対策後サマリ ---
      $afterSummary = ($t === 0)
          ? $after
          : (($projAfter[$t]['summary'] ?? []) ?: []);

      // --- 相続税（精算課税贈与税控除後） ---
      $sozokuBefore = (int)(
          $before['final_after_settlement_yen']
          ?? $before['total_final_after_settlement']
          ?? $before['sozoku_tax_total']
          ?? 0
      );

      $sozokuAfter = (int)(
          $afterSummary['final_after_settlement_yen']
          ?? $afterSummary['total_final_after_settlement']
          ?? $afterSummary['sozoku_tax_total']
          ?? 0
      );

      // --- 贈与税累計額 ---
      // 対策前は t=0 の値を t=0..20 で固定（過去分のみ）
      $giftBefore = $baseGiftBefore;

      // 対策後は Service 側で計算した「暦年＋精算の贈与税額累計」をそのまま使用する
      //   ・calendar_gift_tax_cum_yen … 今回新設した「贈与税額累計（過去＋将来）」フィールド
      //   ・gift_tax_cum_yen など従来の“控除額”ベースはフォールバックに回す
      $giftAfter = (int)(
          // ★第一優先：Service 側で付与した「贈与税額（暦年＋精算）の累計」
          $afterSummary['calendar_gift_tax_cum_yen']
          // フォールバック（旧データ／開発途中用）
          ?? $afterSummary['gift_tax_cum_yen']
          ?? $afterSummary['gift_tax_total_yen']
          ?? (
              (int)($afterSummary['total_gift_tax_credits']      ?? 0)
            + (int)($afterSummary['total_settlement_gift_taxes'] ?? 0)
          )
      );

      $totalBefore = $sozokuBefore + $giftBefore;
      $totalAfter  = $sozokuAfter  + $giftAfter;

      // 差額（対策後 − 対策前）…単位：円
      $diff = $totalAfter - $totalBefore;

      return [
          'nenji'         => ($t === 0) ? '現時点' : ($t . '年後'),
          'age'           => is_int($baseAge) ? ($baseAge + $t) . '歳' : '—',
          'sozoku_before' => $sozokuBefore,
          'gift_before'   => $giftBefore,
          'total_before'  => $totalBefore,
          'sozoku_after'  => $sozokuAfter,
          'gift_after'    => $giftAfter,
          'total_after'   => $totalAfter,
          'diff'          => $diff,
      ];
  };

  $effectRows = [];
  for ($t = 0; $t <= 20; $t++) {
      $effectRows[] = $buildEffectRow($t);
  }


  // ▼ 受贈者の対象 No を決定（将来 UI からの選択に対応するため変数化）
  //   - request('beneficiary_no') に指定があればそれを採用
  //   - 未指定の場合は従来どおり No2 をデフォルトとする
  $benefNo = (int)(request()->input('beneficiary_no', 2));
  if ($benefNo < 2 || $benefNo > 10) {
      $benefNo = 2;
  }
  
  // 受贈者No2 ～ 10 指定した受贈者の財産の推移を表示する
  $benefNo = 2;

  
  $benefIndex = (string)$benefNo; // $family 配列用のキー（1..10）

  // ▼ 受贈者の氏名：family → prefillFamily → old()/request() の順で決定
  $benefNameCandidates = [
      \Illuminate\Support\Arr::get($family ?? [],       $benefIndex . '.name'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [],$benefIndex . '.name'),
      old('name.' . $benefIndex, null),
      request()->input('name.' . $benefIndex),
  ];
  $benefName = '(未設定)';
  foreach ($benefNameCandidates as $cand) {
      $cand = is_string($cand) ? trim($cand) : '';
      if ($cand !== '') { $benefName = $cand; break; }
  }

  // ▼ 続柄マスタは config/relationships.php を唯一の参照元にする
  $relationships = config('relationships', []);  

  // ▼ 続柄コード（relationship_code）を取得
  //   family[benefIndex]['relationship_code'] を最優先で参照し、
  //   無ければ prefillFamily / old()/request() をフォールバックとして見る。
  $benefRelCode = \Illuminate\Support\Arr::get($family ?? [], $benefIndex . '.relationship_code');
  if ($benefRelCode === null) {
      $benefRelCode = \Illuminate\Support\Arr::get($prefillFamily ?? [], $benefIndex . '.relationship_code');
  }
  if ($benefRelCode === null) {
      $tmp = old('relationship_code.' . $benefIndex, null)
          ?? request()->input('relationship_code.' . $benefIndex);
      if ($tmp !== null && trim((string)$tmp) !== '') {
          $benefRelCode = $tmp;
      }
  }
  if ($benefRelCode === null || trim((string)$benefRelCode) === '') {
      $benefRelCode = '(未設定)';
  }

  // ▼ relationship_code → ラベル変換（0〜41 → 本人／長男など）
  $benefRelLabel = '(未設定)';
  if ($benefRelCode !== '(未設定)') {
      // "5" / "05" / "5番" 等でも数値部分だけ取り出す
      $code = (int)preg_replace('/[^\d\-]/u', '', (string)$benefRelCode);
      if (array_key_exists($code, $relationships)) {
          $benefRelLabel = $relationships[$code];
      } else {
          // 未定義コードならコードそのものを表示
          $benefRelLabel = $benefRelCode;
      }
  }

  // ▼ 受贈者の基準年齢（t=0 時点の年齢）を決定
  //   family[benefIndex]['age'] → prefillFamily → old()/request() の順
  $benefAgeCandidates = [
      \Illuminate\Support\Arr::get($family ?? [],       $benefIndex . '.age'),
      \Illuminate\Support\Arr::get($prefillFamily ?? [],$benefIndex . '.age'),
      old('age.' . $benefIndex, null),
      request()->input('age.' . $benefIndex),
  ];
  $benefBaseAge = null;
  foreach ($benefAgeCandidates as $cand) {
      if ($cand === null) continue;
      $n = (int)preg_replace('/[^\d\-]/u', '', (string)$cand);
      if ($n >= 0 && $n <= 130) { $benefBaseAge = $n; break; }
  }


  // $family 側から row_no マッチで拾う（Collection<Model> / 配列どちらにも対応）
  if (isset($family) && is_iterable($family)) {
      foreach ($family as $row) {
          $rowNo = (int)(
              (is_array($row) ? ($row['row_no'] ?? null) : ($row->row_no ?? null))
          );
          if ($rowNo === $benefNo) {
              $name = is_array($row)
                  ? ($row['name'] ?? null)
                  : ($row->name ?? null);
              if (is_string($name) && trim($name) !== '') {
                  $benefName = trim($name);
                  break;
              }
          }
      }
  }

  // $family から取得できなかった場合のフォールバック
  if ($benefName === '(未設定)') {
      $benefNameCandidates = [
          old('name.' . $benefIndex, null),
          request()->input('name.' . $benefIndex),
          \Illuminate\Support\Arr::get($prefillFamily ?? [], $benefIndex . '.name'),
      ];
      foreach ($benefNameCandidates as $cand) {
          $cand = is_string($cand) ? trim($cand) : '';
          if ($cand !== '') { $benefName = $cand; break; }
      }
  }



  // $family に無ければ $prefillFamily を見る
  if ($benefRelCode === null && isset($prefillFamily) && is_iterable($prefillFamily)) {
      foreach ($prefillFamily as $row) {
          $rowNo = (int)(
              (is_array($row) ? ($row['row_no'] ?? null) : ($row->row_no ?? null))
          );
          if ($rowNo === $benefNo) {
              $benefRelCode = is_array($row)
                  ? ($row['relationship_code'] ?? null)
                  : ($row->relationship_code ?? null);
              break;
          }
      }
  }

  // ここまでで relationship_code が取れなければ old()/request() を最後の手段として見る
  if ($benefRelCode === null) {
      $tmp = old('relationship_code.' . $benefIndex, null)
          ?? request()->input('relationship_code.' . $benefIndex);
      if ($tmp !== null && trim((string)$tmp) !== '') {
          $benefRelCode = $tmp;
      }
  }

  // relationship_code が空なら未設定扱い
  if ($benefRelCode === null || trim((string)$benefRelCode) === '') {
      $benefRelCode = '(未設定)';
  }


  // ▼ 受贈者 No{benefNo} 用の「対策後 個人別財産推移」タイムライン
  // Service 側が after.persons[{No}].timeline[t] に以下のフィールドを供給している前提：
  //  - asset_total_yen                 … 所有財産の額（純資産・円）
  //  - gift_net_before_yen             … 贈与による純財産の増加（対策前ベース）
  //  - gift_calendar_received_yen      … 暦年贈与：受領額
  //  - gift_calendar_tax_yen           … 暦年贈与：贈与税
  //  - gift_settlement_received_yen    … 精算課税贈与：受領額
  //  - gift_settlement_tax_yen         … 精算課税贈与：贈与税
  //  - inherit_net_yen                 … 相続による純財産の増加
  //  - inherit_tax_yen                 … 相続税
  //  - investment_gain_yen             … 資産運用による増加額（★）
  //  - asset_after_yen                 … 財産の額（対策後）
  // 無い場合は 0（または空欄）表示になります。
  $personTimeline = \Illuminate\Support\Arr::get($r, 'after.persons.' . $benefIndex . '.timeline', []);








  // 金額フォーマッタ（3桁区切り、0は0表示）
  $yen = function($v) { $n = (int)($v ?? 0); return number_format($n); };
@endphp



@if(empty($after))
  <div class="alert alert-info my-3">
    計算結果が見つかりません。上部の<strong>「計算開始」</strong>を実行してから、再度このタブを表示してください。
  </div>
@else



    @php
      // 一時対応:
      // 「贈与後の相続税の課税価格」以下の結果表示をまとめて非表示にする。
      // 再表示するときは true → false に戻してください。
      $hideKazeikakakuDetailSections = true;
    @endphp

    @if(!$hideKazeikakakuDetailSections)



    {{-- ▼ 贈与後の相続税の課税価格 --}}

    <div class="mt-3">
      <h6 class="mb-1">贈与後の相続税の課税価格</h6>
      <div class="text-muted small mb-2">被相続人：{{ $heirName }}</div>
      
      
      <div class="table-responsive">
        {{-- 年次/年齢 を極小幅に固定するための専用CSS --}}
        <style>
          .table-compact-kekka {
            table-layout: fixed !important;
          }
          /* 年次/年齢 列を“超狭”に固定 */
          .table-compact-kekka col.col-xxs {
            width: 12px !important;
            max-width: 60px !important;
            min-width: 60px !important;
          }
          /* セルの内側余白もほぼゼロに */
          .table-compact-kekka th.col-xxs,
          .table-compact-kekka td.col-xxs {
            padding: 0 2px !important;
            font-size: 14px !important;
            line-height: 1 !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: clip !important;
          }
        </style>


        <table class="table table-sm table-bordered align-middle table-compact-kekka">
          <colgroup>
            <col class="col-xxs"><!-- 年次 -->
            <col class="col-xxs"><!-- 年齢 -->
            <col><!-- 所有財産の額 -->
            <col><!-- 贈与減（暦年） -->
            <col><!-- 贈与減（精算） -->
            <col><!-- 相続財産（贈与後） -->
            <col><!-- 加算累計（暦年） -->
            <col><!-- 加算累計（精算） -->
            <col><!-- 課税価格 -->
          </colgroup>

          <thead class="table-light">
            <tr>
              <th class="text-center col-xxs" rowspan="2">年次</th>
              <th class="text-center col-xxs" rowspan="2">年齢</th>
              <th class="text-center" rowspan="2" style="min-width:120px;">所有財産の額</th>
              <th class="text-center" colspan="2" style="min-width:180px;">贈与による財産の減少</th>
              <th class="text-center" rowspan="2" style="min-width:140px;">相続財産の額（贈与後）</th>
              <th class="text-center" colspan="2" style="min-width:180px;">贈与加算累計額</th>
              <th class="text-center" rowspan="2" style="min-width:120px;">課税価格</th>
            </tr>
            <tr>
              <th class="text-center">暦年贈与</th>
              <th class="text-center">精算課税贈与</th>
              <th class="text-center">暦年課税</th>
              <th class="text-center">精算課税贈与</th>
            </tr>
          </thead>
          <tbody>
@php
  /**
   * 文字列(カンマ付き等)を「数値(円)」へ安全に正規化してから「千円表示」にする
   */
  $toYenNum = function ($v): float {
      if ($v === null) return 0.0;
      if (is_int($v) || is_float($v)) return (float)$v;
      $s = trim((string)$v);
      if ($s === '') return 0.0;
      // カンマや通貨記号などを除去（- と . は許容）
      $s = preg_replace('/[^\d\.\-]/u', '', $s);
      if ($s === '' || $s === '-' || $s === '.') return 0.0;
      return (float)$s;
  };

  // 円 → 千円（四捨五入）でカンマ表示
  $fmtKyen = function ($yen) use ($toYenNum): string {
      $n = $toYenNum($yen);
      $k = (int)round($n / 1000);
      return number_format($k);
  };
@endphp


            @foreach($rows as $i => $r0)
              <tr>
                <td class="text-right col-xxs">{{ $r0['nenji'] }}</td>
                <td class="text-right col-xxs">{{ $r0['age'] }}</td>

                <td class="text-end">{{ $fmtKyen($r0['prop_amount'] ?? 0) }}</td>
                <td class="text-end">{{ $fmtKyen($r0['gift_decr_cal'] ?? 0) }}</td>
                <td class="text-end">{{ $fmtKyen($r0['gift_decr_set'] ?? 0) }}</td>
                <td class="text-end">{{ $fmtKyen($r0['estate_after'] ?? 0) }}</td>
                <td class="text-end">{{ $fmtKyen($r0['incl_cal'] ?? 0) }}</td>
                <td class="text-end">{{ $fmtKyen($r0['incl_set'] ?? 0) }}</td>
                
                </td>




                {{-- 補助：合算値が欲しい場合はツールチップで提示 --}}
                {{-- <td class="text-end text-muted" title="合計">{{ $yen($r0['incl_total']) }}</td> --}}
                <td class="text-end fw-bold">{{ $fmtKyen($r0['taxable'] ?? 0) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>



 
      {{-- ▼ 課税価格 と 生前贈与加算後の課税価格（相続人別内訳） --}}
      <div class="mt-4">
        <h6 class="mb-1">課税価格と生前贈与加算後の課税価格</h6>
        <div class="text-muted small mb-2">
          「贈与後（対策後）」シナリオにおける、年次ごとの課税価格と、生前贈与加算後の課税価格
          （相続人ごとの内訳）を表示します。
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle table-compact-kekka">
            <colgroup>
              <col class="col-xxs"><!-- 年次 -->
              <col><!-- 課税価格 -->
              <col><!-- 生前贈与加算後 合計 -->
              <col><!-- 相続人1 -->
              <col><!-- 相続人2 -->
              <col><!-- 相続人3 -->
              <col><!-- 相続人4 -->
              <col><!-- 相続人5 -->
              <col><!-- 相続人6 -->
              <col><!-- 相続人7 -->
              <col><!-- 相続人8 -->
              <col><!-- 相続人9 -->
            </colgroup>

            <thead class="table-light">
              <tr>
                <th class="text-center col-xxs" rowspan="2">年次</th>
                <th class="text-center" rowspan="2" style="min-width:120px;">課税価格</th>
                <th class="text-center" colspan="10" style="min-width:420px;">生前贈与加算後の課税価格</th>
              </tr>
              <tr>
                <th class="text-center">合計</th>
                <th class="text-center">相続人1</th>
                <th class="text-center">相続人2</th>
                <th class="text-center">相続人3</th>
                <th class="text-center">相続人4</th>
                <th class="text-center">相続人5</th>
                <th class="text-center">相続人6</th>
                <th class="text-center">相続人7</th>
                <th class="text-center">相続人8</th>
                <th class="text-center">相続人9</th>
              </tr>
          </thead>

            <tbody>
              @for($t = 0; $t <= 20; $t++)
                @php
                  // ★ t=0 は after.summary / after.per_idx、それ以降は projections.after[t] を参照
                  if ($t === 0) {
                      $summary = $after ?? [];
                      $perIdx  = ($r['after']['per_idx'] ?? []) ?: [];
                  } else {
                      $summary = ($projAfter[$t]['summary'] ?? []) ?: [];
                      $perIdx  = ($projAfter[$t]['per_idx']  ?? []) ?: [];
                  }

                  // 年次ラベル
                  $nenji = ($t === 0) ? '現時点' : ($t . '年後');

                  // 課税価格（基礎控除前）…上の表と同じロジック
                  $taxableBase = (int)(
                      $summary['kazei_price_yen']
                      ?? $summary['taxable_estate']
                      ?? 0
                  ) / 1000;
                @endphp


                @php
                  // 相続人2〜10 の P_i(t) を合計（price_for_ratio の実際の合計値）
                  $sumP = 0;
                  for ($j = 2; $j <= 10; $j++) {
                      $sumP += (int)($perIdx[$j]['price_for_ratio'] ?? 0) / 1000;
                  }
                @endphp

                <tr>
                  <td class="text-right col-xxs">{{ $nenji }}</td>
                  <td class="text-end">{{ $yen($taxableBase) }}</td>
                  {{-- 合計：相続人1〜9の P_i(t) の実際の合計 --}}
                  <td class="text-end fw-bold">{{ $yen($sumP) }}</td>
                  @for($i = 1; $i <= 9; $i++)
                    @php
                      // 相続人1〜9 → idx=2〜10 に対応させ、Service が計算した
                      // per_idx[idx]['price_for_ratio']（各相続人の生前贈与加算後の課税価格 P_i(t)）を表示する
                      $idx = $i + 1; // 相続人1→idx=2, 相続人2→idx=3, ...
                      $val = (int)($perIdx[$idx]['price_for_ratio'] ?? 0) / 1000;
                    @endphp
                    <td class="text-end">{{ $val !== 0 ? $yen($val) : '' }}</td>
                  @endfor
                </tr>


              @endfor
            </tbody>
          </table>
        </div>

        <!--
        <div class="small text-muted mt-1">
          ※課税価格は Service の <code>kazei_price_yen</code>（無い場合は <code>taxable_estate</code>）を使用しています。<br>
          ※「生前贈与加算後の課税価格（合計）」は、Service の課税価格と同じ値（理論上は各相続人の P_i の合計）を表示しています。<br>
          ※相続人1〜9欄は、Service 側で付与された <code>per_idx[idx]['price_for_ratio']</code>
            （各相続人の生前贈与加算後の課税価格 P_i(t)）を表示しています。
        </div>
        -->
      </div>


      {{-- ▼ 課税価格・基礎控除額・課税遺産総額（相続人別内訳）の中間計算表 --}}
      <div class="mt-4">
        <h6 class="mb-1">課税価格・基礎控除額等の計算過程</h6>
        <div class="text-muted small mb-2">
          「贈与後（対策後）」シナリオにおける、年次ごとの課税価格・基礎控除額・基礎控除後・課税遺産総額
          および相続人別内訳を表示します。
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle table-compact-kekka">
            <colgroup>
              <col class="col-xxs"><!-- 年次 -->
              <col><!-- 課税価格 -->
              <col><!-- 基礎控除額 -->
              <col><!-- 基礎控除後 -->
              <col><!-- 課税遺産総額 合計 -->
              <col><!-- 相続人1 -->
              <col><!-- 相続人2 -->
              <col><!-- 相続人3 -->
              <col><!-- 相続人4 -->
              <col><!-- 相続人5 -->
              <col><!-- 相続人6 -->
              <col><!-- 相続人7 -->
              <col><!-- 相続人8 -->
              <col><!-- 相続人9 -->
            </colgroup>

            <thead class="table-light">
              <tr>
                <th class="text-center col-xxs" rowspan="2">年次</th>
                <th class="text-center" rowspan="2" style="min-width:120px;">課税価格</th>
                <th class="text-center" rowspan="2" style="min-width:120px;">基礎控除額</th>
                <th class="text-center" rowspan="2" style="min-width:120px;">基礎控除後</th>
                <th class="text-center" colspan="10" style="min-width:420px;">課税遺産総額</th>
              </tr>
              <tr>
                <th class="text-center">合計</th>
                <th class="text-center">相続人1</th>
                <th class="text-center">相続人2</th>
                <th class="text-center">相続人3</th>
                <th class="text-center">相続人4</th>
                <th class="text-center">相続人5</th>
                <th class="text-center">相続人6</th>
                <th class="text-center">相続人7</th>
                <th class="text-center">相続人8</th>
                <th class="text-center">相続人9</th>
              </tr>
            </thead>

             <tbody>
              @for($t = 0; $t <= 20; $t++)
                @php
                  // ★ t=0 は after.summary / after.heirs、それ以降は projections.after[t] を参照
                  if ($t === 0) {
                      $summary   = $after ?? [];
                      $heirsRows = ($r['after']['heirs'] ?? []) ?: [];
                  } else {
                      $summary   = ($projAfter[$t]['summary'] ?? []) ?: [];
                      $heirsRows = ($projAfter[$t]['heirs']   ?? []) ?: [];
                  }



                  // row_index => 行 のマップを作成（相続人2〜10を拾う）
                  $heirsByIdx = [];
                  foreach ($heirsRows as $hr) {
                      $idx = $hr['row_index'] ?? null;
                      if ($idx !== null) {
                          $heirsByIdx[(int)$idx] = $hr;
                      }
                  }

                  // 年次ラベル
                  $nenji = ($t === 0) ? '現時点' : ($t . '年後');

                  // 課税価格（基礎控除前）…上の表と同じロジック
                  // 'taxable' => (int)($summary['kazei_price_yen'] ?? $summary['taxable_estate'] ?? 0),
                  $taxableBase = (int)(
                      $summary['kazei_price_yen']
                      ?? $summary['taxable_estate']
                      ?? 0
                  ) / 1000;

                  // 基礎控除額
                  $basicDed = (int)(
                      $summary['basic_deduction']
                      ?? $summary['basic_deduction_yen']
                      ?? 0
                  ) / 1000;

                  // 基礎控除後（0 未満にならないようにガード）
                  $afterBasic = max(0, $taxableBase - $basicDed);

                  // 課税遺産総額（合計）
                  $taxableEstate = (int)($summary['taxable_estate'] ?? $afterBasic) / 1000;
                  

                @endphp

                <tr>
                  <td class="text-right col-xxs">{{ $nenji }}</td>
                  <td class="text-end">{{ $yen($taxableBase) }}</td>
                  <td class="text-end">{{ $yen($basicDed) }}</td>
                  <td class="text-end">{{ $yen($afterBasic) }}</td>
                  <td class="text-end fw-bold">{{ $yen($taxableEstate) }}</td>
                  @for($i = 1; $i <= 9; $i++)
                    @php
                      // 相続人1〜9 → idx=2〜10 を対応させ、Service が計算済みの
                      // heirs[].taxable_share_yen（各相続人の基礎控除後課税遺産額）を表示する
                      $idx = $i + 1;
                      $row = $heirsByIdx[$idx] ?? [];
                      $val = (int)($row['taxable_share_yen'] ?? 0) / 1000;
                    @endphp
                    <td class="text-end">{{ $val !== 0 ? $yen($val) : '' }}</td>
                  @endfor
                </tr>
              @endfor
             </tbody>


          </table>
        </div>

        <!--
        <div class="small text-muted mt-1">
          ※課税価格は Service の <code>kazei_price_yen</code>（無い場合は <code>taxable_estate</code>）を使用しています。<br>
          ※課税遺産総額（合計）は <code>taxable_estate</code>（無い場合は「課税価格 − 基礎控除額」）を表示しています。<br>
          ※相続人1〜9欄は、Service 側で <code>per_idx[*]['price_for_ratio']</code> が供給されている前提で表示しています。
        </div>
        -->
      </div>
 



      {{-- ▼ 暦年課税贈与税控除額（相続人別内訳） --}}
      <div class="mt-4">
        <h6 class="mb-1">暦年課税贈与税控除額</h6>
        <div class="text-muted small mb-2">
          「贈与後（対策後）」シナリオにおける、年次ごとの暦年課税贈与税控除額
          （相続時精算課税分ではなく、暦年課税分の贈与税額控除）を相続人別に表示します。
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle table-compact-kekka">
            <colgroup>
              <col class="col-xxs"><!-- 年次 -->
              <col><!-- 相続人1 -->
              <col><!-- 相続人2 -->
              <col><!-- 相続人3 -->
              <col><!-- 相続人4 -->
              <col><!-- 相続人5 -->
              <col><!-- 相続人6 -->
              <col><!-- 相続人7 -->
              <col><!-- 相続人8 -->
              <col><!-- 相続人9 -->
              <col><!-- 合計 -->
            </colgroup>

            <thead class="table-light">
              <tr>
                <th class="text-center col-xxs">年次</th>
                <th class="text-center">相続人1</th>
                <th class="text-center">相続人2</th>
                <th class="text-center">相続人3</th>
                <th class="text-center">相続人4</th>
                <th class="text-center">相続人5</th>
                <th class="text-center">相続人6</th>
                <th class="text-center">相続人7</th>
                <th class="text-center">相続人8</th>
                <th class="text-center">相続人9</th>
                <th class="text-center" style="min-width:120px;">合計</th>
              </tr>
            </thead>

            <tbody>
              @for($t = 0; $t <= 20; $t++)
                @php
                  // ★ t=0 は after.heirs、それ以降は projections.after[t].heirs を参照
                  if ($t === 0) {
                      $heirsRows = ($r['after']['heirs'] ?? []) ?: [];
                  } else {
                      $heirsRows = ($projAfter[$t]['heirs'] ?? []) ?: [];
                  }

                  // row_index => 行 のマップを作成（相続人2〜10を拾う）
                  $heirsByIdx = [];
                  foreach ($heirsRows as $hr) {
                      $idx = $hr['row_index'] ?? null;
                      if ($idx !== null) {
                          $heirsByIdx[(int)$idx] = $hr;
                      }
                  }

                  // 年次ラベル
                  $nenji = ($t === 0) ? '現時点' : ($t . '年後');

                  // 合計用カウンタ
                  $rowTotal = 0;
                @endphp

                <tr>
                  <td class="text-right col-xxs">{{ $nenji }}</td>
                  @for($i = 1; $i <= 9; $i++)
                    @php
                      // 相続人1〜9 → idx=2〜10 を対応させ、Service が計算済みの
                      // heirs[].gift_tax_credit_calendar_yen（暦年課税贈与税控除額）を表示
                      $idx = $i + 1;
                      $row = $heirsByIdx[$idx] ?? [];
                      $val = (int)($row['gift_tax_credit_calendar_yen'] ?? 0) / 1000;
                      $rowTotal += $val;
                    @endphp
                    <td class="text-end">{{ $val !== 0 ? $yen($val) : '' }}</td>
                  @endfor
                  <td class="text-end fw-bold">{{ $yen($rowTotal) }}</td>
                </tr>
              @endfor
            </tbody>
          </table>
        </div>

        <!--
        <div class="small text-muted mt-1">
          ※各相続人欄は、Service 側で付与された <code>heirs[].gift_tax_credit_calendar_yen</code>
            （暦年課税分の贈与税額控除額）を表示しています。<br>
          ※合計欄は相続人1〜9欄の合計値です。
        </div>
        -->
      </div>








 
      {{-- ▼ 贈与による節税効果の時系列比較 --}}
      {{-- ※ 相続税欄は「相続時精算課税分の贈与税額控除額」控除後の金額（final_after_settlement_yen）を円単位で表示 --}}
      
      <div class="mt-4">
        <h6 class="mb-1">贈与による節税効果の時系列比較</h6>
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle table-compact-kekka">
            <colgroup>
              <col class="col-xxs"><!-- 年次 -->
              <col class="col-xxs"><!-- 年齢 -->
              <col><!-- 対策前 相続税 -->
              <col><!-- 対策前 贈与税累計額 -->
              <col><!-- 対策前 合計 -->
              <col><!-- 対策後 相続税 -->
              <col><!-- 対策後 贈与税累計額 -->
              <col><!-- 対策後 合計 -->
              <col><!-- 差額 -->
            </colgroup>

            <thead class="table-light">
              <tr>
                <th class="text-center col-xxs" rowspan="2">年次</th>
                <th class="text-center col-xxs" rowspan="2">年齢</th>
                <th class="text-center" colspan="3" style="min-width:210px;">対策前</th>
                <th class="text-center" colspan="3" style="min-width:210px;">対策後</th>
                <th class="text-center" rowspan="2" style="min-width:150px;">差額（対策後 − 対策前）</th>
              </tr>
              <tr>
                <th class="text-center">相続税</th>
                <th class="text-center">贈与税累計額</th>
                <th class="text-center">合計</th>
                <th class="text-center">相続税</th>
                <th class="text-center">贈与税累計額</th>
                <th class="text-center">合計</th>
              </tr>
            </thead>

            <tbody>
              @foreach($effectRows as $er)
                <tr>
                  <td class="text-right col-xxs">{{ $er['nenji'] }}</td>
                  <td class="text-right col-xxs">{{ $er['age'] }}</td>
                  <td class="text-end">{{ $yen($er['sozoku_before']/1000) }}</td>
                  <td class="text-end">{{ $yen($er['gift_before']/1000) }}</td>
                  <td class="text-end fw-bold">{{ $yen($er['total_before']/1000) }}</td>
                  <td class="text-end">{{ $yen($er['sozoku_after']/1000) }}</td>
                  <td class="text-end">{{ $yen($er['gift_after']/1000) }}</td>
                  <td class="text-end fw-bold">{{ $yen($er['total_after']/1000) }}</td>
                  <td class="text-end fw-bold">{{ $yen($er['diff']/1000) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <!--
        <div class="small text-muted mt-1">
          ※「相続税」欄は、summary に格納された「相続時精算課税分の贈与税額控除額」控除後の金額
            （final_after_settlement_yen）を基に円単位で表示しています。<br>
          ※贈与税累計額および合計も同様に、summary の値（円）をそのまま使用しています。
        </div>
        -->

      </div>

{{--
@php
\Log::info('kekka_kazeikakaku family dump', [
    'benefNo' => $benefNo ?? null,
    'family_keys' => isset($family) && is_iterable($family)
        ? collect($family)->map(function($row) {
            return [
                'row_no'            => is_array($row) ? ($row['row_no'] ?? null) : ($row->row_no ?? null),
                'relationship_code' => is_array($row) ? ($row['relationship_code'] ?? null) : ($row->relationship_code ?? null),
            ];
        })->values()
        : [],
]);
@endphp
--}}

      {{-- ▼ 対策後の各人別財産の推移（受贈者 No{{ $benefNo }}） --}}
      <div class="mt-4">
        <h6 class="mb-1">対策後の各人別財産の推移（受贈者 No{{ $benefNo }}）</h6>
        
        <div class="text-muted small mb-2">
          受贈者 No{{ $benefNo }}：{{ $benefName }}（続柄：{{ $benefRelLabel }}）
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle table-compact-kekka">
            <colgroup>
              <col class="col-xxs"><!-- 年次 -->
              <col class="col-xxs"><!-- 年齢 -->
              <col><!-- 所有財産の額 -->
              <col><!-- 暦年贈与：受領額 -->
              <col><!-- 暦年贈与：贈与税 -->
              <col><!-- 精算課税贈与：受領額 -->
              <col><!-- 精算課税贈与：贈与税 -->
              <col><!-- 相続による純財産の増加 相続財産-->
              <col><!-- 相続による純財産の増加 相続税-->
              <col><!-- 資産運用による増加額 -->
              <col><!-- 財産の額（対策後） -->
            </colgroup>

            <thead class="table-light">
              <tr>
                <th class="text-center col-xxs" rowspan="3">年次</th>
                <th class="text-center col-xxs" rowspan="3">年齢</th>
                <th class="text-center" rowspan="3" style="min-width:140px;">所有財産の額（対策前）</th>
                <th class="text-center" colspan="4" style="min-width:320px;">贈与による純財産の増加</th>
                <th class="text-center" colspan="2" style="min-width:140px;">相続による純財産の増加</th>
                <th class="text-center" rowspan="3" style="min-width:140px;">資産運用による増加額（★）</th>
                <th class="text-center" rowspan="3" style="min-width:140px;">財産の額（対策後）</th>
              </tr>
              <tr>
                <th class="text-center" colspan="2" >暦年贈与</th>
                <th class="text-center" colspan="2" >精算課税贈与</th>
                <th class="text-center" rowspan="2" >相続財産</th>
                <th class="text-center" rowspan="2" >相続税</th>
              </tr>
              <tr>
                <th class="text-center">受領額</th>
                <th class="text-center">贈与税</th>
                <th class="text-center">受領額</th>
                <th class="text-center">贈与税</th>
              </tr>
            </thead>

            <tbody>
              @for($t = 0; $t <= 20; $t++)

                @php
                  // 年次ラベル
                  $nenji = ($t === 0) ? '現時点' : ($t . '年後');

                  // Service 側から受け取る 1人分のタイムライン行（受贈者 No{benefNo}）
                  $pRow = $personTimeline[$t] ?? [];

                  // 年齢は「表示対象の受贈者」の年齢：t=0 時点の年齢（benefBaseAge）＋ t 年
                  $pAgeLabel = is_int($benefBaseAge) ? ($benefBaseAge + $t) . '歳' : '—';

                  $assetTotal          = (int)($pRow['asset_total_yen']              ?? 0) / 1000;
                  $giftNetBefore       = (int)($pRow['gift_net_before_yen']          ?? 0) / 1000;
                  $giftCalReceived     = (int)($pRow['gift_calendar_received_yen']   ?? 0) / 1000;
                  $giftCalTax          = (int)($pRow['gift_calendar_tax_yen']        ?? 0) / 1000;
                  $giftSetReceived     = (int)($pRow['gift_settlement_received_yen'] ?? 0) / 1000;
                  $giftSetTax          = (int)($pRow['gift_settlement_tax_yen']      ?? 0) / 1000;
                  $inheritNet          = (int)($pRow['inherit_net_yen']              ?? 0) / 1000;
                  $inheritTax          = (int)($pRow['inherit_tax_yen']              ?? 0) / 1000;
                  $investGain          = (int)($pRow['investment_gain_yen']          ?? 0) / 1000;
                  $assetAfter          = (int)($pRow['asset_after_yen']              ?? 0) / 1000;

                  // 表示用：t=0 の贈与関連は「-」固定、それ以降は金額表示
                  $giftCalReceivedDisp = ($t === 0)
                      ? '-'
                      : ($giftCalReceived !== 0 ? $yen($giftCalReceived) : '');
                  $giftCalTaxDisp = ($t === 0)
                      ? '-'
                      : ($giftCalTax !== 0 ? $yen($giftCalTax) : '');
                  $giftSetReceivedDisp = ($t === 0)
                      ? '-'
                      : ($giftSetReceived !== 0 ? $yen($giftSetReceived) : '');
                  $giftSetTaxDisp = ($t === 0)
                      ? '-'
                      : ($giftSetTax !== 0 ? $yen($giftSetTax) : '');



                  // 「-」表示のときだけ中央寄せにするクラス
                  $giftCellClass = ($t === 0) ? 'text-center' : 'text-end';


                @endphp

                <tr>
                  <td class="text-right col-xxs">{{ $nenji }}</td>
                  <td class="text-right col-xxs">{{ $pAgeLabel }}</td>
                  <td class="text-end">{{ $assetTotal !== 0 ? $yen($assetTotal) : '' }}</td>

                  <td class="{{ $giftCellClass }}">{{ $giftCalReceivedDisp }}</td>
                  <td class="{{ $giftCellClass }}">{{ $giftCalTaxDisp }}</td>
                  <td class="{{ $giftCellClass }}">{{ $giftSetReceivedDisp }}</td>
                  <td class="{{ $giftCellClass }}">{{ $giftSetTaxDisp }}</td>

                  <td class="text-end">{{ $inheritNet !== 0 ? $yen($inheritNet) : '' }}</td>
                  <td class="text-end">{{ $inheritTax !== 0 ? $yen($inheritTax) : '' }}</td>
                  <td class="text-end">{{ $investGain !== 0 ? $yen($investGain) : '' }}</td>
                  <td class="text-end">{{ $assetAfter !== 0 ? $yen($assetAfter) : '' }}</td>
                </tr>
              @endfor
            </tbody>
          </table>
        </div>

        <!--
        <div class="small text-muted mt-1">
          ※各項目（所有財産の額・贈与による純財産の増加〔暦年贈与（受領額／贈与税）／精算課税贈与（受領額／贈与税）〕・
            相続による純財産の増加・相続税・資産運用による増加額・財産の額（対策後））の定義は
            Service 側の仕様に準じます。
        </div>
        -->
      </div>

      <div class="small text-muted">
       </div>
     </div>
 


    @endif


@endif

