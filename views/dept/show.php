<?php $name = $this->name; $ms = $this->meets; ?>
<?= $this->partial('common/header') ?>
<div class="stack">
   <div class="meethead"><span class="muted small">部會</span><h1><?= $this->escape($name) ?></h1><div class="meta"><span><?= count($ms) ?> 場會議的事由提到這個部會</span></div></div>
   <section><div class="panel">
   <?php if (!$ms) { ?><p class="muted">還沒有會議的事由提到這個部會。可以改用搜尋，找這個部會官員的發言。</p><?php } else { ?>
     <?php if (count($ms) > 6) { ?><?= $this->partial('common/filter-bar', ['id' => 'dm', 'meets' => $ms]) ?><?php } ?>
     <div class="list" data-list="dm" data-limit="12">
     <?php $i = 0; foreach ($ms as $m) { $html = $this->partial('common/meet-row', ['meet' => $m, 'q' => $name, 'snippet' => $m->topic ? '<div class="small">題目：' . $this->escape($m->topic) . '</div>' : '']); if ($i ++ >= 12) { $html = preg_replace('#^<div class="item"#', '<div hidden class="item"', $html, 1); } echo $html; } ?>
     </div>
     <?php if (count($ms) > 12) { ?><button type="button" class="linkish more" data-more="dm">顯示全部 <?= count($ms) ?> 場</button><?php } ?>
   <?php } ?>
   </div></section>
</div>
<?= $this->partial('common/footer') ?>
