<?php
$today = $this->today;
$today_meets = $this->today_meets;
$live_n = count(array_filter($today_meets, fn($m) => !empty($m->is_live)));
$next_days = $this->next_days;
$next_total = array_sum(array_map('count', $next_days));
$this->description = '不用等公報。會議影片上架後就有 AI 逐字稿，分段對照影片，當天掌握立法院討論什麼。';
?>
<?= $this->partial('common/header') ?>
<div class="stack" style="gap:48px">
  <section class="homeband">
   <div class="heroL">
    <h1>立法院今天在討論什麼？</h1>
    <p class="lead">不用等公報。會議影片上架後就有 AI 逐字稿，分段對照影片，當天掌握立法院討論什麼。</p>
    <p class="aiwarn">AI 自動轉錄可能有誤，要引用請以立法院公報為準。</p>
    <form id="bigsearch" action="/search" method="get" role="search"><div class="searchbox big"><input id="bq" type="search" name="q" placeholder="例如：菸害防制法、海洋委員會、林淑芬" aria-label="搜尋法案、議題或立委"><button>搜尋</button></div></form>
    <?php if ($this->examples) { ?><div class="row small"><span class="exlab">立法院最近 7 天常討論</span><?php foreach ($this->examples as $x) { ?><a class="chip btn" href="/search?q=<?= rawurlencode($x) ?>"><?= $this->escape($x) ?></a><?php } ?></div><?php } ?>
    <a class="firstvisit" href="/learn"><svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 4.5h5a2 2 0 0 1 2 2V16a1.6 1.6 0 0 0-1.6-1.6H3zM17 4.5h-5a2 2 0 0 0-2 2V16a1.6 1.6 0 0 1 1.6-1.6H17z" fill="none" stroke="#6158ca" stroke-width="1.5" stroke-linejoin="round"></path></svg><span>第一次看立法院的會議紀錄？</span><b>先看立法院怎麼運作 →</b></a>
    <p class="small muted">收錄 <?= $this->escape($this->cover_from) ?>（第 8 屆）起的會議</p>
   </div>
  </section>

  <?php if ($today_meets) { ?>
  <section><div class="sechead"><h2>今天的會議</h2><?php if ($live_n) { ?><span class="livebadge">直播中 <?= $live_n ?> 場</span><?php } ?></div>
    <div class="wall">
    <?php foreach ($today_meets as $m) { ?>
      <a class="tile <?= !empty($m->is_live) ? 'live' : '' ?>" href="/meet/<?= rawurlencode($m->id) ?>"><span class="scr"><?= !empty($m->is_live) ? '<span class="livebadge">LIVE</span>' : '' ?></span><span class="nm"><?= $this->escape(($m->unit and $m->unit !== $m->type) ? $m->unit : mb_substr($m->title, 0, 22)) ?></span><span class="tm"><?= $this->escape($m->type) ?></span><span><?= SiteHelper::statusTag($m) ?></span></a>
    <?php } ?>
    </div>
  </section>
  <?php } ?>

  <section><div class="sechead"><h2>會議節目表</h2><a class="linkish small" href="/meets">看全部會議 →</a></div>
   <?php if ($next_days) { ?><p class="nextline"><span class="lab">接下來</span><?php $i = 0; foreach ($next_days as $d => $ms) { if ($i ++ >= 2) break; ?><?= $i > 1 ? '、' : '' ?><b><?= SiteHelper::mmdd($d) ?></b> <?= count($ms) ?> 場<?php } ?></p><?php } ?>
   <div class="daygroups">
    <?php
    $group = function($d, $ms, $lab = '') {
        $show = array_slice($ms, 0, 1);
        $out = '<div class="dhead ' . ($lab ? 'is-today' : '') . '">' . ($lab ? '<span class="dlab">' . $lab . '</span>' : '') . '<span class="dnum">' . SiteHelper::mmdd($d) . '</span><span class="dwk">星期' . SiteHelper::dow($d) . '・<span class="mono">' . count($ms) . '</span> 場</span></div><div class="dlist">';
        foreach ($show as $m) {
            $out .= $this->partial('common/meet-row', ['meet' => $m, 'show_date' => false, 'date' => $d, 'snippet' => '']);
        }
        if (count($ms) > 1) {
            $out .= '<a class="linkish small" href="/meets">還有 ' . (count($ms) - 1) . ' 場 →</a>';
        }
        return $out . '</div>';
    };
    if ($today_meets) {
        echo $group($today, $today_meets, '今天');
    } else {
        echo '<div class="dhead is-today"><span class="dlab">今天</span><span class="dnum">' . SiteHelper::mmdd($today) . '</span><span class="dwk">星期' . SiteHelper::dow($today) . '</span></div><div class="dlist"><p class="muted" style="padding:10px 0">今天沒有排定會議。下面是最近開過的會議。</p></div>';
    }
    foreach ($this->recent_days as $d => $ms) {
        echo $group($d, $ms);
    }
    ?>
   </div></section>

  <section><h2 style="margin-bottom:16px">依法律、立委、部會找</h2><div class="entries">
    <div class="panel"><div class="sechead"><h3>依法律找</h3><a class="linkish small allidx" href="/laws">看全部法律 →</a></div><ul><?php foreach ($this->top_laws as $l) { ?><li><a class="linkish" href="/law/<?= rawurlencode($l['code']) ?>"><?= $this->escape($l['name']) ?></a></li><?php } ?></ul></div>
    <div class="panel"><div class="sechead"><h3>依部會找</h3><a class="linkish small allidx" href="/depts">看全部部會 →</a></div><ul><?php foreach ($this->top_depts as $d) { ?><li><a class="linkish" href="/dept/<?= rawurlencode($d['name']) ?>"><?= $this->escape($d['name']) ?></a><span class="mono" style="font-weight:600;color:var(--ink)"><?= intval($d['count']) ?><span class="muted small" style="font-family:var(--font);font-weight:400"> 場</span></span></li><?php } ?></ul></div>
    <div class="panel"><div class="sechead"><h3>依立委找</h3><a class="linkish small allidx" href="/legislators">看全部立委 →</a></div><ul><?php foreach ($this->top_people as $p) { ?><li><a class="linkish" href="/legislator/<?= rawurlencode($p['name']) ?>"><?= $this->escape($p['name']) ?></a><span class="muted small"><?= $this->escape(SiteHelper::partyShort($p['party'])) ?></span></li><?php } ?></ul></div>
  </div></section>
</div>
<?= $this->partial('common/footer') ?>
