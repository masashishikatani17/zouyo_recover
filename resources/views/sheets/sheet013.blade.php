<div class="container">
  <h2>提案書の表題入力</h2>

  <form method="POST" action="{{ route('sheet013.submit') }}">
    @csrf

    <div class="mb-3">
      <label class="form-label">お客様名</label>
      <input type="text" name="client_name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">提案書の表題</label>
      <input type="text" name="proposal_title" class="form-control">
    </div>

    <div class="mb-3">
      <label class="form-label">提案者名（士業など）</label>
      <input type="text" name="advisor_name" class="form-control">
    </div>

    <div class="mb-3">
      <label class="form-label">日付</label>
      <input type="date" name="proposal_date" class="form-control" value="{{ date('Y-m-d') }}">
    </div>

    <button type="submit" class="btn btn-primary">保存</button>
  </form>
</div>
