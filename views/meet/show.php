<?php
$m = $this->meet;
$videos = [];
foreach ($this->fulls as $f) {
    $videos[] = [
        'id' => intval($f->IVOD_ID),
        'url' => $f->video_url ?? null,
        'ivod_url' => $f->IVOD_URL ?? null,
        'start' => $f->開始時間 ?? null,
        'label' => substr($f->開始時間 ?? '', 0, 10) . ' ' . substr($f->開始時間 ?? '', 11, 5) . '～' . substr($f->結束時間 ?? '', 11, 5),
        'ai' => in_array('ai-transcript', (array)($f->支援功能 ?? [])),
    ];
}
$this->body_class = 'meet-page';
?>
<?= $this->partial('common/header') ?>
<article class="meet">
<header class="meet-head">
  <p class="meet-kind"><?= $this->escape($m->type) ?><?= $m->committees ? '・' . $this->escape(implode('、', $m->committees)) : '' ?>
    <span class="badge badge-<?= $this->escape($m->status) ?>"><?= $this->escape($m->status_label) ?></span></p>
  <h1><?= $this->escape($m->title) ?></h1>
  <p class="meet-meta">
    <?= MeetHelper::fmtDates($m->dates) ?>
    <?php if ($m->start_time) { ?>　<?= $this->escape(substr($m->start_time, 11, 5)) ?><?= $m->end_time ? '～' . $this->escape(substr($m->end_time, 11, 5)) : '' ?><?php } ?>
    <?php if ($m->room) { ?>　<?= $this->escape($m->room) ?><?php } ?>
    <?php if ($m->chair) { ?>　主席 <?= $this->escape($m->chair) ?><?php } ?>
  </p>
  <?php if ($m->subject) { ?>
  <details class="meet-subject" open>
    <summary>會議事由</summary>
    <p><?= nl2br($this->escape($m->subject)) ?></p>
  </details>
  <?php } ?>
  <?php if ($m->bills or $m->attachments or $this->pdfs or $m->ppg_url) { ?>
  <details class="meet-docs">
    <summary>議案與會議文件</summary>
    <?php if ($m->bills) { ?>
    <h4>議案</h4>
    <ul>
    <?php foreach ($m->bills as $b) { ?>
      <li><?= $this->escape($b->標題 ?? $b->議案編號 ?? '') ?><?php if (!empty($b->議案編號)) { ?> <a class="muted" href="https://ppg.ly.gov.tw/ppg/bills/<?= $this->escape($b->議案編號) ?>/details" target="_blank" rel="noopener">議案</a><?php } ?></li>
    <?php } ?>
    </ul>
    <?php } ?>
    <?php if ($m->attachments) { ?>
    <h4>會議文件</h4>
    <ul>
    <?php foreach ($m->attachments as $a) { ?>
      <li><a href="<?= $this->escape($a->連結) ?>" target="_blank" rel="noopener"><?= $this->escape($a->標題 ?: basename($a->連結)) ?></a> <span class="muted"><?= $this->escape($a->格式 ?? '') ?></span></li>
    <?php } ?>
    </ul>
    <?php } ?>
    <?php if ($this->pdfs) { ?>
    <h4>公報</h4>
    <ul>
    <?php foreach ($this->pdfs as $p) { ?>
      <li><a href="<?= $this->escape($p['url']) ?>" target="_blank" rel="noopener">立法院公報 <?= $this->escape($p['gazette_id'] ?? '') ?></a><?php if ($p['page_start']) { ?> <span class="muted">第 <?= intval($p['page_start']) ?>～<?= intval($p['page_end']) ?> 頁</span><?php } ?></li>
    <?php } ?>
    </ul>
    <?php } ?>
    <?php if ($m->ppg_url) { ?><p><a href="<?= $this->escape($m->ppg_url) ?>" target="_blank" rel="noopener">立法院議事網的會議頁 ↗</a></p><?php } ?>
  </details>
  <?php } ?>
</header>

<?php
$has_gazette = $m->status === 'gazette';
$has_ai = count($this->ai_fulls) > 0;
?>
<?php if ($has_gazette or $has_ai) { ?>
<nav class="view-switch">
  <?php if ($has_gazette) { ?><a class="<?= $this->view_mode == 'gazette' ? 'active' : '' ?>" href="/meet/<?= rawurlencode($m->id) ?>?view=gazette">公報定稿</a><?php } ?>
  <?php if ($has_ai) { ?><a class="<?= $this->view_mode == 'ai' ? 'active' : '' ?>" href="/meet/<?= rawurlencode($m->id) ?>?view=ai">AI 轉錄稿</a><?php } ?>
  <?php if ($this->view_mode == 'ai' and count($this->ai_fulls) > 1) { ?>
  <span class="muted">影片：
    <?php foreach ($this->ai_fulls as $f) { ?>
      <a class="<?= intval($f->IVOD_ID) === intval($this->ai_ivod->IVOD_ID) ? 'active' : '' ?>" href="/meet/<?= rawurlencode($m->id) ?>?view=ai&ivod=<?= intval($f->IVOD_ID) ?>"><?= $this->escape(substr($f->開始時間, 5, 5) . ' ' . substr($f->開始時間, 11, 5)) ?></a>
    <?php } ?>
  </span>
  <?php } ?>
</nav>
<?php } ?>

<?php if ($this->view_mode == 'gazette' and $this->gazette) { ?>
<?php $g = $this->gazette; ?>
<div class="transcript-layout">
  <aside class="toc">
    <h3>段落目錄</h3>
    <ol>
    <?php foreach ($g['toc'] as $t) { ?>
      <li class="toc-<?= $t['kind'] ?>"><a href="#<?= $this->escape($t['anchor']) ?>"><?= $this->escape($t['label']) ?></a></li>
    <?php } ?>
    </ol>
  </aside>
  <section class="transcript gazette">
    <?php if (count($g['docs']) > 1) { ?>
    <nav class="doc-nav">
      <span class="muted">這場會議的公報分 <?= count($g['docs']) ?> 份文件：</span>
      <?php $i = 0; foreach ($g['docs'] as $doc_id => $n) { $i ++; ?>
        <a class="<?= $doc_id === $g['doc_id'] ? 'active' : '' ?>" href="/meet/<?= rawurlencode($m->id) ?>?view=gazette&doc=<?= rawurlencode($doc_id) ?>" title="<?= $this->escape($doc_id) ?>（<?= intval($n) ?> 則）"><?= $i ?></a>
      <?php } ?>
    </nav>
    <?php } ?>
    <p class="transcript-note">立法院公報的正式紀錄<?= count($g['docs']) > 1 ? '，第 ' . (array_search($g['doc_id'], array_keys($g['docs'])) + 1) . ' 份文件' : '' ?>，<?= count($g['blocks']) ?> 則。有影片時間的段落點「播放」會跳到那一段。</p>
    <?php $prev_speaker = null; $prev_section = null; ?>
    <?php foreach ($g['blocks'] as $b) { ?>
      <?php $sec = trim((string)($b->段落 ?? '')); if ($sec !== '' and $sec !== $prev_section) { $prev_section = $sec; $prev_speaker = null; ?>
      <h3 class="section-title" id="<?= $this->escape($b->發言編號) ?>-sec"><?= $this->escape($sec) ?></h3>
      <?php } ?>
      <?php $kind = $b->區塊種類 ?? '發言'; $speaker = $b->發言者 ?? ''; ?>
      <div class="block kind-<?= $this->escape($kind) ?>" id="<?= $this->escape($b->發言編號) ?>">
        <?php if (in_array($kind, ['發言', '主席程序']) and $speaker !== '' and $speaker !== $prev_speaker) { $prev_speaker = $speaker; ?>
        <div class="speaker role-<?= $this->escape($b->發言者身分 ?? '') ?>">
          <?php if (($b->發言者身分 ?? '') === '委員') { ?><a href="/legislator/<?= rawurlencode($speaker) ?>"><?= $this->escape($speaker) ?></a><?php } else { ?><?= $this->escape($speaker) ?><?php } ?>
          <span class="title"><?= $this->escape($b->發言者職稱 ?? $b->發言者身分 ?? '') ?></span>
        </div>
        <?php } elseif (!in_array($kind, ['發言', '主席程序'])) { ?>
        <div class="speaker role-other"><span class="title"><?= $this->escape($kind === '附件' ? '附件：' . $speaker : $kind) ?></span></div>
        <?php } ?>
        <?php if (in_array($kind, ['附件', '議事錄']) and mb_strlen($b->內文) > 600) { ?>
        <details class="text long"><summary><?= $this->escape(mb_substr(preg_replace('#\s+#u', ' ', $b->內文), 0, 120)) ?>…<span class="muted">（<?= mb_strlen($b->內文) ?> 字，展開）</span></summary><?= nl2br($this->escape($b->內文)) ?></details>
        <?php } else { ?>
        <div class="text"><?= nl2br($this->escape($b->內文)) ?></div>
        <?php } ?>
        <div class="block-tools">
          <?php if (!empty($b->影片->IVOD_ID)) { ?>
          <button type="button" class="play" data-ivod="<?= intval($b->影片->IVOD_ID) ?>" data-offset="<?= intval($b->影片->秒數) ?>">▶ 播放 <?= TranscriptHelper::fmtOffset($b->影片->秒數) ?><?= ($b->時間來源 ?? '') === 'interpolated' ? '（約）' : '' ?></button>
          <?php } ?>
          <button type="button" class="copy-link" data-anchor="<?= $this->escape($b->發言編號) ?>">複製連結</button>
          <button type="button" class="copy-quote" data-anchor="<?= $this->escape($b->發言編號) ?>" data-speaker="<?= $this->escape($speaker) ?>" data-meet="<?= $this->escape($m->title) ?>" data-date="<?= $this->escape($b->日期) ?>" data-gazette="<?= $this->escape($b->公報編號 ?? '') ?>" data-page="<?= intval($b->公報起始頁 ?? 0) ?>">複製引文</button>
        </div>
      </div>
    <?php } ?>
  </section>
  <?= $this->partial('meet/video', ['videos' => $videos, 'meet' => $m]) ?>
</div>

<?php } elseif ($this->view_mode == 'ai' and $this->ai) { ?>
<?php $ai = $this->ai; $iv = $this->ai_ivod; ?>
<div class="transcript-layout">
  <aside class="toc">
    <h3>段落目錄</h3>
    <?php if ($ai['toc']) { ?>
    <ol>
    <?php foreach ($ai['toc'] as $t) { ?>
      <li class="toc-turn"><a href="#" class="seek" data-ivod="<?= intval($iv->IVOD_ID) ?>" data-offset="<?= intval($t['offset']) ?>"><?= $this->escape($t['label']) ?> <span class="muted"><?= TranscriptHelper::fmtOffset($t['offset']) ?></span></a></li>
    <?php } ?>
    </ol>
    <?php } else { ?>
    <p class="muted">這場沒有委員質詢片段，目錄依發言者切換。</p>
    <?php } ?>
  </aside>
  <section class="transcript ai">
    <p class="transcript-note"><span class="badge badge-ai">AI 轉錄稿</span> 由會議影片自動轉出，文字和發言者都可能有錯，引用前請聽原音核對；公報出版後會自動換成正式紀錄。共 <?= intval($ai['sentences']) ?> 句，認得出發言者的有 <?= intval($ai['named']) ?> 句。</p>
    <?php foreach ($ai['blocks'] as $b) { ?>
      <div class="block" id="<?= $this->escape($b['anchor']) ?>">
        <div class="speaker role-<?= $this->escape($b['role']) ?>">
          <?php if ($b['name']) { ?>
            <?php if ($b['role'] === '委員') { ?><a href="/legislator/<?= rawurlencode($b['name']) ?>"><?= $this->escape($b['name']) ?></a><?php } else { ?><?= $this->escape($b['name']) ?><?php } ?>
            <span class="title"><?= $this->escape($b['role']) ?></span>
          <?php } else { ?>
            <span class="unknown">發言者待確認</span><span class="title muted"><?= $this->escape($b['speaker'] ?? '') ?></span>
          <?php } ?>
          <button type="button" class="play" data-ivod="<?= intval($iv->IVOD_ID) ?>" data-offset="<?= intval($b['start']) ?>">▶ <?= TranscriptHelper::fmtOffset($b['start']) ?></button>
        </div>
        <div class="text sentences">
          <?php foreach ($b['sentences'] as $s) { ?><span class="sentence" data-ivod="<?= intval($iv->IVOD_ID) ?>" data-offset="<?= $s['start'] ?>" title="<?= TranscriptHelper::fmtOffset($s['start']) ?>"><?= $this->escape($s['text']) ?></span> <?php } ?>
        </div>
        <div class="block-tools">
          <button type="button" class="copy-link" data-anchor="<?= $this->escape($b['anchor']) ?>">分享這段</button>
        </div>
      </div>
    <?php } ?>
  </section>
  <?= $this->partial('meet/video', ['videos' => $videos, 'meet' => $m, 'current' => intval($iv->IVOD_ID)]) ?>
</div>

<?php } else { ?>
<section class="no-transcript">
  <?php if ($m->status === 'processing') { ?>
    <p>影片已經上傳，AI 轉錄稿產生中。整場轉錄完成後會一次放上。</p>
  <?php } elseif ($m->status === 'scheduled') { ?>
    <p>這場會議還沒有影片。開會後影片上傳，就會開始產生 AI 轉錄稿。</p>
  <?php } elseif ($m->status === 'novideo') { ?>
    <p>立法院沒有這場會議的影片，所以無法產生 AI 轉錄稿。</p>
  <?php } elseif ($m->status === 'gazette') { ?>
    <p>公報已出版，發言索引正在更新（每 3 小時），請稍後再來。</p>
  <?php } else { ?>
    <p>尚無這場會議的逐字稿。</p>
  <?php } ?>
  <?php if ($videos) { ?>
  <?= $this->partial('meet/video', ['videos' => $videos, 'meet' => $m]) ?>
  <?php } ?>
</section>
<?php } ?>
</article>
<?= $this->partial('common/footer') ?>
