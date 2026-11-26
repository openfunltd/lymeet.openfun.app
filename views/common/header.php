<?php
$site = '立法院會議逐字稿';
$title = ($this->title ?? false) ? "{$this->title}｜{$site}" : $site;
$nav = $this->nav ?? '';
$ticker = MeetHelper::ticker();
$today_live = 0;
if (isset($this->today_meets)) {
    foreach ($this->today_meets as $tm) {
        if (!empty($tm->is_live)) {
            $today_live ++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $this->escape($title) ?></title>
  <meta name="description" content="<?= $this->escape($this->description ?? '不用等公報。會議影片上架後就有 AI 逐字稿，分段對照影片，當天掌握立法院討論什麼。') ?>">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= $this->escape($title) ?>">
  <meta property="og:description" content="<?= $this->escape($this->description ?? '') ?>">
  <link rel="icon" href="/static/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700;900&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/static/css/common.css?v=<?= filemtime(MINI_ENGINE_ROOT . '/static/css/common.css') ?>">
</head>
<body class="<?= $this->escape($this->body_class ?? '') ?>">
<header class="site gutter"><div class="wrap">
  <a class="brand" href="/" aria-label="回首頁"><img src="/static/img/logo-mark-dark.svg" width="34" height="27" alt=""><span class="zh">立法院會議逐字稿</span></a>
  <div class="mbtns">
    <button type="button" class="mbtn" data-act="msearch" aria-label="搜尋"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 16l4.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
    <button type="button" class="mbtn" data-act="mnav" aria-label="選單"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
  </div>
  <nav class="main" aria-label="主要導覽">
    <a href="/meets" <?= $nav == 'meets' ? 'aria-current="true"' : '' ?>>會議</a>
    <a href="/legislators" <?= $nav == 'people' ? 'aria-current="true"' : '' ?>>立委</a>
    <a href="/laws" <?= $nav == 'laws' ? 'aria-current="true"' : '' ?>>法律</a>
    <a href="/learn" <?= $nav == 'learn' ? 'aria-current="true"' : '' ?>>說明</a>
  </nav>
  <form class="hsearch" action="/search" method="get" role="search" <?= ($this->body_class ?? '') === 'home' ? 'hidden' : '' ?>><div class="searchbox"><input id="hq" type="search" name="q" value="<?= $this->escape($this->q ?? '') ?>" placeholder="搜尋法案、議題或立委" aria-label="搜尋法案、議題或立委"><button>搜尋</button></div></form>
</div></header>
<div class="ticker gutter" id="ticker" aria-label="最新"><div class="wrap">
  <span class="day">今天 <?= SiteHelper::mmdd($ticker['today']) ?> 星期<?= SiteHelper::dow($ticker['today']) ?></span>
  <?php if ($today_live) { ?><span class="livebadge">直播中 <?= $today_live ?> 場</span><?php } ?>
  <?php if ($ticker['next']) { ?><a class="linkish ti" href="/meet/<?= rawurlencode($ticker['next']['id']) ?>"><b>下一場</b><?= SiteHelper::mmdd($ticker['next']['date']) ?> <?= $this->escape($ticker['next']['label']) ?></a><?php } ?>
  <?php if ($ticker['ai'] and $ticker['ai']['id']) { ?><a class="linkish ti" href="/meet/<?= rawurlencode($ticker['ai']['id']) ?>"><b>新上線 AI 轉錄稿</b><?= $this->escape(mb_substr($ticker['ai']['title'], 0, 24)) ?></a><?php } ?>
  <?php if ($ticker['third']) { ?><a class="linkish ti" href="/meet/<?= rawurlencode($ticker['third']['id']) ?>"><b>最近三讀</b><?= SiteHelper::mmdd($ticker['third']['date']) ?> 院會 <?= intval($ticker['third']['n']) ?> 案</a><?php } ?>
  <button type="button" class="linkish tmore" data-act="mticker">更多 ▾</button>
</div></div>
<main class="gutter"><div class="wrap" id="app">
