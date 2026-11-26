<?php $T = $this->term; $groups = $this->groups; $total = array_sum(array_map(fn($g) => count($g['people']), $groups)); ?>
<?= $this->partial('common/header') ?>
<div class="stack idxpage">
  <div class="stack" style="gap:6px"><h1>立委</h1><p class="muted">第 <?= $T ?> 屆（<?= LegislatorHelper::YEARS[$T] ?>）的立委，依該屆黨籍分組。每位立委一頁，列出他在哪些會議發言、說了什麼。可以輸入名字、選區或委員會。要找部長或其他官員，請用上方搜尋。</p></div>
  <div class="idxtools"><input type="search" class="idxq" placeholder="輸入名字、選區或委員會" aria-label="輸入名字、選區或委員會"><div class="filters" style="margin:0">
    <div class="filters" style="margin:0 0 8px;width:100%"><span class="muted">屆別</span><?php foreach (LegislatorHelper::TERMS as $t) { ?><a class="chip btn" aria-pressed="<?= $t === $T ? 'true' : 'false' ?>" href="/legislators?term=<?= $t ?>">第 <?= $t ?> 屆<span class="muted small" style="margin-left:4px"><?= LegislatorHelper::YEARS[$t] ?></span></a><?php } ?></div>
    <span class="muted">黨籍</span><button type="button" class="chip btn" aria-pressed="true" data-ig="">全部</button><?php foreach ($groups as $g) { ?><button type="button" class="chip btn" aria-pressed="false" data-ig="<?= $this->escape($g['short']) ?>"><?= $this->escape($g['short']) ?></button><?php } ?>
  </div></div>
  <?php foreach ($groups as $g) { ?>
  <section class="idxgrp" data-grp="<?= $this->escape($g['short']) ?>"><h2><?= SiteHelper::partyChip($g['full']) ?><span class="count"><?= $g['active'] ?></span><?php if ($g['gone']) { ?><span class="muted small" style="margin-left:8px;font-weight:400">另 <?= $g['gone'] ?> 位已離職，列在最後</span><?php } ?></h2>
    <ul class="idxlist">
    <?php foreach ($g['people'] as $p) { ?>
      <li data-tx="<?= $this->escape($p['name'] . $p['area'] . implode('', $p['committees'])) ?>" data-g="<?= $this->escape($g['short']) ?>"><a class="linkish" href="/legislator/<?= rawurlencode($p['name']) ?>"><?= $this->escape($p['name']) ?></a><span class="meta"><?php if ($p['left']) { ?><?= $this->escape($p['area']) ?>・<b>已離職</b>（<?= $this->escape(trim($p['leftDate'] . ' ' . $p['leftReason'])) ?>）<?php } else { ?><?= $this->escape($p['area'] . ($p['committees'] ? '・' . implode('、', $p['committees']) : '')) ?><?php } ?></span><span class="n"><?= intval($p['count']) ?> 段</span></li>
    <?php } ?>
    </ul></section>
  <?php } ?>
  <p class="muted small">黨籍是立法院資料記的該屆黨籍；屆中換黨的，顯示屆末的黨籍。</p>
  <p class="muted small idxempty" hidden>沒有符合的項目。</p>
</div>
<?= $this->partial('common/footer') ?>
