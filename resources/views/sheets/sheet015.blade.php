<div class="container">
  <h2>今後の贈与計画</h2>

  <form method="POST" action="{{ route('sheet015.submit') }}">
    @csrf

    <div class="row mb-3">
      <div class="col">
        <label>予定年（例：2025）</label>
        <input type="number" name="plan_year" class="form-control">
      </div>
      <div class="col">
        <label>贈与額（円）</label>
        <input type="number" name="planned_amount" class="form-control">
      </div>
    </div>

    <div class="mb-3">
      <label>贈与対象者</label>
      <input type="text" name="target_name" class="form-control">
    </div>

    <button type="submit" class="btn btn-success">登録</button>
  </form>
</div>
