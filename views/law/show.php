<?php
$name = $this->name;
$code = $this->code;
$rows = $this->rows;
$brs = $this->branches;
$r1 = $this->r1;
$n1 = count(array_filter($rows, fn($x) => $x['only1']));
$all = $r1 ? $rows : array_values(array_filter($rows, fn($x) => !$x['only1']));
$stage_help = Glossary::stageHelp();
$step_plain = Glossary::stepPlain();
?>
<?= $this->partial('common/header') ?>
<div class="stack">
   <div class="meethead"><span class="muted small">法律</span><h1><?= $this->escape($name) ?></h1>
     <div class="meta"><span>法律編號 <span class="mono"><?= $this->escape($code) ?></span></span></div>
     <?php if (!$brs) { ?><div class="extlinks"><a class="act" href="https://lawtrace.tw/law/history/<?= rawurlencode($code) ?>" target="_blank" rel="noopener">完整立法歷程與條文比較，請到 LawTrace ↗</a></div><?php } ?></div>
   <?php if ($brs) { ?>
   <section><div class="sechead"><h2>修法進度</h2><span class="muted small"><?= count($brs) > 1 ? '共 ' . count($brs) . ' 個修法案。' : '' ?>每個修法階段開了哪些會議，點了就能看那場會議。完整立法歷程與條文比較，請到 <a href="https://lawtrace.tw/law/history/<?= rawurlencode($code) ?>" target="_blank" rel="noopener">LawTrace ↗</a></span></div>
    <div class="branches">
    <?php foreach ($brs as $br) {
      $names = array_values(array_unique(array_filter(array_map(fn($b) => LawHelper::draftName($b->議案名稱 ?? ''), $br['bills']))));
      $props = array_values(array_unique(array_filter(array_map(fn($b) => LawHelper::proposer($b->議案名稱 ?? '', $b), $br['bills']))));
      $names_show = count($names) > 5 ? implode('、', array_slice($names, 0, 5)) . '…等 ' . count($names) . ' 件' : implode('、', $names);
      $props_show = count($props) > 5 ? implode('、', array_slice($props, 0, 5)) . '…等 ' . count($props) . ' 位' : implode('、', $props);
      $nb = count($br['bills']);
    ?>
      <div class="branch"><div class="bhead"><b>修法案：<?= $this->escape($names_show ?: '（案名待補）') ?></b><span class="muted small"><?= $nb > 1 ? $nb . ' 件提案' . ($br['pending'] ? '，都還在這一步' : '一起處理') . '・' : '' ?>提案人：<?= $this->escape($props_show) ?></span></div>
      <ol class="bsteps">
      <?php foreach ($br['steps'] as $j => $s) {
        $last = $j === count($br['steps']) - 1;
        $rg = $br['range'][$s['l']] ?? [];
        $multi = count($rg) > 1;
        $date = $multi ? SiteHelper::mmdd($rg[0]) . '–<br>' . SiteHelper::mmdd(end($rg)) : SiteHelper::mmdd($s['date']);
        $plain = $step_plain[$s['l']] ?? '';
        if ($multi) { $plain .= '（' . $nb . ' 件提案各自在不同日期）'; }
        $ms = ($s['id'] and !$multi) ? array_merge([['id' => $s['id'], 'date' => $s['date']]], $s['more']) : [];
        if ($s['l'] === '委員會審查') {
            $plain = $this->escape($s['committee']) . '審查' . ($s['id'] ? '' : '（是哪一天的哪一場會議，等資料串接後補上）');
        } elseif ($s['l'] === '黨團協商') {
            $plain = '各黨團代表協商這個草案';
        }
        if (count($ms) > 1) { $plain .= '。這件草案先後排進 ' . count($ms) . ' 次院會的討論事項'; $date = SiteHelper::mmdd($ms[0]['date']) . '–<br>' . SiteHelper::mmdd(end($ms)['date']); }
      ?>
        <li class="<?= $last ? 'now' : '' ?>"><span class="bdate"><?= $date ?></span><span class="bdot" aria-hidden="true"></span><div class="bbody"><b><?= $this->escape($s['l']) ?></b><?= $last ? '<span class="flag">' . ($s['l'] === '院會三讀' ? '已完成' : '目前停在這裡') . '</span>' : '' ?><p><?= $plain ?></p>
          <?php if ($ms) { ?><div class="blinks"><?php foreach ($ms as $x) { $nth = preg_match('#院會-\d+-\d+-(\d+)$#', $x['id'], $mm) ? $mm[1] : ''; ?><a class="linkish small" href="/meet/<?= rawurlencode($x['id']) ?>">看 <?= SiteHelper::mmdd($x['date']) ?> <?= $nth ? "第 {$nth} 次院會" : ($s['l'] === '委員會審查' ? '這場委員會' : ($s['l'] === '黨團協商' ? '這場協商' : '這場會議')) ?> →</a><?php } ?></div><?php } ?>
        </div></li>
      <?php } ?>
      </ol></div>
    <?php } ?>
    </div></section>
   <?php } ?>
   <section><div class="sechead"><h2>討論過這部法的會議<span class="count"><?= count($all) ?> 場</span></h2><?php if ($n1) { ?><a class="linkish small" href="/law/<?= rawurlencode($code) ?><?= $r1 ? '' : '?r1=1' ?>"><?= $r1 ? '隱藏只有院會一讀的會議' : "加回只有院會一讀的會議（{$n1} 場）" ?></a><?php } ?></div>
   <div class="panel">
   <?php if (!$all) { ?><p class="muted"><?= $n1 ? '這部法目前只在院會一讀（宣讀標題、交給委員會），還沒有會議實際討論。' : '還沒有會議討論過這部法。' ?></p><?php } else { ?>
     <?php if (count($all) > 6) { ?><?= $this->partial('common/filter-bar', ['id' => 'lm', 'meets' => array_map(fn($x) => $x['meet'], $all)]) ?><?php } ?>
     <div class="list" data-list="lm" data-limit="12">
     <?php $i = 0; foreach ($all as $x) { $m = $x['meet'];
       $parts = [];
       if ($x['via'] === '發言') { $parts[] = '只是在發言提到'; }
       elseif ($x['via'] === '事由' and !$x['main']) { $parts[] = '只是在會議事由提到'; }
       elseif ($x['via'] === '議案' and $x['only1']) { $parts[] = '只有宣讀標題（院會一讀）'; }
       foreach ($x['tags'] as $t) { if ($x['only1'] and $t === '院會一讀') { continue; } $parts[] = '<span class="stage" title="' . $this->escape($stage_help[$t] ?? '') . '">' . $this->escape($t) . '</span>'; }
       $agenda = $parts ? '<div class="agenda">這部法在這場：' . implode('、', $parts) . '</div>' : '';
       $snip = '';
       if ($x['via'] === '議案' and $x['snippet'] !== '') { $snip = '<div class="snip">議案：' . SiteHelper::hl($x['snippet'], $name) . '</div>'; }
       elseif ($x['via'] === '事由') { $snip = '<div class="snip">' . SiteHelper::hl($x['snippet'], $name) . '</div>'; }
       $html = $this->partial('common/meet-row', ['meet' => $m, 'q' => $name, 'agenda' => $agenda, 'snippet' => $snip]);
       if ($i ++ >= 12) { $html = preg_replace('#^<div class="item"#', '<div hidden class="item"', $html, 1); }
       echo $html;
     } ?>
     </div>
     <?php if (count($all) > 12) { ?><button type="button" class="linkish more" data-more="lm">顯示全部 <?= count($all) ?> 場</button><?php } ?>
   <?php } ?>
   </div></section>
</div>
<?= $this->partial('common/footer') ?>
