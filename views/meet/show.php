<?php
$m = $this->meet;
$g = $this->gazette;
$ai = $this->ai;
$isAI = ($this->view_mode === 'ai' and $ai);
$isGZ = (bool)$g;
$hasText = ($isAI or $isGZ);
$seg = MeetHelper::isSeg($m);
$this->body_class = 'meet-page';
$e = fn($s) => $this->escape($s);

// 影片：全部用 Full；Clip 只拿「委員名稱＋起訖時間」當時間軸的標籤
$videos = [];
foreach ($this->fulls as $f) {
    $videos[] = [
        'id' => intval($f->IVOD_ID), 'url' => $f->video_url ?? null, 'ivod_url' => $f->IVOD_URL ?? null,
        'start' => $f->開始時間 ?? null, 'end' => $f->結束時間 ?? null, 'duration' => MeetHelper::toSeconds($f->影片長度 ?? 0),
        'date' => substr($f->開始時間 ?? '', 0, 10),
    ];
}
$clipData = [];
foreach ($this->clips as $c) {
    $name = str_replace(' ', '', $c->委員名稱 ?? '');
    if ($name === '' or $name === '完整會議' or empty($c->開始時間)) {
        continue;
    }
    // 對到哪支 Full：開始時間落在哪支影片的區間
    $vid = null;
    foreach ($videos as $v) {
        if ($v['start'] and $c->開始時間 >= $v['start'] and (!$v['end'] or $c->開始時間 <= $v['end'])) {
            $vid = $v;
            break;
        }
    }
    $clipData[] = ['name' => $name, 'start' => $c->開始時間, 'end' => $c->結束時間 ?? null, 'ivod' => $vid['id'] ?? null, 'offset' => $vid ? max(0, strtotime($c->開始時間) - strtotime($vid['start'])) : null, 'offsetEnd' => ($vid and !empty($c->結束時間)) ? max(0, strtotime($c->結束時間) - strtotime($vid['start'])) : null];
}
$hasVid = count($videos) > 0;

// 公報卷期、頁碼
$vols = [];
foreach ($this->pdfs as $p) {
    if ($p['gazette_id']) {
        $vols[preg_replace('#\d+冊$#u', '', MeetHelper::gazetteVol($p['gazette_id']))] = true;
    }
}
$pages = $g['pages'] ?? null;
$gzVol = $pages && $pages['gazette_id'] ? MeetHelper::gazetteVol($pages['gazette_id']) : (array_key_first($vols) ?: '');
$pdf = $this->pdfs[0]['url'] ?? null;
foreach ($this->pdfs as $p) {
    if ($g and $p['agenda_id'] === $g['doc_id']) {
        $pdf = $p['url'];
    }
}
$pageText = ($pages and $pages['from']) ? ($pages['from'] . '–' . ($pages['to'] ?: $pages['from'])) : '';

// 黨籍（那場會議所屬屆的黨籍）
$parties = [];
try {
    foreach (LegislatorHelper::listTerm($m->term ?: MeetHelper::defaultTerm()) as $nm => $p) {
        $parties[$nm] = $p['party'];
    }
} catch (Exception $ex) {
}

$ivodLive = $m->ivod_links[0] ?? 'https://ivod.ly.gov.tw/';
$noGz = ($m->type === '黨團協商' or ($m->type === '公聽會' and !$m->committees));
$lyzer = null;
if ($isGZ and in_array($m->type, ['委員會', '公聽會']) and $g['doc_id']) {
    $lyzer = 'https://lyzer.tw/gazettes/' . rawurlencode($g['doc_id']) . '/';
}
$bills = $this->bills;
$minfo = array_filter([$m->subject ? '事由' : '', $bills ? '議案' : '', $m->docs ? '文件' : '', ($isGZ and ($gzVol or $pdf)) ? '公報' : '', $hasText ? '時間軸' : '']);
?>
<?= $this->partial('common/header') ?>
<div class="stack">
<div class="meethead mband <?= $isAI ? 'ai' : 'gz' ?>">
  <div class="row"><?= SiteHelper::statusTag($m) ?><?= SiteHelper::kind($m) ?></div>
  <h1><?= $e($m->title) ?></h1>
  <?php if ($isGZ and $gzVol) { ?><div class="bigline"><span class="small">公報</span><span class="bignum"><?= $e($gzVol) ?></span><?php if ($pageText) { ?><span class="small muted">這份文件第 <?= $e($pageText) ?> 頁</span><?php } ?></div>
  <?php } elseif ($isAI) { ?><div class="bigline"><span class="bignum"><?= $e(SiteHelper::hms($this->ai_ivod->開始時間 ?? '')) ?></span><span>點逐字稿裡任何一句，就從那句開始播放</span></div><?php } ?>
  <div class="meta"><span><?= count($m->dates) > 1 ? '分 ' . count($m->dates) . ' 天開：' : '' ?><?= $e(implode('、', array_map([SiteHelper::class, 'fmtFull'], $m->dates))) ?></span><?php if ($m->chair) { ?><span>主席：<a class="linkish" href="<?= isset($parties[$m->chair]) ? '/legislator/' . rawurlencode($m->chair) : '/search?q=' . rawurlencode($m->chair) ?>"><?= $e($m->chair) ?></a></span><?php } ?></div>
  <button type="button" class="minfobtn" data-act="minfo" aria-expanded="false"><span>會議資訊：<?= $e(implode('、', $minfo)) ?></span><span class="mi-o">展開 ▾</span><span class="mi-c">收起 ▴</span></button>
  <div class="ctx">
    <?php if ($m->subject) { ?><details class="reason" <?= $m->status === 'processing' ? 'open' : '' ?>><summary>這場會議要討論什麼（會議事由）</summary><div style="margin-top:6px;white-space:pre-wrap"><?= $e($m->subject) ?></div></details><?php } ?>
    <?php if ($m->docs) { ?><details class="bills docs"><summary>會議文件（<?= count($m->docs) ?> 份）</summary><ul><?php foreach ($m->docs as $d) { ?><li><a href="<?= $e($d['url']) ?>" target="_blank" rel="noopener"><?= $e($d['title']) ?> ↗</a><span class="ft"><?= $e($d['type']) ?></span></li><?php } ?></ul><p class="muted small" style="margin-top:8px">由立法院議事網提供，例如部會的書面報告。目前只能搜尋文件標題。</p></details><?php } ?>
    <?php if ($bills) { ?><details class="bills"><summary>這場討論的<?= Glossary::term('議案') ?>（<?= count($bills) ?> 件）</summary><ul>
      <?php foreach (array_slice($bills, 0, 40) as $b) { $title = $b->議案名稱 ?? ($b->標題 ?? ''); ?>
      <li><?= $e($title) ?>
        <?php $st = LawHelper::billSteps($b); $i = -1; foreach ($st as $j => $s) { if ($s['id'] === $m->id) { $i = $j; } } ?>
        <?php if ($i >= 0) { $nx = $st[$i + 1] ?? null; ?><div class="nextstep"><?php if (!$nx) { ?><?= $st[$i]['l'] === '院會三讀' ? '這案在這場三讀，流程結束' : '同一案的下一步：尚未排定' ?><?php } elseif ($nx['l'] === '委員會審查') { ?>同一案的下一步：委員會審查（<?= $e($nx['committee']) ?><?= $nx['id'] ? '，<a class="linkish small" href="/meet/' . rawurlencode($nx['id']) . '">' . SiteHelper::mmdd($nx['date']) . ' →</a>' : '，哪一場待資料' ?>）<?php } else { ?>同一案的下一步：<?= $e($nx['l']) ?> <?php if ($nx['id']) { ?><a class="linkish small" href="/meet/<?= rawurlencode($nx['id']) ?>"><?= SiteHelper::mmdd($nx['date']) ?> →</a><?php } ?><?php } ?></div><?php } ?>
        <?php foreach (array_slice((array)($b->法律編號 ?? []), 0, 3) as $li => $lc) { $ln = ((array)($b->{'法律編號:str'} ?? []))[$li] ?? $lc; ?> <a class="linkish small" href="/law/<?= rawurlencode($lc) ?>">→ <?= $e($ln) ?>在其他會議的討論</a><?php } ?>
      </li>
      <?php } ?>
    </ul></details><?php } ?>
  </div>
  <?php if ($isAI) { ?><div class="banner ai"><span><span class="dl">電腦自動產生，可能有錯字或認錯人，所以這個版本不提供引文。要確認請點一句聽原音。公報出版後會自動換成正式紀錄，到時就能引用。</span><span class="mo">AI 自動轉錄可能有錯，不提供引文；點一句聽原音。</span></span></div>
  <?php } elseif ($isGZ) { ?><div class="banner gz"><span class="muted"><?= $pageText ? '頁碼標在各段落' : '頁碼還沒對上' ?></span><?php if ($pdf) { ?><a href="<?= $e($pdf) ?>" target="_blank" rel="noopener">開啟公報 PDF ↗</a><?php } ?><?php if ($lyzer) { ?><a class="ext" href="<?= $e($lyzer) ?>" target="_blank" rel="noopener">看這場的 AI 摘要（LYZER，外部網站）↗</a><?php } ?></div><?php } ?>
  <?php if ($isAI and $noGz) { ?><div class="simbanner"><b>這類會議多半沒有公報</b>　黨團協商約七成、非委員會的公聽會幾乎都不刊登公報，這場會一直是 AI 轉錄稿，不能引用，只能分享連結。</div><?php } ?>
  <?php if ($isAI and !empty($ai['coverage']['gap'])) { $cv = $ai['coverage']; ?><div class="simbanner"><b>逐字稿有缺口</b>　這支影片的逐字稿只涵蓋 <?= SiteHelper::fmtSec($cv['from']) ?>–<?= SiteHelper::fmtSec($cv['to']) ?>，其餘沒有轉錄。</div><?php } ?>
  <?php if ($this->from_ai and $isGZ) { ?><div class="banner ai landing"><span class="lbl">這個連結分享時還是 AI 轉錄稿</span><span>這段現在已換成公報正式紀錄，文字可能不同。請按「播放這個段落」聽原音核對。</span></div><?php } ?>
</div>

<?php if ($m->status === 'novideo') { ?>
<div class="empty"><h3>立法院沒有這場會議的影片，所以無法產生 AI 轉錄稿</h3><p class="muted">我們不知道沒有影片的原因。可以到<a href="https://ivod.ly.gov.tw/" target="_blank" rel="noopener">立法院 IVOD ↗</a>或<a href="https://ppg.ly.gov.tw/" target="_blank" rel="noopener">議事暨公報資訊網 ↗</a>查詢這場會議。</p></div>

<?php } elseif (!$hasText) { ?>
  <?php if ($m->status === 'processing' and $m->is_live) { ?><div class="livecta"><a href="<?= $e($ivodLive) ?>" target="_blank" rel="noopener"><span class="dot" aria-hidden="true"></span>到立法院 IVOD 看直播 ↗</a></div><?php } ?>
  <?php if ($m->status === 'scheduled') { ?>
  <div class="empty"><h3>這場會議還沒開始</h3><p class="muted">開會且整場轉錄完成後，AI 轉錄稿會放在這裡。</p></div>
  <?php } elseif ($m->status === 'processing') { ?>
  <div class="empty"><h3>尚無 AI 轉錄的會議記錄</h3><p class="muted">整場轉錄完成後，會一次放上完整的 AI 轉錄稿。想先看看 AI 轉錄稿長什麼樣子，可以看最近一場已經完成的會議。</p><p style="margin-top:8px"><a class="linkish" href="/meets">最近已有 AI 轉錄稿的會議 →</a></p></div>
  <?php } else { ?>
  <div class="empty"><h3>公報已出版，發言索引正在更新</h3><p class="muted">每 3 小時更新一次，請稍後再來。</p></div>
  <?php } ?>
  <?php if ($hasVid) { ?><div class="livecta"><a href="<?= $e($videos[0]['ivod_url'] ?: $ivodLive) ?>" target="_blank" rel="noopener">在 IVOD 看整場影片 ↗</a></div><?php } ?>

<?php } else { ?>
<?php
// ===== 有逐字稿：段落目錄、議程時間軸、逐字稿、影片 =====
$units = $isGZ ? $g['units'] : [];
$timed = false;
if ($units) {
    // 議程時間軸：依單元，有起訖時間照比例，沒有就平均並標「約」
    $atl = [];
    foreach ($units as $u) {
        $t0 = null;
        $t1 = null;
        $ivods = [];
        foreach ($u['items'] as $it) {
            if (!is_null($it['start'])) {
                $t0 = is_null($t0) ? $it['start'] : min($t0, $it['start']);
                $t1 = is_null($t1) ? $it['start'] : max($t1, $it['start']);
                $ivods[$it['ivod']] = true;
            }
        }
        $atl[] = ['u' => $u, 't0' => $t0, 't1' => $t1, 'ivods' => array_keys($ivods)];
    }
    // 有起訖時間才照比例：每個單元都有時間、而且都在同一支影片裡（跨天的會議每天一支影片，秒數不能比）
    $all_ivods = array_unique(array_merge(...array_map(fn($x) => $x['ivods'], $atl)));
    $timed = count($atl) > 1 and count(array_filter($atl, fn($x) => !is_null($x['t0']))) === count($atl) and count($all_ivods) === 1;
    if ($timed) {
        $sorted = array_map(fn($x) => $x['t0'], $atl);
        $copy = $sorted; sort($copy);
        $timed = ($sorted === $copy);
    }
}
?>
<?php if ($units) { ?>
<div class="atl" aria-label="議程時間軸"><div class="ax"><span><?= $timed ? SiteHelper::fmtSec($atl[0]['t0']) : '開始' ?></span><span><?= $timed ? SiteHelper::fmtSec(end($atl)['t1']) : '結束' ?></span></div><div class="bars">
<?php foreach ($atl as $i => $x) { $u = $x['u']; $w = $timed ? max(1, ($i + 1 < count($atl) ? $atl[$i + 1]['t0'] : $x['t1'] + 60) - $x['t0']) : 1; $first = null; foreach ($u['items'] as $it) { if ($it['href']) { $first = $it['href']; break; } } ?>
  <a class="bar" style="flex:<?= $w ?> 1 0" href="<?= $e($first ?: '#unit-' . $u['no']) ?>" title="<?= $e($u['label']) ?>"><?= $e($u['label']) ?></a>
<?php } ?>
</div><?= $timed ? '' : '<p class="cap">部分單元沒有起訖時間，寬度依順序平均分配（約）。</p>' ?></div>
<?php } ?>

<div class="meetgrid <?= $hasVid ? 'hasvid' : '' ?>">
  <button type="button" class="tocbtn" data-act="mtoc">段落目錄 ☰</button>
  <aside class="toc" aria-label="段落目錄"><div class="tochead"><h3>段落目錄</h3><button type="button" class="linkish tocx" data-act="mtoc">關閉 ✕</button></div>
  <?php if ($isGZ and $units) { ?>
    <?php if (!$seg and !$g['segmented']) { ?><p class="muted small" style="padding:0 10px">這類會議還沒有分段，目錄依公報的議程項目與質詢輪次列出。</p><?php } ?>
    <?php if (($m->segmenter ?? '') === '委員會預算審查') { $orgs = []; foreach ($units as $u) { foreach ($u['items'] as $it) { if ($it['kind'] === 'group') { $orgs[SectionHelper::name($it['label'])] = true; } } } if (count($orgs) > 1) { ?>
    <div class="tocorg"><label class="muted small">機關 <select data-tocorg><option value="">全部</option><?php foreach (array_keys($orgs) as $o) { ?><option value="<?= $e($o) ?>"><?= $e($o) ?></option><?php } ?></select></label><span class="muted small">依主席宣告的機關分組，可能不完整</span></div>
    <?php } } ?>
    <?php foreach ($units as $u) {
      $single = (count($u['items']) === 1 and SectionHelper::name($u['items'][0]['label']) === $u['label']);
      $uhref = null; foreach ($u['items'] as $it) { if ($it['href']) { $uhref = $it['href']; break; } }
      if ($single or !$u['items']) { ?>
      <div class="sess"><?php if ($uhref) { ?><a class="linkish" href="<?= $e($uhref) ?>" style="font:inherit;color:inherit"><?= $e($u['label']) ?></a><?php } else { ?><?= $e($u['label']) ?><?php } ?></div>
    <?php } else { ?>
      <div class="sess" id="unit-<?= $u['no'] ?>"><?= $e($u['label']) ?></div><ol>
      <?php $org = ''; $prevDoc = null; foreach ($u['items'] as $it) {
        if ($it['kind'] === 'group') { $org = SectionHelper::name($it['label']); }
        $tt = '';
        if ($isGZ and $it['here'] and $pageText and $it['doc'] !== $prevDoc) { $tt = '頁 ' . $pageText; $prevDoc = $it['doc']; }
        $person = SectionHelper::personOf($it);
        $attr = 'class="' . ($it['here'] ? '' : 'elsewhere') . '" data-org="' . $e($org) . '"' . ($person ? ' data-person="' . $e($person) . '"' : '') . ($it['anchor'] ? ' data-anchor="' . $e($it['anchor']) . '"' : '');
      ?>
        <li <?= $attr ?>><?php if ($it['href']) { ?><a class="tocbtn-item" href="<?= $e($it['href']) ?>" data-jump="<?= $e($it['anchor']) ?>"><?php } else { ?><span class="tocbtn-item off"><?php } ?><span class="tt"><?= $e($tt) ?></span><span class="sb"></span><?= SectionHelper::tocItem($it, $parties) ?><?= $it['href'] ? '</a>' : '</span>' ?></li>
      <?php } ?>
      </ol>
    <?php } } ?>
  <?php } elseif ($isAI) { ?>
    <div class="sess">委員的影片段落</div>
    <?php if ($clipData) { ?><ol><?php foreach ($clipData as $i => $c) { ?><li data-person="<?= $e($c['name']) ?>"><?php if (!is_null($c['offset']) and $c['ivod'] === intval($this->ai_ivod->IVOD_ID)) { ?><a class="tocbtn-item" href="#ai-<?= $c['ivod'] ?>-<?= intval($c['offset']) ?>" data-play="<?= intval($c['offset']) ?>" data-ivod="<?= $c['ivod'] ?>" data-label="<?= $e($c['name']) ?> 委員"><?php } else { ?><span class="tocbtn-item off"><?php } ?><span class="tt"><?= $e(substr(SiteHelper::hms($c['start']), 0, 5)) ?></span><span class="sb"></span><span class="tperson"><?= $e($c['name']) ?> 委員<?= isset($parties[$c['name']]) ? '<span class="tparty">' . $e(SiteHelper::partyShort($parties[$c['name']])) . '</span>' : '' ?></span><?= (!is_null($c['offset']) and $c['ivod'] === intval($this->ai_ivod->IVOD_ID)) ? '</a>' : '</span>' ?></li><?php } ?></ol>
    <?php } else { ?><p class="muted small" style="padding:0 10px">這場沒有委員質詢片段。</p><?php } ?>
  <?php } ?>
  </aside>

  <div style="min-width:0">
    <div class="txsize" role="group" aria-label="逐字稿字級"><span>逐字稿字級</span><button type="button" data-txs="s" aria-pressed="false">小</button><button type="button" data-txs="m" aria-pressed="true">中</button><button type="button" data-txs="l" aria-pressed="false">大</button></div>

    <?php if ($isGZ) { ?>
    <?php if (count($g['docs']) > 1) { ?>
    <nav class="doc-nav"><span class="muted small">這場會議的公報分 <?= count($g['docs']) ?> 份文件，目前看第 <?= array_search($g['doc_id'], array_keys($g['docs'])) + 1 ?> 份：</span>
      <?php $i = 0; foreach ($g['docs'] as $doc_id => $n) { $i ++; ?><a class="chip btn <?= $doc_id === $g['doc_id'] ? 'on' : '' ?>" aria-pressed="<?= $doc_id === $g['doc_id'] ? 'true' : 'false' ?>" href="/meet/<?= rawurlencode($m->id) ?>?doc=<?= rawurlencode($doc_id) ?>" title="<?= $e($doc_id) ?>（<?= intval($n) ?> 則）"><?= $i ?></a><?php } ?>
    </nav>
    <?php } ?>
    <?php if (!$seg and !$g['segmented'] and $m->type !== '委員會') { ?><div class="banner"><span>這類會議（<?= $e(implode('、', $m->kinds) ?: $m->type) ?>）還沒有細分段落：下面是整場逐字稿，依時間排列。</span></div><?php } ?>
    <?php
    $prev_speaker = null;
    $open = false;
    $secIndex = 0;
    foreach ($g['blocks'] as $b) {
        $seq = intval($b->順序);
        foreach ($g['starts'][$seq] ?? [] as $stt) {
            $u = $stt['unit']; $it = $stt['item'];
            if ($open) { echo '</div>'; }
            $open = true;
            $secIndex ++;
            $prev_speaker = null;
            $person = SectionHelper::personOf($it);
            echo '<div class="secblock" id="sec-' . $e($it['anchor']) . '" data-anchor="' . $e($it['anchor']) . '"' . ($person ? ' data-person="' . $e($person) . '"' : '') . '>';
            $h3 = $stt['show_label'] ? SectionHelper::show($it) : $u['label'];
            $kindText = $it['type'] ?: $u['label'];
            echo '<div class="sectitle"><span class="sbar"></span>' . ($kindText !== $h3 ? '<span class="kind">' . Glossary::term($kindText) . '</span>' : '') . SectionHelper::flags($it) . '<h3>' . $e($h3) . '</h3>';
            if (!is_null($it['start'])) {
                echo '<span class="muted small mono">' . ($it['estimated'] ? '約 ' : '') . SiteHelper::fmtSec($it['start']) . '</span><button type="button" class="act" data-play="' . intval($it['start']) . '" data-ivod="' . intval($it['ivod']) . '" data-anchor="sec-' . $e($it['anchor']) . '" data-label="' . $e(SectionHelper::show($it)) . '">▶ 播放<span class="dl">這個段落</span></button>';
            } else {
                echo '<span class="muted small">' . ($hasVid ? '<span class="dl">這個段落還沒有</span><span class="mo">未</span>對到影片時間' : '這場會議沒有對應的影片') . '</span>';
            }
            if ($pageText and $pdf) {
                echo '<a class="gzlink" href="' . $e($pdf) . '" target="_blank" rel="noopener" title="段落沒有自己的頁碼，這是所在公報文件的頁碼範圍"><span class="dl">所在</span>公報<span class="dl">文件 </span>第<span class="dl"> </span>' . $e($pageText) . '<span class="dl"> </span>頁 ↗</a>';
            }
            echo '</div>';
            if ($it['kind'] === 'attachment') {
                echo '<div class="speech gap"><div class="who muted">—</div><div class="tx">本文為圖片，請看公報 PDF。</div><div></div></div>';
            }
        }
        $kind = $b->區塊種類 ?? '發言';
        $speaker = trim((string)($b->發言者 ?? ''));
        $role = $b->發言者身分 ?? '';
        $isRec = (!in_array($kind, ['發言', '主席程序']) or $speaker === '');
        $isChair = ($role === '主席' or strpos($speaker, '主席') === 0);
        $cont = (!$isRec and $speaker === $prev_speaker);
        $prev_speaker = $isRec ? null : $speaker;
        $t = $g['times'][$b->發言編號] ?? null;
        $cls = 'speech' . ($isRec ? ' rec' : '') . ($isChair ? ' chair' : '') . ($cont ? ' cont' : '');
        $title = $role === '委員' ? '委員' : ($b->發言者職稱 ?? '');
        $textLong = (in_array($kind, ['附件', '議事錄']) and mb_strlen($b->內文) > 600);
        ?>
        <div class="<?= $cls ?>" id="<?= $e($b->發言編號) ?>" data-key="<?= $e($b->發言編號) ?>" data-who="<?= $e($isRec ? '會議紀錄' : ($isChair ? '主席' : $speaker . ($title ? ' ' . $title : ''))) ?>" <?= $t ? 'data-t="' . intval($t['start']) . '" data-ivod="' . intval($t['ivod']) . '" data-est="' . ($t['estimated'] ? '1' : '0') . '"' : '' ?>>
          <div class="who"><?php if ($isRec) { ?><?= Glossary::term('紀錄文字', 'rec') ?><?= $kind === '附件' ? '<span class="role">附件' . ($speaker ? '：' . $e($speaker) : '') . '</span>' : '' ?><?php } elseif ($isChair) { ?><span class="chairname">主席</span><?php } elseif ($role === '委員') { ?><a class="linkish" href="/legislator/<?= rawurlencode($speaker) ?>"><?= $e($speaker) ?> 委員</a><span class="role">立委<?= isset($parties[$speaker]) ? '・' . $e(SiteHelper::partyShort($parties[$speaker])) : '' ?></span><?php } else { ?><span><?= $e($speaker) ?><?= $title ? ' ' . $e($title) : '' ?></span><?php if ($title) { ?><span class="role"><?= $e($role ?: '官員') ?></span><?php } ?><?php } ?><?php if ($t) { ?><div class="btime mono"><?= $t['estimated'] ? '約 ' : '' ?><?= SiteHelper::fmtSec($t['start']) ?></div><?php } ?></div>
          <?php if ($textLong) { ?><details class="tx long"><summary><?= $e(mb_substr(preg_replace('#\s+#u', ' ', $b->內文), 0, 120)) ?>…<span class="muted">（<?= mb_strlen($b->內文) ?> 字，展開）</span></summary><?= nl2br($e($b->內文)) ?></details>
          <?php } else { ?><div class="tx"><?= nl2br($e($b->內文)) ?></div><?php } ?>
          <div class="acts"><?php if (!$isRec) { ?><button type="button" class="act primary" data-quote="<?= $e($b->發言編號) ?>">引用這段</button><?php } ?></div>
        </div>
    <?php } ?>
    <?php if ($open) { echo '</div>'; } ?>

    <?php } elseif ($isAI) { $iv = intval($this->ai_ivod->IVOD_ID); ?>
    <?php if (count($this->ai_fulls) > 1) { ?><nav class="doc-nav"><span class="muted small">這場會議分 <?= count($this->ai_fulls) ?> 支影片：</span><?php foreach ($this->ai_fulls as $f) { ?><a class="chip btn <?= intval($f->IVOD_ID) === $iv ? 'on' : '' ?>" aria-pressed="<?= intval($f->IVOD_ID) === $iv ? 'true' : 'false' ?>" href="/meet/<?= rawurlencode($m->id) ?>?view=ai&ivod=<?= intval($f->IVOD_ID) ?>"><?= $e(substr($f->開始時間, 5, 5) . ' ' . substr($f->開始時間, 11, 5)) ?></a><?php } ?></nav><?php } ?>
    <?php $prevName = null; foreach ($ai['blocks'] as $b) { $unk = empty($b['name']); $isChair = $b['role'] === '主席'; $cont = (!$unk and $b['name'] === $prevName); $prevName = $unk ? null : $b['name']; ?>
      <div class="speech <?= $isChair ? 'chair' : '' ?> <?= $cont ? 'cont' : '' ?>" id="<?= $e($b['anchor']) ?>" data-key="<?= $e($b['anchor']) ?>" data-who="<?= $e($unk ? '發言者待確認' : $b['name'] . ' ' . $b['role']) ?>" data-t="<?= intval($b['start']) ?>" data-tend="<?= intval($b['end']) ?>" data-ivod="<?= $iv ?>" data-est="0">
        <div class="who"><?php if ($unk) { ?><?= Glossary::term('發言者待確認', 'unk') ?><?php } elseif ($b['role'] === '委員') { ?><a class="linkish" href="/legislator/<?= rawurlencode($b['name']) ?>"><?= $e($b['name']) ?> 委員</a><span class="role">立委<?= isset($parties[$b['name']]) ? '・' . $e(SiteHelper::partyShort($parties[$b['name']])) : '' ?></span><?php } elseif ($isChair) { ?><span class="chairname">主席<?= $b['name'] !== '主席' ? '（' . $e($b['name']) . '）' : '' ?></span><?php } else { ?><span><?= $e($b['name']) ?></span><span class="role"><?= $e($b['role']) ?></span><?php } ?><div class="btime mono"><?= SiteHelper::fmtSec($b['start']) ?></div></div>
        <div class="tx"><?php foreach ($b['sentences'] as $si => $s) { ?><span role="button" tabindex="0" class="snt" id="s-<?= $e($b['anchor']) ?>-<?= $si ?>" data-play="<?= $s['start'] ?>" data-ivod="<?= $iv ?>" data-anchor="s-<?= $e($b['anchor']) ?>-<?= $si ?>" data-label="<?= $e($unk ? '發言者待確認' : $b['name']) ?>" title="<?= SiteHelper::fmtSec($s['start']) ?> 從這句播放"><?= $e($s['text']) ?></span> <?php } ?></div>
        <div class="acts"><button type="button" class="act" data-quote="<?= $e($b['anchor']) ?>">分享這段</button></div>
      </div>
    <?php } ?>
    <?php } ?>
  </div>

  <?php if ($hasVid) { ?><aside class="vcol" aria-label="影片"><div class="vdock" id="vdock"><video id="player" controls playsinline preload="none"></video></div><div id="vctl"></div></aside><?php } ?>
</div>
<?php } ?>
</div>
<script>window.LYMEET_MEET = <?= json_encode([
    'id' => $m->id, 'title' => $m->title, 'date' => $m->date, 'dates' => $m->dates,
    'version' => $isAI ? 'ai' : ($isGZ ? 'gazette' : null),
    'gazette' => $isGZ ? ['vol' => $gzVol, 'pages' => $pageText, 'doc' => $g['doc_id']] : null,
    'videos' => $videos, 'clips' => $clipData, 'ivodLive' => $ivodLive,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?= $this->partial('common/footer') ?>
