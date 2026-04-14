<!--   pdf_shitei.blade  -->
{{-- PDF指定タブ --}}
@php

    // ▼ セッションから PDF 選択状態を取得
    //    A4PDF は廃止のため、指定画面は A3 固定
     $pdfState = session()->get("zouyo.pdf_state.{$dataId}", []);
     $pdfSelectedPagesA3 = $pdfState['pages_a3'] ?? [];

     $pdfSelectedPagesA3 = array_values(array_unique(array_map('intval', (array)$pdfSelectedPagesA3)));
     // 「各人別相続税」(page id: 6) は廃止
     $pdfSelectedPagesA3 = array_values(array_filter(
         $pdfSelectedPagesA3,
         fn (int $pageId) => $pageId !== 6
     ));

@endphp

<div class="mt-3">
    <h6 class="mb-2">PDF指定</h6>

    <style>
      .pdf-page-select-grid {
        max-width: 520px;
        margin-bottom: 12px;
      }
      .pdf-page-select-card {
        border: 1px solid #ced4da;
        border-radius: 6px;
        padding: 12px 14px;
        background: #fff;
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
          max-width: 100%;
        }
      }
    </style>

      <div
        id="pdf-page-card-a3"
        class="pdf-page-select-card"
        data-paper-card="A3"
      >
        <h6>ページ選択  (用紙サイズはすべてA3横)</h6>

        <fieldset class="pdf-page-select-fieldset">

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
                <input type="checkbox" name="pages_a3[]" value="3" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(3, $pdfSelectedPagesA3, true))> 家族構成相続税額
            </label>
    
            <label>
                <input type="checkbox" name="pages_a3[]" value="4" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(4, $pdfSelectedPagesA3, true))> 贈与プラン
            </label>
     
            <label>
                <input type="checkbox" name="pages_a3[]" value="5" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(5, $pdfSelectedPagesA3, true))> 各人別贈与額
            </label>
     
            <label>
                <input type="checkbox" name="pages_a3[]" value="7" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(7, $pdfSelectedPagesA3, true))> 贈与後の相続税
            </label>
     
            <label>
                <input type="checkbox" name="pages_a3[]" value="8" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(8, $pdfSelectedPagesA3, true))> 相続人別財産の推移
            </label>
     
            <label>
                <input type="checkbox" name="pages_a3[]" value="9" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(9, $pdfSelectedPagesA3, true))> 各人別財産の推移
            </label>
     
            <label>
                <input type="checkbox" name="pages_a3[]" value="10" class="page-checkbox" data-paper-size="A3" form="zouyo-pdf-form" @checked(in_array(10, $pdfSelectedPagesA3, true))> おわりに
            </label>
             
        </fieldset>
        
      </div>
    </div>

    <div>
      <button
          type="button"
          id="select-all"
          onclick="(function(){
              document.querySelectorAll('.page-checkbox[form=&quot;zouyo-pdf-form&quot;][data-paper-size=&quot;A3&quot;]').forEach(function(cb){
                  cb.checked = true;
              });
          })();"
      >すべて選択</button>
      <button
          type="button"
          id="deselect-all"
          onclick="(function(){
              document.querySelectorAll('.page-checkbox[form=&quot;zouyo-pdf-form&quot;][data-paper-size=&quot;A3&quot;]').forEach(function(cb){
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
            var paperSize = 'A3';

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

</div>

