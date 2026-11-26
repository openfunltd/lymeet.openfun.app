<?= $this->partial('common/header') ?>
<?php
$sec = function($id, $title, $sub, $days, $limit = 0) {
    $n = array_sum(array_map('count', $days));
    $out = '<section><div class="sechead"><h2>' . $title . '</h2><span class="muted small">' . $sub . '</span></div><div class="panel">';
    if (!$n) {
        return $out . '<p class="muted">沒有會議。</p></div></section>';
    }
    $out .= '<div class="list" data-list="' . $id . '" data-group="mt" data-limit="' . ($limit ?: 9999) . '">';
    $i = 0;
    foreach ($days as $d => $ms) {
        foreach ($ms as $m) {
            $html = $this->partial('common/meet-row', ['meet' => $m, 'date' => $d]);
            if ($limit and $i >= $limit) {
                $html = preg_replace('#^<div class="item"#', '<div hidden class="item"', $html, 1);
            }
            $out .= $html;
            $i ++;
        }
    }
    $out .= '</div>';
    if ($limit and $n > $limit) {
        $out .= '<button type="button" class="linkish more" data-more="' . $id . '">顯示全部 ' . $n . ' 場</button>';
    }
    return $out . '</div></section>';
};
?>
<?php if ($this->older_only) { ?>
<div class="stack">
  <div class="stack" style="gap:6px"><h1>更早的會議</h1><p class="muted"><?= SiteHelper::fmtFull($this->older_from) ?> 到 <?= SiteHelper::fmtFull($this->older_to) ?>，依日期由新到舊。</p></div>
  <?= $sec('older', '更早的會議', '依日期由新到舊', $this->older_days) ?>
  <p class="row"><a class="linkish" href="/meets?before=<?= $this->escape($this->prev_before) ?>">再往前 30 天 →</a><a class="linkish" href="/meets">回到最近的會議</a></p>
</div>
<?php } else { ?>
<div class="stack">
   <div class="stack" style="gap:6px"><h1>會議</h1><p class="muted">今天、最近和已排定的會議，也可以往前找更早的會議。整場會議轉錄完成後，AI 轉錄稿會一次放上來。公報出版後，會自動換成正式紀錄。</p></div>
   <div class="panel topfilter"><?= $this->partial('common/filter-bar', ['id' => 'mt', 'meets' => $this->all_meets]) ?></div>
   <?= $sec('mt-today', '今天', SiteHelper::fmtFull($this->today), $this->today_meets ? [$this->today => $this->today_meets] : []) ?>
   <?= $sec('mt-recent', '最近 3 天', '可以看 AI 轉錄稿', $this->recent_days) ?>
   <?= $sec('mt-next', '已排定', '未來 7 天', $this->next_days) ?>
   <?= $sec('older', '更早的會議', '依日期由新到舊', $this->older_days, 15) ?>
   <p><a class="linkish" href="/meets?before=<?= $this->escape($this->prev_before) ?>">再往前 30 天 →</a></p>
</div>
<?php } ?>
<?= $this->partial('common/footer') ?>
