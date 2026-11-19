<?= $this->partial('common/header') ?>
<h1>搜尋發言</h1>
<form class="search-form" action="/search" method="get" role="search">
  <input type="search" name="q" value="<?= $this->escape($this->q) ?>" placeholder="法案、議題或某句話" aria-label="搜尋發言內容" required>
  <input type="text" name="speaker" value="<?= $this->escape($this->speaker) ?>" placeholder="發言者（選填）" aria-label="發言者">
  <select name="type" aria-label="會議種類">
    <option value="">所有會議種類</option>
    <?php foreach (MeetHelper::TYPE_ORDER as $t) { ?><option value="<?= $t ?>" <?= $this->type === $t ? 'selected' : '' ?>><?= $t ?></option><?php } ?>
  </select>
  <button type="submit">搜尋</button>
</form>
<p class="hint muted">搜尋範圍：第 <?= MeetHelper::defaultTerm() ?> 屆公報已出版會議的發言（院會、委員會、聯席會議、全院委員會、公聽會、黨團協商），依整句比對，俗稱請改用正式名稱。AI 轉錄稿暫不在搜尋範圍內。</p>

<?php if ($this->result) { $r = $this->result; ?>
<p class="result-count">找到 <?= number_format($r->total) ?> 則發言<?= $r->total > 0 ? "，第 {$r->page} / {$r->total_page} 頁" : '' ?></p>
<?php if ($r->total == 0) { ?>
<div class="empty">
  <p>找不到「<?= $this->escape($this->q) ?>」。可以試試：</p>
  <ul>
    <li>換較短的詞，或用法律的正式名稱（例如「菸害防制法」而不是「菸防法」）</li>
    <li>如果是最近的會議，公報可能還沒出版，到<a href="/meets">會議</a>裡找那一場的 AI 轉錄稿</li>
  </ul>
</div>
<?php } ?>
<?php foreach ($r->groups as $g) { $meet = $g['meet']; ?>
<section class="result-group">
  <h3>
    <a href="/meet/<?= rawurlencode($g['meet_id']) ?>"><?= $this->escape($meet ? $meet->title : $g['meet_id']) ?></a>
    <span class="muted"><?= $meet ? $this->escape(MeetHelper::fmtDates($meet->dates)) : '' ?><?= $meet && $meet->committees ? '　' . $this->escape(implode('、', $meet->committees)) : '' ?></span>
  </h3>
  <ul class="hit-list">
  <?php foreach ($g['hits'] as $h) { ?>
    <li class="hit">
      <a class="hit-speaker" href="<?= ($h->發言者身分 ?? '') === '委員' ? '/legislator/' . rawurlencode($h->發言者) : '#' ?>"><?= $this->escape($h->發言者 ?? '') ?></a>
      <span class="title muted"><?= $this->escape($h->發言者職稱 ?? $h->發言者身分 ?? '') ?></span>
      <p class="hit-text"><?= SearchHelper::highlightHtml($h) ?></p>
      <a class="hit-link" href="/meet/<?= rawurlencode($g['meet_id']) ?>?view=gazette&doc=<?= rawurlencode($h->文件編號 ?? '') ?>#<?= $this->escape($h->發言編號) ?>">看這段<?= !empty($h->影片->IVOD_ID) ? '、播放影片' : '' ?> →</a>
    </li>
  <?php } ?>
  </ul>
</section>
<?php } ?>
<?php if ($r->total_page > 1) { ?>
<p class="pager">
  <?php $base = '/search?' . http_build_query(['q' => $this->q, 'speaker' => $this->speaker, 'type' => $this->type]); ?>
  <?php if ($r->page > 1) { ?><a href="<?= $base ?>&page=<?= $r->page - 1 ?>">← 上一頁</a><?php } ?>
  <span class="muted">第 <?= $r->page ?> / <?= $r->total_page ?> 頁</span>
  <?php if ($r->page < $r->total_page) { ?><a href="<?= $base ?>&page=<?= $r->page + 1 ?>">下一頁 →</a><?php } ?>
</p>
<?php } ?>
<?php } ?>
<?= $this->partial('common/footer') ?>
