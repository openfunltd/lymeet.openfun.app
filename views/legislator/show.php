<?php $info = $this->info; $name = $this->name; $r = $this->result; $rounds = $this->rounds; ?>
<?= $this->partial('common/header') ?>
<div class="stack">
   <div class="meethead"><span class="muted small">立委</span><h1><?= $this->escape($name) ?></h1>
     <dl class="profile">
      <div><dt>黨籍</dt><dd><?= SiteHelper::partyChip($info['party']) ?></dd></div>
      <div><dt>選區</dt><dd><?= $this->escape($info['area']) ?></dd></div>
      <div><dt>委員會</dt><dd><?= $info['committees'] ? $this->escape(implode('、', $info['committees'])) : '—' ?></dd></div>
      <div><dt>擔任立委</dt><dd><?php $first = true; foreach ($info['terms'] as $t => $ti) { ?><?= $first ? '' : '<br>' ?>第 <?= $t ?> 屆・<?= SiteHelper::partyChip($ti['party']) ?><?= $ti['left'] ? '（' . $this->escape($ti['leftDate']) . ' 離職）' : '' ?><?php $first = false; } ?></dd></div>
     </dl><p class="muted small">資料來源：立法院開放資料。黨籍、選區、委員會是最近一屆的資料，委員會為該屆最後一個會期；黨籍是各屆屆末的黨籍。</p>
     <div class="meta"><span>在 <?= $this->escape($this->meet_count ?? '—') ?> 場會議發言（只計算本站已有逐字稿或影片段落的會議）</span></div></div>
   <section><div class="sechead"><h2>有發言的會議</h2><form class="filters" style="margin:0" method="get" action="/legislator/<?= rawurlencode($name) ?>"><input id="pf" name="q" type="search" value="<?= $this->escape($this->q) ?>" placeholder="在發言裡找議題" aria-label="在發言裡找議題" style="border:1px solid var(--line-strong);border-radius:4px;padding:4px 8px;background:var(--surface);color:var(--ink)">
      <label class="muted">時間 <select id="pd" name="days" onchange="this.form.submit()"><option value="">不限</option><?php foreach ([3, 7, 30, 90] as $d) { ?><option value="<?= $d ?>" <?= $this->days == $d ? 'selected' : '' ?>>最近 <?= $d ?> 天</option><?php } ?></select></label><button class="act" type="submit">找</button></form></div>
    <div class="panel"><div class="list" id="plist">
    <?php if ($r->unavailable) { ?><p class="muted">發言資料暫時讀不到。</p><?php } elseif (!$r->groups) { ?><p class="muted" id="pempty"><?= ($this->q !== '' or $this->days) ? '這段期間沒有符合的發言。請放寬條件。' : '還沒有發言紀錄。' ?></p><?php } ?>
    <?php foreach ($r->groups as $g) { $m = $g['meet']; if (!$m) { continue; } $hits = $g['hits']; $first = $hits[0];
      $best = $first; foreach ($hits as $h) { if (mb_strlen($h->內文 ?? '') > mb_strlen($best->內文 ?? '')) { $best = $h; } }
      $seg = MeetHelper::isSeg($m);
      $own = $rounds[$m->id] ?? [];
      $extra = '<div class="agenda">發言 ' . count($hits) . ' 段' . ($r->total > count($hits) ? '（這一頁）' : '') . '</div>';
      $extra .= '<div class="quote">' . $this->escape(SiteHelper::snippet($best->內文 ?? '', '', 50)) . '</div>';
      if (!$seg) {
          $extra .= '<div class="small muted">這類會議還沒有分段，以下逐句列出</div>';
      } elseif ($own) {
          $extra .= '<div class="rounds"><div class="small muted">他的那一輪（' . count($own) . '）</div>';
          foreach ($own as $x) {
              $href = '/meet/' . rawurlencode($m->id) . ($x['doc'] ? '?doc=' . rawurlencode($x['doc']) : '') . ($x['anchor'] ? '#' . $x['anchor'] : '');
              $extra .= '<div class="quote"><a class="linkish" href="' . $this->escape($href) . '">' . $this->escape($x['unit']) . ' › ' . $this->escape(SiteHelper::parseSpeaker($x['label'])[0] ?: $x['label']) . '</a></div>';
          }
          $extra .= '</div>';
      }
      if (count($hits) > 1) {
          $extra .= '<details class="more-speech"><summary class="small">看這場的 ' . count($hits) . ' 段發言</summary>';
          foreach ($hits as $h) {
              $extra .= '<div class="quote"><a class="linkish small" href="/meet/' . rawurlencode($m->id) . '?doc=' . rawurlencode($h->文件編號) . '#' . $this->escape($h->發言編號) . '">' . ($this->q !== '' ? SearchHelper::highlightHtml($h, 1) : $this->escape(SiteHelper::snippet($h->內文 ?? '', '', 36))) . '</a></div>';
          }
          $extra .= '</details>';
      }
    ?>
      <?= $this->partial('common/meet-row', ['meet' => $m, 'q' => $this->q, 'snippet' => $extra, 'agenda' => '', 'focus' => $first->發言編號 ?? '']) ?>
    <?php } ?>
    </div>
    <?php if ($r->total_page > 1) { ?>
    <p class="row small" style="margin-top:12px">
      <?php $base = '/legislator/' . rawurlencode($name) . '?q=' . rawurlencode($this->q) . '&days=' . intval($this->days) . '&page='; ?>
      <?php if ($r->page > 1) { ?><a class="linkish" href="<?= $base . ($r->page - 1) ?>">← 上一頁</a><?php } ?>
      <span class="muted">第 <?= $r->page ?> 頁，共 <?= $r->total_page ?> 頁</span>
      <?php if ($r->page < $r->total_page) { ?><a class="linkish" href="<?= $base . ($r->page + 1) ?>">下一頁 →</a><?php } ?>
    </p>
    <?php } ?>
    </div></section>
</div>
<?= $this->partial('common/footer') ?>
