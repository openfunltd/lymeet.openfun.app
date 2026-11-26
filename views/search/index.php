<?php $r = $this->result; $q = $this->q; ?>
<?= $this->partial('common/header') ?>
<?php if (is_null($r)) { ?>
<p class="muted">請輸入要找的法案、議題或立委。</p>
<?php } elseif (!SearchHelper::total($r)) { ?>
<div class="stack"><h1>找不到「<?= $this->escape($r->orig) ?>」的結果</h1>
  <div class="empty">
   <?php if ($r->similar) { ?><div><h3>相近的關鍵字</h3><div class="row" style="margin-top:6px"><?php foreach ($r->similar as $s) { ?><a class="chip btn" href="/law/<?= rawurlencode($s['code']) ?>"><?= $this->escape($s['name']) ?></a><?php } ?></div></div><?php } ?>
   <div><h3>換個方式找</h3><div class="row" style="margin-top:6px">
     <a class="chip btn" href="/meets">看會議</a><a class="chip btn" href="/laws">瀏覽法律</a><a class="chip btn" href="/legislators">瀏覽立委</a></div></div>
   <div><h3>搜尋小技巧</h3><ul class="tips muted small"><li>用法律的正式名稱，例如 <a class="linkish" href="/search?q=菸害防制法">菸害防制法</a></li><li>改用比較短的詞，例如 <a class="linkish" href="/search?q=菸害">菸害</a></li><li>直接輸入立委名字，例如 <a class="linkish" href="/search?q=林淑芬">林淑芬</a></li><li>俗稱（例如「某某專法」）常常不是法律的正式名稱，可以到 <a class="linkish" href="/laws">法律列表</a> 找正式名稱</li></ul></div>
   <p class="muted small">目前收錄 <?= $this->escape($this->cover_from) ?> 起的會議，更早的會議還查不到。</p>
  </div></div>
<?php } else { $qq = $r->relaxed ?: $r->q; ?>
<div class="stack">
   <div class="stack" style="gap:10px"><h1>「<?= $this->escape($r->orig) ?>」的搜尋結果</h1><p class="muted small">在會議事由、發言內容和會議文件標題裡找。</p>
   <?php if ($r->relaxed) { ?><div class="banner"><span>找不到「<?= $this->escape($r->orig) ?>」，改找「<b><?= $this->escape($r->relaxed) ?></b>」的結果。</span></div><?php } ?></div>
   <?php if ($r->laws or $r->depts or $r->people) { ?><section class="entries">
     <?php if ($r->laws) { ?><div class="panel"><h3>相關法律<span class="count"><?= count($r->laws) ?></span></h3><ul><?php foreach ($r->laws as $l) { ?><li><a class="linkish" href="/law/<?= rawurlencode($l['code']) ?>"><?= SiteHelper::hl($l['name'], $qq) ?></a></li><?php } ?></ul></div><?php } ?>
     <?php if ($r->depts) { ?><div class="panel"><h3>相關部會<span class="count"><?= count($r->depts) ?></span></h3><ul><?php foreach ($r->depts as $d) { ?><li><a class="linkish" href="/dept/<?= rawurlencode($d) ?>"><?= SiteHelper::hl($d, $qq) ?></a></li><?php } ?></ul></div><?php } ?>
     <?php if ($r->people) { ?><div class="panel"><h3>立委與官員<span class="count"><?= count($r->people) ?></span></h3><ul><?php foreach ($r->people as $p) { ?><li><a class="linkish" href="/legislator/<?= rawurlencode($p['name']) ?>"><?= SiteHelper::hl($p['name'], $qq) ?></a><span class="muted small">立委</span></li><?php } ?></ul></div><?php } ?>
   </section><?php } ?>
   <section><div class="sechead"><h2>提到「<?= $this->escape($qq) ?>」的會議<span class="count"><?= array_sum($r->counts) ? ($r->counts['reason'] + $r->counts['doc'] + ($r->speech_more ? 100 : $r->counts['speech'])) . ($r->speech_more ? '+' : '') : 0 ?> 場</span></h2></div>
     <div class="doctabs stabs" role="tablist" aria-label="在哪裡找到">
     <?php foreach (SearchHelper::TABS as $k => $n) { $c = $r->counts[$k]; ?>
       <a role="tab" href="/search?q=<?= rawurlencode($r->orig) ?>&tab=<?= $k ?>" class="<?= $k === $r->tab ? 'on' : '' ?>" aria-selected="<?= $k === $r->tab ? 'true' : 'false' ?>" <?= $c ? '' : 'aria-disabled="true" tabindex="-1"' ?>>在<?= $n ?>找到 <span class="count"><?= $c ?><?= ($k === 'speech' and $r->speech_more) ? '+' : (($k !== 'speech' and $r->more_meets) ? '+' : '') ?> 場</span></a>
     <?php } ?>
     </div>
     <div class="panel stabpanel" role="tabpanel">
     <?php if (!$r->groups) { ?><p class="muted">沒有會議提到這個詞。</p><?php } else { ?>
       <?php if (count($r->groups) > 6) { ?><?= $this->partial('common/filter-bar', ['id' => 'sm', 'meets' => array_filter(array_map(fn($g) => $g['meet'], $r->groups))]) ?><?php } ?>
       <div class="list" data-list="sm" data-limit="9999">
       <?php foreach ($r->groups as $g) { $m = $g['meet']; if (!$m) { continue; } ?>
         <?php
         $extra = '';
         if ($r->tab === 'reason') {
             $extra = '<div class="quote">' . SiteHelper::hl($g['reason'], $qq) . '</div>';
         } elseif ($r->tab === 'speech') {
             $seen = [];
             $n = 0;
             foreach ($g['hits'] as $h) {
                 $key = mb_substr($h->內文 ?? '', 0, 60);
                 if (isset($seen[$key])) { continue; }
                 $seen[$key] = true;
                 if ($n ++ >= 2) { break; }
                 $extra .= '<div class="quote"><b>' . $this->escape(SearchHelper::whoLabel($h)) . '</b>' . (($h->段落 ?? '') ? '（' . $this->escape(mb_substr($h->段落, 0, 20)) . '）' : '') . '：' . SearchHelper::highlightHtml($h) . ' <a class="linkish small" href="/meet/' . rawurlencode($m->id) . '?doc=' . rawurlencode($h->文件編號) . '#' . $this->escape($h->發言編號) . '">看這段 →</a></div>';
             }
             if (count($g['hits']) > 2) {
                 $h = $g['hits'][2];
                 $extra .= '<a class="linkish small" href="/meet/' . rawurlencode($m->id) . '?doc=' . rawurlencode($h->文件編號) . '#' . $this->escape($h->發言編號) . '">還有 ' . (count($g['hits']) - 2) . ' 段 →</a>';
             }
         } else {
             foreach (array_slice($g['docs'], 0, 3) as $f) {
                 $extra .= '<div class="quote"><a href="' . $this->escape($f['url']) . '" target="_blank" rel="noopener">' . SiteHelper::hl($f['title'], $qq) . ' ↗</a></div>';
             }
             if (count($g['docs']) > 3) {
                 $extra .= '<a class="linkish small" href="/meet/' . rawurlencode($m->id) . '">看這場的全部 ' . count($g['docs']) . ' 份文件 →</a>';
             }
         }
         ?>
         <?= $this->partial('common/meet-row', ['meet' => $m, 'q' => $qq, 'snippet' => $extra]) ?>
       <?php } ?>
       </div>
       <?php if ($r->tab === 'speech' and $r->total_page > 1) { ?>
       <p class="row small" style="margin-top:12px">
         <?php if ($r->page > 1) { ?><a class="linkish" href="/search?q=<?= rawurlencode($r->orig) ?>&tab=speech&page=<?= $r->page - 1 ?>">← 上一頁</a><?php } ?>
         <span class="muted">第 <?= $r->page ?> 頁，共 <?= $r->total_page ?> 頁</span>
         <?php if ($r->page < $r->total_page) { ?><a class="linkish" href="/search?q=<?= rawurlencode($r->orig) ?>&tab=speech&page=<?= $r->page + 1 ?>">下一頁 →</a><?php } ?>
       </p>
       <?php } ?>
     <?php } ?>
     </div>
   </section>
</div>
<?php } ?>
<?= $this->partial('common/footer') ?>
