<?= $this->partial('common/header') ?>
<h1>會議</h1>
<form class="filter-bar" method="get" action="/meets">
  <label>從 <input type="date" name="from" value="<?= $this->escape($this->from) ?>"></label>
  <label>到 <input type="date" name="to" value="<?= $this->escape($this->to) ?>"></label>
  <span class="type-filter">
  <?php foreach (MeetHelper::TYPE_ORDER as $t) { ?>
    <label class="chip-check"><input type="checkbox" name="type[]" value="<?= $t ?>" <?= in_array($t, $this->types) ? 'checked' : '' ?>> <?= $t ?></label>
  <?php } ?>
  </span>
  <button type="submit">更新</button>
</form>
<p class="pager">
  <a href="/meets?from=<?= $this->prev['from'] ?>&to=<?= $this->prev['to'] ?><?= $this->types ? '&' . http_build_query(['type' => $this->types]) : '' ?>">← 更早一週</a>
  <span class="muted"><?= MeetHelper::fmtDate($this->from) ?> ～ <?= MeetHelper::fmtDate($this->to) ?></span>
  <?php if ($this->to < date('Y-m-d', strtotime('+14 days'))) { ?>
  <a href="/meets?from=<?= $this->next['from'] ?>&to=<?= $this->next['to'] ?><?= $this->types ? '&' . http_build_query(['type' => $this->types]) : '' ?>">下一週 →</a>
  <?php } ?>
</p>
<?php if (!$this->days) { ?>
  <p class="empty">這段期間沒有符合的會議。</p>
<?php } ?>
<?php foreach ($this->days as $day => $meets) { ?>
<h3 class="day-title"><?= MeetHelper::fmtDate($day) ?><?= $day == date('Y-m-d') ? '　今天' : '' ?></h3>
<?= $this->partial('common/meet-list', ['meets' => $meets]) ?>
<?php } ?>
<p class="legend muted">狀態：公報定稿＝立法院公報已出版的正式紀錄；AI 轉錄稿＝由會議影片自動轉出，可能有錯；產生中＝影片已上傳、整場轉錄完成後一次放上；已排定＝還沒開會或影片還沒上傳；沒有影片＝立法院沒有這場會議的影片。</p>
<?= $this->partial('common/footer') ?>
