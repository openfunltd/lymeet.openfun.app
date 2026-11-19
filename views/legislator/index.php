<?= $this->partial('common/header') ?>
<h1>第 <?= $this->term ?> 屆立法委員</h1>
<p class="hint muted">點進去看這位委員在各場會議的發言。官員沒有列表，從搜尋或會議頁裡的發言者名字進入。</p>
<?php foreach ($this->groups as $party => $list) { ?>
<section class="party-group">
  <h2><?= $this->escape($party) ?> <span class="muted"><?= count($list) ?> 位</span></h2>
  <ul class="legislator-grid">
  <?php foreach ($list as $l) { ?>
    <li class="<?= ($l->是否離職 ?? '') === '是' ? 'left' : '' ?>">
      <a href="/legislator/<?= rawurlencode($l->委員姓名) ?>">
        <?php if (!empty($l->照片位址)) { ?><img src="<?= $this->escape(str_replace('http://', 'https://', $l->照片位址)) ?>" alt="" loading="lazy"><?php } else { ?><span class="no-photo"></span><?php } ?>
        <span class="name"><?= $this->escape($l->委員姓名) ?></span>
        <span class="area muted"><?= $this->escape(mb_strlen($l->選區名稱 ?? '') > 12 ? mb_substr($l->選區名稱, 0, 11) . '…' : ($l->選區名稱 ?? '')) ?></span>
        <?php if (($l->是否離職 ?? '') === '是') { ?><span class="area muted">已離職<?= !empty($l->離職日期) ? '（' . $this->escape($l->離職日期) . '）' : '' ?></span><?php } ?>
      </a>
    </li>
  <?php } ?>
  </ul>
</section>
<?php } ?>
<?= $this->partial('common/footer') ?>
