<?php $l = $this->legislator; $r = $this->result; ?>
<?= $this->partial('common/header') ?>
<section class="person-head">
  <?php if (!empty($l->照片位址)) { ?><img class="person-photo" src="<?= $this->escape(str_replace('http://', 'https://', $l->照片位址)) ?>" alt=""><?php } ?>
  <div>
    <h1><?= $this->escape($l->委員姓名) ?> <span class="muted">委員</span></h1>
    <p class="person-meta">
      <?= $this->escape($l->黨籍 ?: '無黨籍') ?>　<?= $this->escape($l->選區名稱 ?? '') ?>　第 <?= $this->term ?> 屆
      <?php if (($l->是否離職 ?? '') === '是') { ?>　<span class="badge badge-novideo">已離職<?= !empty($l->離職日期) ? ' ' . $this->escape($l->離職日期) : '' ?></span><?php } ?>
    </p>
    <?php if (!empty($l->委員會)) { ?><p class="muted small"><?= $this->escape(implode('、', (array)$l->委員會)) ?></p><?php } ?>
    <?php if (!is_null($this->speech_count)) { ?><p class="person-stats">公報紀錄裡有 <?= number_format($this->speech_count) ?> 則發言，分布在 <?= $this->escape($this->meet_count) ?> 場會議<span class="muted">（只計算本站已有公報發言的會議）</span></p><?php } ?>
  </div>
</section>

<form class="search-form inline" method="get" action="/legislator/<?= rawurlencode($l->委員姓名) ?>">
  <input type="search" name="q" value="<?= $this->escape($this->q) ?>" placeholder="在這位委員的發言裡找議題，例如：托嬰" aria-label="在發言裡找">
  <button type="submit">找</button>
  <?php if ($this->q !== '') { ?><a class="muted" href="/legislator/<?= rawurlencode($l->委員姓名) ?>">清除</a><?php } ?>
</form>

<h2><?= $this->q !== '' ? '提到「' . $this->escape($this->q) . '」的發言（' . number_format($r->total) . ' 則）' : '最近的發言' ?></h2>
<?php if (!empty($r->unavailable)) { ?>
  <p class="empty">公報發言的查詢功能尚未開放（LYAPI 的 /speeches 端點還沒上線）。</p>
<?php } elseif ($r->total == 0) { ?>
  <p class="empty"><?= $this->q !== '' ? '沒有找到提到這個詞的發言。' : '公報紀錄裡還沒有這位委員的發言。' ?></p>
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
      <p class="hit-text"><?= SearchHelper::highlightHtml($h) ?></p>
      <a class="hit-link" href="/meet/<?= rawurlencode($g['meet_id']) ?>?view=gazette&doc=<?= rawurlencode($h->文件編號 ?? '') ?>#<?= $this->escape($h->發言編號) ?>">看這段<?= !empty($h->影片->IVOD_ID) ? '、播放影片' : '' ?> →</a>
    </li>
  <?php } ?>
  </ul>
</section>
<?php } ?>
<?php if ($r->total_page > 1) { ?>
<p class="pager">
  <?php $base = '/legislator/' . rawurlencode($l->委員姓名) . '?' . http_build_query(['q' => $this->q, 'term' => $this->term]); ?>
  <?php if ($r->page > 1) { ?><a href="<?= $base ?>&page=<?= $r->page - 1 ?>">← 上一頁</a><?php } ?>
  <span class="muted">第 <?= $r->page ?> / <?= $r->total_page ?> 頁</span>
  <?php if ($r->page < $r->total_page) { ?><a href="<?= $base ?>&page=<?= $r->page + 1 ?>">下一頁 →</a><?php } ?>
</p>
<?php } ?>
<?= $this->partial('common/footer') ?>
