<div class="container">
  <h2>過年度の贈与入力</h2>

  <form method="POST" action="{{ route('sheet014.submit') }}">
    @csrf

    <div class="row mb-3">
      <div class="col">
        <label>贈与者名</label>
        <input type="text" name="donor" class="form-control">
      </div>
      <div class="col">
        <label>受贈者名</label>
        <input type="text" name="recipient" class="form-control">
      </div>
    </div>

    <div class="mb-3">
      <label>贈与年月日</label>
      <input type="date" name="donation_date" class="form-control">
    </div>

    <div class="mb-3">
      <label>贈与額（円）</label>
      <input type="number" name="donation_amount" class="form-control">
    </div>

    <div class="mb-3">
      <label>贈与財産の種類</label>
      <select name="asset_type" class="form-select">
        <option value="現金">現金</option>
        <option value="不動産">不動産</option>
        <option value="株式">株式</option>
        <option value="その他">その他</option>
      </select>
    </div>

    <button type="submit" class="btn btn-success">追加</button>
  </form>
</div>
