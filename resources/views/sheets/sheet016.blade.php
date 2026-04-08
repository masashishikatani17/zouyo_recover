<div class="container">
  <h2>遺産分割方法の入力</h2>

  <form method="POST" action="{{ route('sheet016.submit') }}">
    @csrf

    <div class="mb-3">
      <label>遺産分割方法</label>
      <select name="division_method" class="form-select">
        <option value="legal">法定相続割合</option>
        <option value="manual">手入力</option>
      </select>
    </div>

    <div class="mb-3">
      <label>遺産合計額（円）</label>
      <input type="number" name="total_estate" class="form-control">
    </div>

    <div class="mb-3">
      <label>各相続人の配分（例：渡邉淳 50%）</label>
      <textarea name="heirs_allocation" class="form-control" rows="5" placeholder="名前：割合%（手入力の場合のみ）"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">保存</button>
  </form>
</div>
