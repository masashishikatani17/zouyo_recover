<!--   pdf_shitei.blade  -->
{{-- PDF指定タブ --}}
@php

    // ▼ セッションから PDF 選択状態を取得
    //    新仕様: zouyo.pdf_state.{dataId}
    //    旧仕様: zouyo.pdf_pages.{dataId}（A4のみ）
    $pdfState = session()->get("zouyo.pdf_state.{$dataId}", []);
    $legacyA4Pages = session()->get("zouyo.pdf_pages.{$dataId}", []);

    $selectedPaperSize = strtoupper((string)($pdfState['paper_size'] ?? 'A4'));
    if (!in_array($selectedPaperSize, ['A4', 'A3'], true)) {
        $selectedPaperSize = 'A4';
    }

    $pdfSelectedPagesA4 = $pdfState['pages_a4'] ?? $legacyA4Pages ?? [];
    $pdfSelectedPagesA3 = $pdfState['pages_a3'] ?? [];

    $pdfSelectedPagesA4 = array_values(array_unique(array_map('intval', (array)$pdfSelectedPagesA4)));
    $pdfSelectedPagesA3 = array_values(array_unique(array_map('intval', (array)$pdfSelectedPagesA3)));
@endphp

<div class="mt-3">
    <h6 class="mb-2">PDF指定</h6>

    <style>
      .pdf-paper-select {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
        margin-bottom: 12px;
      }
      .pdf-page-select-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(280px, 1fr));
        gap: 16px;
        margin-top: 8px;
        margin-bottom: 12px;
      }
      .pdf-page-select-card {
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 12px 14px;
        background: #fff;
        transition: opacity .15s ease, background-color .15s ease, border-color .15s ease;
      }
      .pdf-page-select-card.is-inactive {
        opacity: .55;
        background: #f8f9fa;
      }
      .pdf-page-select-card h6 {
        margin: 0 0 10px 0;
        font-weight: 700;
      }
      .pdf-page-select-card label {
        display: block;
        margin-bottom: 6px;
      }
      
      .pdf-page-select-fieldset {
        border: 0;
        margin: 0;
        padding: 0;
        min-width: 0;
      }

      @media (max-width: 767.98px) {
        .pdf-page-select-grid {
          grid-template-columns: 1fr;
        }
      }
    </style>

    <div class="pdf-paper-select">
      <span class="fw-bold">PDFサイズ</span>
      <label class="mb-0">
        <input
          type="radio"
          name="paper_size"
          value="A4"
          class="paper-size-radio"
          form="zouyo-pdf-form"
          @checked($selectedPaperSize === 'A4')
        >
        A4サイズ
      </label>
      <label class="mb-0">
        <input
          type="radio"
          name="paper_size"
          value="A3"
          class="paper-size-radio"
          form="zouyo-pdf-form"
          @checked($selectedPaperSize === 'A3')
        >
        A3サイズ
      </label>
    </div>

    <div class="pdf-page-select-grid">
      <div
        id="pdf-page-card-a4"
        class="pdf-page-select-card {{ $selectedPaperSize === 'A4' ? '' : 'is-inactive' }}"
        data-paper-card="A4"
      >
        <h6>A4サイズ用ページ選択</h6>

        <fieldset class="pdf-page-select-fieldset" {{ $selectedPaperSize === 'A4' ? '' : 'disabled' }}>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="0" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(0, $pdfSelectedPagesA4, true))> 表紙
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="1" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(1, $pdfSelectedPagesA4, true))> はじめに
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="2" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(2, $pdfSelectedPagesA4, true))> 暦年課税と相続時精算課税の比較
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="3" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(3, $pdfSelectedPagesA4, true))> 贈与税、相続税の速算表
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="4" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(4, $pdfSelectedPagesA4, true))> 家族構成、所有財産など
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="5" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(5, $pdfSelectedPagesA4, true))> 各人別贈与額および贈与税
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="6" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(6, $pdfSelectedPagesA4, true))> 贈与後の相続税の課税価格
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="7" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(7, $pdfSelectedPagesA4, true))> 各人別相続税額の試算
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="8" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(8, $pdfSelectedPagesA4, true))> 贈与による節税効果の時系列比較
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="9" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(9, $pdfSelectedPagesA4, true))> 対策後の各人別財産の推移
            </label>
    
            <label>
                <input type="checkbox" name="pages_a4[]" value="10" class="page-checkbox" data-paper-size="A4" form="zouyo-pdf-form" @checked(in_array(10, $pdfSelectedPagesA4, true))> おわりに
            </label>

        </fieldset>

      </div>

      <div
        id="pdf-page-card-a3"
        class="pdf-page-select-card {{ $selectedPaperSize === 'A3' ? '' : 'is-inactive' }}"
        data-paper-card="A3"
      >
        <h6>A3サイズ用ページ選択</h6>

        <fieldset class="pdf-page-select-fieldset" {{ $selectedPaperSize === 'A3' ? '' : 'disabled' }}>

            <label>
                <input type="checkbox" name="pages_a3[]" value="0" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(0, $pdfSelectedPagesA3, true))> 表紙
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="1" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(1, $pdfSelectedPagesA3, true))> はじめに
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="2" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(2, $pdfSelectedPagesA3, true))> 比較説明
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="3" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(3, $pdfSelectedPagesA3, true))> 家族構成贈与プラン
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="4" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(4, $pdfSelectedPagesA3, true))> 各人別贈与額
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="5" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(5, $pdfSelectedPagesA3, true))> 各人別相続税
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="6" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(6, $pdfSelectedPagesA3, true))> 贈与後の相続税
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="7" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(7, $pdfSelectedPagesA3, true))> 相続人別財産の推移
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="8" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(8, $pdfSelectedPagesA3, true))> 各人別財産の推移
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="9" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(9, $pdfSelectedPagesA3, true))> おわりに
            </label>
            
        </fieldset>
        
      </div>
    </div>

    <div>
      <button
          type="button"
          id="select-all"
          onclick="(function(){
              var checkedPaper = document.querySelector('.paper-size-radio:checked');
              var paperSize = checkedPaper ? checkedPaper.value : 'A4';
              document.querySelectorAll('.page-checkbox[form=&quot;zouyo-pdf-form&quot;][data-paper-size=&quot;' + paperSize + '&quot;]').forEach(function(cb){
                  cb.checked = true;
              });
          })();"
      >すべて選択</button>
      <button
          type="button"
          id="deselect-all"
          onclick="(function(){
              var checkedPaper = document.querySelector('.paper-size-radio:checked');
              var paperSize = checkedPaper ? checkedPaper.value : 'A4';
              document.querySelectorAll('.page-checkbox[form=&quot;zouyo-pdf-form&quot;][data-paper-size=&quot;' + paperSize + '&quot;]').forEach(function(cb){
                  cb.checked = false;
              });
          })();"
      >すべて解除</button>
    </div>

    <button
        type="button"
        id="submit-form-button"
        data-action="{{ route('generate_pdf') }}"
        data-data-id="{{ (string)$dataId }}"
        onclick="(function(btn){
            var checkedPaper = document.querySelector('.paper-size-radio:checked');
            var paperSize = checkedPaper ? checkedPaper.value : 'A4';

            var checkedBoxes = document.querySelectorAll(
                '.page-checkbox[form=&quot;zouyo-pdf-form&quot;][data-paper-size=&quot;' + paperSize + '&quot;]:checked'
            );

            if (!checkedBoxes || checkedBoxes.length === 0) {
                alert('PDFにする項目が選択されていません。');
                return;
            }

            var winName = 'zouyoPdfWin';
            var w = window.open('', winName);
            if (!w) {
                alert('ポップアップがブロックされています。ブラウザ設定で許可してください。');
                return;
            }

            var action = btn.getAttribute('data-action');
            var dataId = btn.getAttribute('data-data-id');

            if (!action) { w.document.write('PDF action not found.'); return; }

            var meta = document.querySelector('meta[name=&quot;csrf-token&quot;]');
            var token = meta ? meta.getAttribute('content') : null;
            if (!token)  { w.document.write('CSRF token not found.'); return; }

            var f = document.createElement('form');
            f.method = 'POST';
            f.action = action;
            f.target = winName;

            var t = document.createElement('input');
            t.type = 'hidden';
            t.name = '_token';
            t.value = token;
            f.appendChild(t);

            var d = document.createElement('input');
            d.type = 'hidden';
            d.name = 'data_id';
            d.value = dataId || '';
            f.appendChild(d);

            var ps = document.createElement('input');
            ps.type = 'hidden';
            ps.name = 'paper_size';
            ps.value = paperSize;
            f.appendChild(ps);

            checkedBoxes.forEach(function(cb){
                var p = document.createElement('input');
                p.type = 'hidden';
                p.name = cb.name;
                p.value = cb.value;
                f.appendChild(p);
            });

            document.body.appendChild(f);
            f.submit();
            document.body.removeChild(f);
        })(this);"
    >PDF作成</button>

    <script>
      (function() {
        function syncPaperCards() {
          var checkedPaper = document.querySelector('.paper-size-radio:checked');
          var paperSize = checkedPaper ? checkedPaper.value : 'A4';
          var a4Card = document.getElementById('pdf-page-card-a4');
          var a3Card = document.getElementById('pdf-page-card-a3');
          var a4Fieldset = a4Card ? a4Card.querySelector('.pdf-page-select-fieldset') : null;
          var a3Fieldset = a3Card ? a3Card.querySelector('.pdf-page-select-fieldset') : null;

          if (a4Card) {
            a4Card.classList.toggle('is-inactive', paperSize !== 'A4');
          }
          if (a3Card) {
            a3Card.classList.toggle('is-inactive', paperSize !== 'A3');
          }
          if (a4Fieldset) {
            a4Fieldset.disabled = (paperSize !== 'A4');
          }
          if (a3Fieldset) {
            a3Fieldset.disabled = (paperSize !== 'A3');
          }

        }

        document.querySelectorAll('.paper-size-radio').forEach(function(radio) {
          radio.addEventListener('change', syncPaperCards);
        });

        syncPaperCards();
      })();
    </script>
</div>

